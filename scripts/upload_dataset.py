"""Publish one immutable local dataset to HDFS from the compute machine."""
import argparse, json, uuid
from pathlib import Path
from pyspark.sql import SparkSession
from file_checksums import parquet_checksums

def main():
    parser=argparse.ArgumentParser()
    parser.add_argument('--data',required=True)
    parser.add_argument('--destination',required=True)
    args=parser.parse_args()
    source=Path(args.data).resolve()
    meta=json.loads((source/'manifest.json').read_text())
    if parquet_checksums(source)!=meta['fileChecksums']:
        raise ValueError('Source dataset checksums differ')
    spark=SparkSession.builder.appName('PublishImmutableDataset').getOrCreate()
    try:
        jvm=spark.sparkContext._jvm
        conf=spark.sparkContext._jsc.hadoopConfiguration()
        target=jvm.org.apache.hadoop.fs.Path(args.destination)
        fs=target.getFileSystem(conf)
        if fs.exists(target): raise ValueError('Destination already exists; choose a new dataset version')
        fs.mkdirs(target.getParent())
        stage=jvm.org.apache.hadoop.fs.Path(args.destination+'.upload-'+uuid.uuid4().hex)
        fs.copyFromLocalFile(False,False,jvm.org.apache.hadoop.fs.Path(str(source)),stage)
        for path in source.rglob('*'):
            if path.is_file() and (path.suffix=='.parquet' or path.name=='manifest.json'):
                remote=jvm.org.apache.hadoop.fs.Path(stage,path.relative_to(source).as_posix())
                if fs.getFileStatus(remote).getLen()!=path.stat().st_size:
                    raise ValueError('Uploaded file length differs: '+str(path))
        if not fs.rename(stage,target): raise RuntimeError('Failed publishing staged dataset')
        print('PUBLISHED',args.destination,meta['datasetVersion'],flush=True)
    finally: spark.stop()
if __name__=='__main__':main()
