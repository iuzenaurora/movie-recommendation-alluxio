"""Explicit ALS selected on validation ranking, with a shared candidate policy."""
import argparse
import hashlib
import json
import uuid
from pathlib import Path

import numpy as np
from pyspark import StorageLevel
from pyspark.ml.evaluation import RegressionEvaluator
from pyspark.ml.recommendation import ALS
from pyspark.sql import SparkSession, functions as F
from recommend_core import ranking_metrics


def candidate_ids(counts, known_ids, minimum):
    return {int(mid) for mid in known_ids if counts.get(int(mid), 0) >= minimum}


def choose_trial(candidate, incumbent, metric):
    if incumbent is None:
        return True
    def key(row):
        error, ndcg = float(row['rmse']), float(row['rankingAt10']['ndcg'])
        return (-ndcg, error) if metric == 'ndcg' else (error, -ndcg)
    return key(candidate) < key(incumbent)


def average_metrics(rows):
    return {key: float(np.mean([r[key] for r in rows]))
            for key in ['precision', 'recall', 'ndcg']} if rows else {}


def ranking_context(split, target, limit, seed):
    selected = [int(r.userId) for r in split.filter(F.col('split') == target)
                .select('userId').distinct().orderBy(F.xxhash64('userId', F.lit(seed)), 'userId')
                .limit(limit).collect()]
    seen, truth = {uid: set() for uid in selected}, {uid: set() for uid in selected}
    previous = ['train'] if target == 'validation' else ['train', 'validation']
    if selected:
        rows = split.filter(F.col('userId').isin(selected) & F.col('split').isin(previous + [target])) \
                    .select('userId', 'movieId', 'rating', 'split').collect()
        for r in rows:
            uid, mid = int(r.userId), int(r.movieId)
            if r['split'] in previous:
                seen[uid].add(mid)
            elif float(r.rating) >= 4.0:
                truth[uid].add(mid)
    return {'selected': selected, 'seen': seen, 'truth': truth, 'split': target}


def evaluate_ranking(model, context, counts, minimum, k=10):
    items = sorted(model.itemFactors.collect(), key=lambda r: r.id)
    mids = np.array([r.id for r in items], dtype=np.int32)
    factors = np.array([r.features for r in items], dtype=np.float64)
    allowed = candidate_ids(counts, mids, minimum)
    indices = np.array([i for i, mid in enumerate(mids) if int(mid) in allowed], dtype=int)
    ids, q = mids[indices], factors[indices]
    vectors = {int(r.id): np.asarray(r.features, dtype=np.float64)
               for r in model.userFactors.filter(F.col('id').isin(context['selected'])).collect()}
    popular = sorted(allowed, key=lambda mid: (-counts[mid], mid))
    ranking, baseline, lengths = [], [], []
    total_relevant = available = no_factor = no_positive = 0
    for uid in context['selected']:
        if uid not in vectors:
            no_factor += 1
            continue
        relevant = context['truth'][uid]
        if not relevant:
            no_positive += 1
            continue
        scores = q @ vectors[uid]
        mask = np.isfinite(scores) & np.array(
            [int(mid) not in context['seen'][uid] for mid in ids], dtype=bool)
        ix = np.flatnonzero(mask)
        order = np.lexsort((ids[ix], -scores[ix]))[:k]
        rec = [int(ids[i]) for i in ix[order]]
        pop = [mid for mid in popular if mid not in context['seen'][uid]][:k]
        ranking.append(ranking_metrics(rec, relevant, k))
        baseline.append(ranking_metrics(pop, relevant, k))
        lengths.append(len(rec))
        total_relevant += len(relevant)
        available += len(relevant & allowed)
    return {
        'rankingAt10': average_metrics(ranking), 'popularityAt10': average_metrics(baseline),
        'rankingUsersEvaluated': len(ranking), 'rankingUsersSampled': len(context['selected']),
        'usersExcludedNoFactor': no_factor, 'usersExcludedNoRelevant': no_positive,
        'candidateMovies': len(allowed), 'knownModelMovies': len(mids),
        'catalogRetainedPct': 100 * len(allowed) / len(mids) if len(mids) else 0.0,
        'relevantAvailablePct': 100 * available / total_relevant if total_relevant else 0.0,
        'avgALSListLength': float(np.mean(lengths)) if lengths else 0.0,
        'evaluationSplit': context['split'], 'k': k,
    }


def make_als(rank, reg, iterations, seed, blocks):
    return ALS(rank=rank, regParam=reg, maxIter=iterations, seed=seed,
               userCol='userId', itemCol='movieId', ratingCol='rating',
               implicitPrefs=False, nonnegative=False, coldStartStrategy='drop',
               numUserBlocks=blocks, numItemBlocks=blocks)


def export(model, spark, data, out, metadata, reg, model_version,
           counts=None, minimum=0, allowed_ids=None):
    dst = Path(out)
    dst.mkdir(parents=True, exist_ok=False)
    users = sorted(model.userFactors.collect(), key=lambda r: r.id)
    items = sorted(model.itemFactors.collect(), key=lambda r: r.id)
    np.savez_compressed(dst / 'factors.npz',
        user_ids=np.array([r.id for r in users], dtype=np.int32),
        user_factors=np.array([r.features for r in users], dtype=np.float32),
        movie_ids=np.array([r.id for r in items], dtype=np.int32),
        movie_factors=np.array([r.features for r in items], dtype=np.float32))
    if allowed_ids is not None:
        allowed = {int(mid) for mid in allowed_ids} & {int(r.id) for r in items}
    else:
        if counts is None and minimum > 0:
            raise ValueError('Train counts required for candidate threshold')
        allowed = candidate_ids(counts or {}, [r.id for r in items], minimum)
    movies = [r.asDict() for r in spark.read.parquet(data + '/movies').collect()]
    # Full-data popularity and histories are for serving demos, never evaluation.
    popularity = [r.asDict() for r in spark.read.parquet(data + '/ratings').groupBy('movieId')
                  .agg(F.count('*').alias('count'), F.avg('rating').alias('mean'))
                  .orderBy(F.desc('count'), 'movieId').collect()]
    profile_ids = [int(r.id) for r in users[:20]]
    profiles = {str(uid): {} for uid in profile_ids}
    for r in spark.read.parquet(data + '/ratings').filter(F.col('userId').isin(profile_ids)).collect():
        profiles[str(r.userId)][str(r.movieId)] = float(r.rating)
    for name, value in [('movies.json', movies), ('popularity.json', popularity),
                        ('profiles.json', profiles), ('candidate_movie_ids.json', sorted(allowed))]:
        (dst / name).write_text(json.dumps(value, ensure_ascii=False), encoding='utf-8')
    manifest = {
        **metadata, 'modelVersion': model_version, 'regParam': reg,
        'modelProtocolVersion': 'explicit-als-validation-ranking-v2',
        'candidatePolicy': {'minTrainRatings': minimum, 'idsFile': 'candidate_movie_ids.json',
                            'countSource': 'parent candidate ids retained after serving retrain' if allowed_ids is not None
                                           else ('evaluation train split only; retained after refit' if counts is not None
                                                 else 'all model items; no count threshold'),
                            'candidateMovies': len(allowed)},
        'profilePolicy': '20 profiles; full dataset history for demo only; never used in evaluation',
    }
    manifest['checksums'] = {p.name: hashlib.sha256(p.read_bytes()).hexdigest()
                             for p in dst.iterdir() if p.is_file()}
    (dst / 'manifest.json').write_text(json.dumps(manifest, indent=2), encoding='utf-8')
    model.write().save(str(dst / 'spark-model'))


def main():
    p = argparse.ArgumentParser()
    p.add_argument('--data', required=True); p.add_argument('--manifest', required=True)
    p.add_argument('--out', required=True); p.add_argument('--refit', action='store_true')
    p.add_argument('--ranks', type=int, nargs='+', default=[20])
    p.add_argument('--regs', type=float, nargs='+', default=[0.1, 0.2])
    p.add_argument('--max-iter', type=int, default=10)
    p.add_argument('--min-train-ratings', type=int, default=100)
    p.add_argument('--selection-metric', choices=['ndcg', 'rmse'], default='ndcg')
    p.add_argument('--ranking-users', type=int, default=200)
    p.add_argument('--seed', type=int, default=42); p.add_argument('--num-blocks', type=int, default=2)
    a = p.parse_args()
    if (any(r <= 0 for r in a.ranks) or any(not np.isfinite(r) or r <= 0 for r in a.regs)
            or a.max_iter <= 0 or a.min_train_ratings < 0 or a.ranking_users <= 0 or a.num_blocks <= 0):
        p.error('Positive ranks, regularization, iterations, users and blocks required; minimum >= 0')
    if Path(a.out).exists():
        raise ValueError('Output exists: use a new model folder')
    metadata = json.loads(Path(a.manifest).read_text())
    spark = SparkSession.builder.appName('MovieLens-ALS-Ranking-v2').getOrCreate()
    train = val = pred = best = None
    try:
        split = spark.read.parquet(a.data + '/split')
        train = split.filter("split='train'").drop('split').persist(StorageLevel.MEMORY_AND_DISK)
        val = split.filter("split='validation'").drop('split').persist(StorageLevel.MEMORY_AND_DISK)
        test = split.filter("split='test'").drop('split')
        counts = {int(r.movieId): int(r['count']) for r in train.groupBy('movieId').count().collect()}
        if not any(n >= a.min_train_ratings for n in counts.values()):
            raise ValueError('No candidate movies; choose a lower min-train-ratings')
        val_total = val.count()
        context = ranking_context(split, 'validation', a.ranking_users, a.seed)
        evaluator = RegressionEvaluator(labelCol='rating', predictionCol='prediction', metricName='rmse')
        trials, best_trial = [], None
        for rank in a.ranks:
            for reg in a.regs:
                print(f'Fitting rank={rank}, regParam={reg}, maxIter={a.max_iter}', flush=True)
                model = make_als(rank, reg, a.max_iter, a.seed, a.num_blocks).fit(train)
                prediction = model.transform(val).persist(StorageLevel.MEMORY_AND_DISK)
                try:
                    scored = prediction.count()
                    if not scored:
                        model.userFactors.unpersist(); model.itemFactors.unpersist()
                        continue
                    rmse = evaluator.evaluate(prediction)
                    rank_result = evaluate_ranking(model, context, counts, a.min_train_ratings)
                    if not rank_result['rankingUsersEvaluated']:
                        raise ValueError('No validation ranking users')
                    trial = {'rank': rank, 'regParam': reg, 'maxIter': a.max_iter,
                             'rmse': rmse, 'scoredRows': scored, 'totalRows': val_total,
                             'coverage': scored / val_total, **rank_result}
                    trials.append(trial)
                    print('Validation:', json.dumps(trial), flush=True)
                    if choose_trial(trial, best_trial, a.selection_metric):
                        if best is not None:
                            best.userFactors.unpersist(); best.itemFactors.unpersist()
                        best, best_trial = model, trial
                    else:
                        model.userFactors.unpersist(); model.itemFactors.unpersist()
                finally:
                    prediction.unpersist()
        if best is None:
            raise ValueError('No supported validation predictions')
        # Test does not participate in parameter selection.
        pred = best.transform(test).persist(StorageLevel.MEMORY_AND_DISK)
        total, scored = test.count(), pred.count()
        if not scored:
            raise ValueError('No supported test predictions')
        rmse = evaluator.evaluate(pred)
        mae = RegressionEvaluator(labelCol='rating', predictionCol='prediction', metricName='mae').evaluate(pred)
        means = train.groupBy('movieId').agg(F.avg('rating').alias('movieMean'))
        mean = train.agg(F.avg('rating')).first()[0]
        base = pred.drop('prediction').join(means, 'movieId', 'left').withColumn(
            'prediction', F.coalesce(F.col('movieMean'), F.lit(mean)))
        test_result = evaluate_ranking(best, ranking_context(split, 'test', a.ranking_users, a.seed),
                                       counts, a.min_train_ratings)
        metrics = {
            'trials': trials,
            'chosen': {'rank': best_trial['rank'], 'regParam': best_trial['regParam'],
                       'maxIter': a.max_iter, 'seed': a.seed},
            'selectionMetric': a.selection_metric,
            'selectionPolicy': 'validation NDCG descending then RMSE ascending' if a.selection_metric == 'ndcg'
                               else 'validation RMSE ascending then NDCG descending',
            'validation': best_trial,
            'test': {'rmse': rmse, 'mae': mae, 'scoredRows': scored, 'totalRows': total,
                     'coverage': scored / total, 'baselineMovieMeanRMSEOnSamePairs': evaluator.evaluate(base)},
            **test_result,
            'rankingPolicy': {'positiveRatingCutoff': 4.0, 'minTrainRatings': a.min_train_ratings,
                'candidateCountSource': 'train only; same candidates for ALS and popularity',
                'validationSeen': 'train only', 'testSeen': 'train and validation',
                'relevanceDenominator': 'all positives, including unknown or filtered movies',
                'sampling': 'up to ranking-users by xxhash64(userId,seed), then userId',
                'ties': 'score descending, movieId ascending'},
            'ratingMetricsScope': 'evaluation model before optional refit; supported test pairs only',
        }
        metadata.update({'metrics': metrics, 'trainingInput': a.data, 'servingRefit': a.refit})
        chosen_model = best
        if a.refit:
            chosen_model = make_als(best_trial['rank'], best_trial['regParam'], a.max_iter, a.seed,
                                    a.num_blocks).fit(spark.read.parquet(a.data + '/ratings'))
        export(chosen_model, spark, a.data, a.out, metadata, best_trial['regParam'],
               'als-' + uuid.uuid4().hex[:12], counts, a.min_train_ratings)
        print(json.dumps(metrics, indent=2), flush=True)
    finally:
        for frame in [pred, val, train]:
            if frame is not None:
                frame.unpersist()
        spark.stop()


if __name__ == '__main__':
    main()
