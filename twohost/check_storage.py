"""Read real HDFS and Alluxio bytes; listing metadata alone is insufficient."""
import argparse, json
from pathlib import Path
from pyspark.sql import SparkSession


def main():
    p = argparse.ArgumentParser()
    p.add_argument('--dataset', default='ml32m-full-v1')
    p.add_argument('--manifest', default='data/processed/ml32m-full-v1/manifest.json')
    p.add_argument('--hdfs-root', default='hdfs://hdfs-storage:9000/datasets/')
    p.add_argument('--alluxio-root', default='alluxio://alluxio-master:19998/datasets/')
    a = p.parse_args()
    expected = json.loads(Path(a.manifest).read_text(encoding='utf-8-sig'))
    spark = SparkSession.builder.appName('TwoHostStoragePreflight').getOrCreate()
    try:
        jvm = spark.sparkContext._jvm
        conf = spark.sparkContext._jsc.hadoopConfiguration()
        first_samples = []
        for root in [a.hdfs_root, a.alluxio_root]:
            base = root.rstrip('/') + '/' + a.dataset
            fs = jvm.org.apache.hadoop.fs.FileSystem.get(jvm.java.net.URI(base), conf)
            stream = fs.open(jvm.org.apache.hadoop.fs.Path(base + '/manifest.json'))
            try:
                remote = json.loads(str(jvm.org.apache.commons.io.IOUtils.toString(stream, 'UTF-8')))
            finally:
                stream.close()
            for key in ['datasetVersion', 'splitCounts', 'fileChecksums']:
                if remote[key] != expected[key]:
                    raise ValueError('Remote dataset differs: ' + base + ' key ' + key)
            parts = sorted(str(s.getPath()) for s in fs.listStatus(jvm.org.apache.hadoop.fs.Path(base + '/split/split=train'))
                           if s.isFile() and str(s.getPath().getName()).endswith('.parquet'))
            if not parts:
                raise ValueError('No train Parquet files at ' + base)
            stream = fs.open(jvm.org.apache.hadoop.fs.Path(parts[0]))
            try:
                sample = bytes(stream.read() for _ in range(4))
            finally:
                stream.close()
            if sample != b'PAR1':
                raise ValueError('Unexpected Parquet header at ' + parts[0])
            first_samples.append(sample)
            print('PASS manifest + actual Parquet block read:', base, flush=True)
        print('Storage preflight passed. This is a connectivity check, not a performance benchmark.', flush=True)
    finally:
        spark.stop()


if __name__ == '__main__':
    main()
