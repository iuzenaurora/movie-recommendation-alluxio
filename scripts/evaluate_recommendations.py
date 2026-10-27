"""Evaluate the actual serving rankers on a common chronological held-out cohort."""
import argparse
import collections
import csv
import datetime
import hashlib
import json
import platform
import sys
import time
from pathlib import Path

import numpy as np
import pyarrow as pa
import pyarrow.dataset as ds

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'src'))
from evaluation_protocol import (MODES, load_package, recommend_all, select_users,
                                 summarize_rows, paired_ndcg_intervals)
from recommend_core import ranking_metrics


def read_rows(folder, users=None, columns=None):
    dataset = ds.dataset(str(folder), format='parquet', exclude_invalid_files=True,
                         ignore_prefixes=['.', '_'])
    filt = ds.field('userId').isin(users) if users is not None else None
    yield from dataset.scanner(columns=columns, filter=filt, batch_size=65536, use_threads=False).to_batches()


def verify_dataset(root, meta, model_meta):
    if meta['datasetVersion'] != model_meta['datasetVersion']:
        raise ValueError('Dataset/model version mismatch')
    checks = meta.get('fileChecksums', {})
    split_files = {p.relative_to(root).as_posix() for p in (root / 'split').rglob('*.parquet')}
    if not split_files or not split_files.issubset(checks):
        raise ValueError('Every split Parquet file must have a manifest checksum')
    for name in sorted(split_files):
        file = (root / name).resolve()
        if root.resolve() not in file.parents:
            raise ValueError('Unsafe dataset checksum path')
        with file.open('rb') as stream:
            digest = hashlib.file_digest(stream, 'sha256').hexdigest()
        if digest != checks[name]:
            raise ValueError('Dataset checksum mismatch: ' + name)


def evaluate(args):
    out = Path(args.out)
    out.mkdir(parents=True, exist_ok=False)
    started = time.perf_counter()
    meta, ids, factors, movies, candidates = load_package(args.model)
    data = Path(args.data)
    dataset_meta = json.loads((data / 'manifest.json').read_text(encoding='utf-8'))
    verify_dataset(data, dataset_meta, meta)
    print('Verified original model and split checksums', flush=True)
    all_users = set()
    for batch in read_rows(data / 'split/split=test', columns=['userId']):
        all_users.update(int(uid) for uid in batch.column('userId').to_numpy())
    users = select_users(all_users, args.users, args.seed)
    histories = {uid: [] for uid in users}
    truth = {uid: [] for uid in users}
    for split in ('train', 'validation', 'test'):
        for batch in read_rows(data / ('split/split=' + split), users,
                               ['userId', 'movieId', 'rating', 'timestamp']):
            for row in batch.to_pylist():
                target = truth if split == 'test' else histories
                target[row['userId']].append((row['timestamp'], row['movieId'], row['rating']))
    counts, sums = collections.Counter(), collections.defaultdict(float)
    for batch in read_rows(data / 'split/split=train', columns=['movieId', 'rating']):
        mids = batch.column('movieId').to_numpy()
        ratings = batch.column('rating').to_numpy()
        unique, inverse, n = np.unique(mids, return_inverse=True, return_counts=True)
        totals = np.bincount(inverse, weights=ratings)
        for mid, count, total in zip(unique, n, totals):
            counts[int(mid)] += int(count)
            sums[int(mid)] += float(total)
    expected = {int(mid) for mid in ids if counts[int(mid)] >= int(meta['candidatePolicy']['minTrainRatings'])}
    if candidates != expected:
        raise ValueError('Model candidates do not match train-only candidate policy')
    popular = [(mid, sums[mid] / counts[mid]) for mid in sorted(candidates, key=lambda mid: (-counts[mid], mid))]
    print(f'Prepared {len(users)} users and {len(candidates)} shared candidates', flush=True)
    mapping = {int(mid): i for i, mid in enumerate(ids)}
    scenarios = {}
    contexts = []
    all_rows = []
    for limit in args.history_sizes:
        scenario = 'full' if limit == 0 else 'last-' + str(limit)
        rows, errors, base_errors = [], [], []
        total_pairs, scored_pairs = 0, 0
        for index, uid in enumerate(users):
            ordered = sorted(histories[uid])
            observed = ordered[-limit:] if limit else ordered
            history = {int(mid): float(rating) for _, mid, rating in observed}
            # The API knows only the supplied history. Hidden feedback is not used
            # to improve candidate filtering in the sparse-history scenarios.
            allowed = candidates
            relevant = {int(mid) for _, mid, rating in truth[uid] if rating >= 4}
            tick = time.perf_counter()
            ranked, vector, genre = recommend_all(history, ids, factors, movies, allowed,
                                                   popular, meta['regParam'], args.k)
            computation_ms = (time.perf_counter() - tick) * 1000
            for mode in MODES:
                recommended = [mid for mid, _ in ranked[mode]]
                row = {'scenario': scenario, 'userId': uid, 'mode': mode,
                       'historySize': len(history), 'supportedRatings': sum(mid in mapping for mid in history),
                       'favoriteGenre': genre, 'relevantCount': len(relevant),
                       'relevantCandidateCount': len(relevant & allowed), 'recommended': recommended,
                       'metrics': ranking_metrics(recommended, relevant, args.k), 'fallback': vector is None}
                rows.append(row)
            if scenario == args.api_history:
                contexts.append({'userId': uid, 'history': history, 'genre': genre})
            for _, mid, rating in truth[uid]:
                total_pairs += 1
                if vector is not None and mid in mapping and counts[mid]:
                    score = float(np.asarray(factors[mapping[mid]], dtype=np.float64) @ vector)
                    errors.append(score - rating)
                    base_errors.append(sums[mid] / counts[mid] - rating)
                    scored_pairs += 1
            if (index + 1) % 25 == 0:
                print(f'{scenario}: {index + 1}/{len(users)} users; last case {computation_ms:.1f} ms', flush=True)
        modes = summarize_rows(rows, args.k)
        for value in modes.values():
            value['catalogCoverage'] = value['catalogRecommended'] / len(candidates)
        scenarios[scenario] = {
            'modes': modes, 'pairedNdcgVsFoldIn': paired_ndcg_intervals(rows, args.seed),
            'ratingPrediction': {'scope': 'unclipped fold-in and train movie mean on identical supported test pairs',
                                 'totalPairs': total_pairs, 'scoredPairs': scored_pairs,
                                 'coverage': scored_pairs / total_pairs if total_pairs else 0.,
                                 'alsFoldInRMSE': float(np.sqrt(np.mean(np.square(errors)))) if errors else None,
                                 'alsFoldInMAE': float(np.mean(np.abs(errors))) if errors else None,
                                 'movieMeanRMSE': float(np.sqrt(np.mean(np.square(base_errors)))) if errors else None,
                                 'movieMeanMAE': float(np.mean(np.abs(base_errors))) if errors else None}}
        all_rows.extend(rows)
    if not contexts:
        raise ValueError('--api-history must be included in --history-sizes')
    result = {
        'createdAtUTC': datetime.datetime.now(datetime.timezone.utc).isoformat(),
        'modelVersion': meta['modelVersion'], 'datasetVersion': meta['datasetVersion'],
        'modelManifestSHA256': hashlib.sha256((Path(args.model) / 'manifest.json').read_bytes()).hexdigest(),
        'datasetManifestSHA256': hashlib.sha256((data / 'manifest.json').read_bytes()).hexdigest(),
        'protocol': {'split': 'test', 'historySource': 'train + validation only', 'historySizes': args.history_sizes,
                     'apiHistoryScenario': args.api_history, 'usersRequested': args.users, 'usersSampled': len(users),
                     'userIds': users, 'sampling': 'SHA256(seed:userId) ascending over all test users',
                     'seed': args.seed, 'k': args.k, 'positiveRatingCutoff': 4,
                     'candidateMovies': len(candidates), 'excludeSeen': 'only supplied history, matching serving API',
                     'relevanceDenominator': 'all test positives, including unsupported or genre-filtered movies',
                     'popularity': 'train counts descending, movieId ascending; train-only means',
                     'genrePolicy': 'Laravel positive feedback >3; top genre; stable movieId-history order',
                     'hybridWeight': .75, 'noTestTuning': True, 'modelRefit': False,
                     'sparseHistoryCaveat': 'simulated limited feedback; item factors still trained on the original train split'},
        'environment': {'python': platform.python_version(), 'numpy': np.__version__, 'pyarrow': pa.__version__,
                        'platform': platform.platform()},
        'scenarios': scenarios, 'elapsedSeconds': time.perf_counter() - started,
        'limitations': ['This is a sampled offline evaluation, not a satisfaction study.',
                        'The cohort differs from the original Spark evaluation; do not compare scores as a before/after claim.',
                        'Hybrid weight and genre policy were fixed before reading test results.',
                        'Bootstrap intervals are exploratory and do not adjust for multiple comparisons.',
                        'Hybrid and genre scores are ranking scores, not calibrated rating predictions.',
                        'API latency is measured separately; offline runtime is not website response time.']}
    for name, value in [('summary.json', result), ('api-contexts.json', contexts)]:
        (out / name).write_text(json.dumps(value, indent=2, ensure_ascii=False), encoding='utf-8')
    with (out / 'per-user.jsonl').open('w', encoding='utf-8') as stream:
        for row in all_rows:
            stream.write(json.dumps(row, ensure_ascii=False) + '\n')
    with (out / 'quality.csv').open('w', newline='', encoding='utf-8-sig') as stream:
        writer = csv.DictWriter(stream, fieldnames=['scenario', 'mode', 'rankingUsers', 'precision', 'recall', 'ndcg',
                                                   'meanListLength', 'fullListRate', 'catalogCoverage', 'fallbackUsers'])
        writer.writeheader()
        for name, scenario in scenarios.items():
            for mode, values in scenario['modes'].items():
                writer.writerow({key: {'scenario': name, 'mode': mode, **values}[key] for key in writer.fieldnames})
    report = ['# Kết quả đánh giá các cách gợi ý', '',
              f"Model: `{meta['modelVersion']}`. Dataset: `{meta['datasetVersion']}`.",
              f'Mẫu {len(users)} người dùng. K={args.k}. Lịch sử chỉ từ train + validation. Phim phù hợp: test >=4 sao.',
              'Các phương pháp dùng cùng tập ứng viên và cùng mẫu. Không sử dụng profiles.json hoặc popularity toàn dataset.', '']
    for name, scenario in scenarios.items():
        report += ['## Lịch sử ' + name, '', '| Phương pháp | Người có positive test | Precision@10 | Recall@10 | NDCG@10 | Độ dài TB |',
                   '|---|---:|---:|---:|---:|---:|']
        for mode, r in scenario['modes'].items():
            report.append(f"| {mode} | {r['rankingUsers']} | {r['precision']:.6f} | {r['recall']:.6f} | {r['ndcg']:.6f} | {r['meanListLength']:.2f} |")
        report += ['', 'Chênh lệch NDCG so với ALS fold-in (bootstrap ghép cặp theo người, CI 95%):', '']
        for mode, r in scenario['pairedNdcgVsFoldIn'].items():
            report.append(f"- {mode}: {r['deltaMean']:+.6f}; [{r['lower95']:+.6f}, {r['upper95']:+.6f}].")
        e = scenario['ratingPrediction']
        report += ['', f"Fold-in RMSE={e['alsFoldInRMSE']:.6f}; MAE={e['alsFoldInMAE']:.6f}. "
                   f"Movie mean RMSE={e['movieMeanRMSE']:.6f}; MAE={e['movieMeanMAE']:.6f}. "
                   f"Cùng {e['scoredPairs']}/{e['totalPairs']} cặp test có hỗ trợ.", '']
    report += ['## Phạm vi kết luận', '', *['- ' + x for x in result['limitations']]]
    (out / 'report.md').write_text('\n'.join(report) + '\n', encoding='utf-8')
    print('Published ' + str(out / 'summary.json'), flush=True)


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--data', default='data/processed/ml32m-full-v1')
    parser.add_argument('--model', default='models/current')
    parser.add_argument('--out', required=True)
    parser.add_argument('--users', type=int, default=500)
    parser.add_argument('--seed', type=int, default=42)
    parser.add_argument('--k', type=int, default=10)
    parser.add_argument('--history-sizes', type=int, nargs='+', default=[5, 20, 0])
    parser.add_argument('--api-history', default='last-20')
    args = parser.parse_args()
    if args.users < 1 or args.k < 1 or len(set(args.history_sizes)) != len(args.history_sizes) or any(n < 0 for n in args.history_sizes):
        parser.error('Positive users/k and distinct nonnegative history sizes required')
    evaluate(args)
