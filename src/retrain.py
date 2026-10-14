"""Batch serving retrain; retain parent candidate policy and label inherited metrics."""
import argparse
import hashlib
import json
import uuid
from pathlib import Path
from pyspark.sql import SparkSession
from train import export, make_als


def main():
    p = argparse.ArgumentParser()
    for name in ['data', 'manifest', 'previous', 'out']:
        p.add_argument('--' + name, required=True)
    p.add_argument('--num-blocks', type=int, default=16)
    a = p.parse_args()
    if a.num_blocks < 1: p.error('num-blocks must be positive')
    previous = Path(a.previous)
    old = json.loads((previous / 'manifest.json').read_text())
    meta = json.loads(Path(a.manifest).read_text())
    cfg = old['metrics']['chosen']
    minimum = int(old.get('candidatePolicy', {}).get('minTrainRatings', 0))
    allowed = None
    if old.get('candidatePolicy'):
        candidate_file = previous / 'candidate_movie_ids.json'
        expected = old.get('checksums', {}).get(candidate_file.name)
        if not expected or hashlib.sha256(candidate_file.read_bytes()).hexdigest() != expected:
            raise ValueError('Parent candidate policy checksum mismatch')
        allowed = json.loads(candidate_file.read_text())
    if Path(a.out).exists():
        raise ValueError('Output exists; select a new version')
    spark = SparkSession.builder.appName('Alluxio-Serving-Retrain').getOrCreate()
    try:
        ratings = spark.read.parquet(a.data + '/ratings')
        model = make_als(cfg['rank'], cfg['regParam'], cfg['maxIter'], cfg.get('seed', 42), a.num_blocks).fit(ratings)
        meta.update(metrics=old['metrics'],
                    metricsScope='Inherited from initial held-out evaluation; NOT a new evaluation of this model',
                    trainingInput=a.data, servingRefit=True, parentModelVersion=old['modelVersion'])
        export(model, spark, a.data, a.out, meta, cfg['regParam'],
               'als-' + uuid.uuid4().hex[:12], minimum=minimum, allowed_ids=allowed)
    finally:
        spark.stop()


if __name__ == '__main__':
    main()
