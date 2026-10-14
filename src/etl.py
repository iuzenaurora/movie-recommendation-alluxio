"""Deterministic ETL. Default output refuses overwrite."""
import argparse,json,uuid
from pathlib import Path
from pyspark.sql import SparkSession,functions as F,Window
from file_checksums import parquet_checksums
def main():
    p=argparse.ArgumentParser(); p.add_argument('--raw',required=True); p.add_argument('--out',required=True)
    p.add_argument('--target-ratings',type=int,default=0); p.add_argument('--partitions',type=int,default=8); a=p.parse_args()
    spark=SparkSession.builder.appName('MovieLens-ETL').getOrCreate()
    r=spark.read.option('header',True).schema('userId INT, movieId INT, rating DOUBLE, timestamp LONG').csv(a.raw+'/ratings.csv')
    m=spark.read.option('header',True).option('multiLine',True).option('escape','"').schema('movieId INT, title STRING, genres STRING').csv(a.raw+'/movies.csv')
    before=r.count()
    valid=r.filter((F.col('userId')>0)&(F.col('movieId')>0)&F.col('timestamp').isNotNull()&(F.col('timestamp')>=0)&(~F.isnan('rating'))&F.col('rating').between(.5,5)&((F.col('rating')*2)%1==0))
    exact=valid.dropDuplicates(['userId','movieId','rating','timestamp'])
    w=Window.partitionBy('userId','movieId').orderBy(F.col('timestamp').desc(),F.col('rating').desc())
    latest=exact.withColumn('_rn',F.row_number().over(w)).filter('_rn=1').drop('_rn')
    # Metadata policy: retain one deterministic non-null metadata row; remove ratings without metadata.
    m=m.filter((F.col('movieId')>0)&F.col('title').isNotNull()).groupBy('movieId').agg(F.min('title').alias('title'),F.min('genres').alias('genres'))
    joined=latest.join(m.select('movieId'),'movieId','inner')
    joined_count=joined.count(); sample=joined
    if a.target_ratings>0 and joined_count>a.target_ratings:
        threshold=max(1,int(a.target_ratings/joined_count*1000000))
        sample=joined.filter(F.pmod(F.xxhash64('userId',F.lit(42)),F.lit(1000000))<threshold)
    sampled=sample.count()
    order=Window.partitionBy('userId').orderBy('timestamp','movieId')
    group=Window.partitionBy('userId')
    split=sample.withColumn('_n',F.count('*').over(group)).filter('_n>=5').withColumn('_i',F.row_number().over(order))
    split=split.withColumn('split',F.when(F.col('_i')<=F.floor(F.col('_n')*.8),'train').when(F.col('_i')<=F.floor(F.col('_n')*.9),'validation').otherwise('test')).drop('_n','_i')
    counts={x['split']:x['count'] for x in split.groupBy('split').count().collect()}
    version='ml-'+uuid.uuid4().hex[:12]
    # ErrorIfExists protects earlier immutable datasets.
    sample.repartition(a.partitions).write.parquet(a.out+'/ratings')
    m.repartition(2).write.parquet(a.out+'/movies')
    split.repartition(a.partitions).write.partitionBy('split').parquet(a.out+'/split')
    stats={'datasetVersion':version,'rawRows':before,'validRows':valid.count(),'exactDedupRows':exact.count(),'latestRows':latest.count(),'metadataJoinedRows':joined_count,'sampleRows':sampled,'eligibleSplitRows':sum(counts.values()),'splitCounts':counts,'users':sample.select('userId').distinct().count(),'moviesRated':sample.select('movieId').distinct().count(),'seed':42,'samplePolicy':'whole users, deterministic xxhash64 threshold; approximate target','splitPolicy':'per-user chronological 80/10/10, >=5 ratings, ties movieId','duplicatePolicy':'latest timestamp then higher rating','out':a.out}
    # Local/Drive output only for ETL; manifest travels with the folder.
    stats['maxSourceUserId']=r.agg(F.max('userId')).first()[0]
    stats['fileChecksums']=parquet_checksums(a.out)
    Path(a.out,'manifest.json').write_text(json.dumps(stats,indent=2),encoding='utf-8')
    print(json.dumps(stats,indent=2)); spark.stop()
if __name__=='__main__': main()
