"""One independent Spark application, no user DataFrame cache, materialized scan."""
import argparse,json,time,datetime
from pathlib import Path
from pyspark.sql import SparkSession,functions as F
def main():
    p=argparse.ArgumentParser(); p.add_argument('--input',required=True); p.add_argument('--label',required=True); p.add_argument('--repeat',type=int,required=True); p.add_argument('--task',choices=['scan','group'],default='scan'); p.add_argument('--results',default='experiments/results'); p.add_argument('--dataset-version',default='unspecified'); a=p.parse_args()
    s=SparkSession.builder.appName('StorageBenchmark-'+a.label).getOrCreate()
    start=time.perf_counter(); d=s.read.parquet(a.input)
    if a.task=='scan':
        check=d.agg(F.count('*').alias('rows'),F.sum('rating').alias('sumRating'),F.sum('timestamp').alias('sumTimestamp')).first().asDict()
    else:
        g=d.groupBy('movieId').agg(F.count('*').alias('n'),F.sum('rating').alias('sumRating'))
        check=g.agg(F.count('*').alias('groups'),F.sum('n').alias('rows'),F.sum('sumRating').alias('sumRating')).first().asDict()
    elapsed=time.perf_counter()-start
    row={'timestampUTC':datetime.datetime.now(datetime.timezone.utc).isoformat(),'label':a.label,'repeat':a.repeat,'task':a.task,'input':a.input,'seconds':elapsed,'check':check,'sparkVersion':s.version,'master':s.sparkContext.master,'shufflePartitions':s.conf.get('spark.sql.shuffle.partitions'),'timingScope':'read+materialized action, Spark startup excluded; no explicit DataFrame persist','osCacheState':'uncontrolled; not claimed cold'}
    row['datasetVersion']=a.dataset_version
    dst=Path(a.results); dst.mkdir(parents=True,exist_ok=True)
    (dst/f'{a.task}-{a.label}-{a.repeat}.json').write_text(json.dumps(row,indent=2))
    print(json.dumps(row)); s.stop()
if __name__=='__main__': main()
