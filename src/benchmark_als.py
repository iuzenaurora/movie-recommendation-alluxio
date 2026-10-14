"""Fixed ALS workload: time train input materialization and fit separately.

No validation tuning, test evaluation, serving export, or invented speedups.
Each invocation must be a fresh Spark application.
"""
import argparse
import datetime
import json
import math
import platform
import time
from pathlib import Path

from pyspark import StorageLevel
from pyspark.ml.recommendation import ALS
from pyspark.sql import SparkSession, functions as F


def parse_args():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--data', required=True)
    p.add_argument('--manifest', required=True)
    p.add_argument('--label', choices=['H0', 'H1', 'H2', 'L0', 'L1', 'L2'], required=True)
    p.add_argument('--repeat', type=int, required=True)
    p.add_argument('--results', required=True)
    p.add_argument('--rank', type=int, default=20)
    p.add_argument('--reg', type=float, default=0.1)
    p.add_argument('--max-iter', type=int, default=10)
    p.add_argument('--num-blocks', type=int, default=16)
    p.add_argument('--seed', type=int, default=42)
    a = p.parse_args()
    if min(a.repeat, a.rank, a.max_iter, a.num_blocks) < 1 or not math.isfinite(a.reg) or a.reg <= 0:
        p.error('repeat, rank, iterations, blocks and regularization must be positive')
    return a


def main():
    a = parse_args()
    output = Path(a.results) / ('als-%s-%d.json' % (a.label, a.repeat))
    if output.exists():
        raise ValueError('Result already exists; use a new experiment directory: ' + str(output))
    manifest = json.loads(Path(a.manifest).read_text(encoding='utf-8'))
    version = manifest['datasetVersion']
    expected = int(manifest['splitCounts']['train'])
    if expected <= 0:
        raise ValueError('Manifest has no train rows')
    split_root = a.data.rstrip('/') + '/split'
    train_input = split_root + '/split=train'
    spark = SparkSession.builder.appName('ALSBenchmark-' + a.label).getOrCreate()
    train = model = None
    try:
        # Timer begins after Spark starts. Read all three ALS columns, not count-only metadata.
        begin = time.perf_counter()
        train = spark.read.option('basePath', split_root).parquet(train_input) \
            .select('userId', 'movieId', 'rating').persist(StorageLevel.MEMORY_AND_DISK)
        check = train.agg(
            F.count('*').alias('rows'),
            F.sum((F.col('rating') * 2).cast('long')).alias('sumRatingHalfUnits'),
            F.sum(F.col('userId').cast('long')).alias('sumUserId'),
            F.sum(F.col('movieId').cast('long')).alias('sumMovieId'),
        ).first().asDict()
        read_end = time.perf_counter()
        if check['rows'] != expected:
            raise ValueError('Train row count differs: %s vs manifest %s' % (check['rows'], expected))
        print('TRAIN_MATERIALIZED:', json.dumps(check), flush=True)
        params = {'rank': a.rank, 'regParam': a.reg, 'maxIter': a.max_iter,
                  'numUserBlocks': a.num_blocks, 'numItemBlocks': a.num_blocks,
                  'seed': a.seed, 'implicitPrefs': False, 'nonnegative': False,
                  'userCol': 'userId', 'itemCol': 'movieId', 'ratingCol': 'rating',
                  'coldStartStrategy': 'drop', 'intermediateStorageLevel': 'MEMORY_AND_DISK',
                  'finalStorageLevel': 'MEMORY_AND_DISK'}
        fit_begin = time.perf_counter()
        model = ALS(**params).fit(train)
        # Include complete materialization of both factor tables in the fit timer.
        user_factors, item_factors = model.userFactors.count(), model.itemFactors.count()
        finish = time.perf_counter()
        row = {
            'task': 'als', 'label': a.label, 'repeat': a.repeat, 'datasetVersion': version,
            'timestampUTC': datetime.datetime.now(datetime.timezone.utc).isoformat(),
            'input': train_input, 'expectedTrainRows': expected, 'check': check,
            'readMaterializeSeconds': read_end - begin,
            'fitMaterializeSeconds': finish - fit_begin,
            'readPlusFitSeconds': (read_end - begin) + (finish - fit_begin),
            'factorCounts': {'users': user_factors, 'movies': item_factors}, 'alsParams': params,
            'environment': {'sparkVersion': spark.version, 'master': spark.sparkContext.master,
                'driverMemory': spark.sparkContext.getConf().get('spark.driver.memory', 'unspecified'),
                'shufflePartitions': spark.conf.get('spark.sql.shuffle.partitions'),
                'pythonVersion': platform.python_version(),
                'javaVersion': spark.sparkContext._jvm.java.lang.System.getProperty('java.version')},
            'timingScope': 'read+persist+aggregate; ALS.fit+user/item factor counts; Spark startup excluded',
            'dataframeStorageLevel': 'MEMORY_AND_DISK',
            'osCacheState': 'uncontrolled; cold refers only to Alluxio cache',
            'warmLoadSecondsIncluded': False,
            'excludedWork': ['validation/test', 'parameter search', 'model export', 'web inference'],
        }
        output.parent.mkdir(parents=True, exist_ok=True)
        with output.open('x', encoding='utf-8') as f:
            json.dump(row, f, indent=2)
        print('ALS_BENCHMARK_RESULT:', json.dumps(row), flush=True)
    finally:
        if model is not None:
            model.userFactors.unpersist()
            model.itemFactors.unpersist()
        if train is not None:
            train.unpersist()
        spark.stop()


if __name__ == '__main__':
    main()
