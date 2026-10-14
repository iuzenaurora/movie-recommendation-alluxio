#!/usr/bin/env bash
set -euo pipefail
exec spark-submit --master 'local[2]' --driver-memory "${SPARK_DRIVER_MEMORY:-2g}" \
  --jars /opt/alluxio/client.jar \
  --conf spark.hadoop.fs.alluxio.impl=alluxio.hadoop.FileSystem \
  --conf spark.hadoop.fs.AbstractFileSystem.alluxio.impl=alluxio.hadoop.AlluxioFileSystem \
  --conf spark.hadoop.dfs.client.use.datanode.hostname=true \
  --conf spark.hadoop.alluxio.master.hostname=alluxio-master \
  --conf spark.hadoop.alluxio.user.file.readtype.default=CACHE \
  --conf spark.hadoop.alluxio.user.file.writetype.default=CACHE_THROUGH \
  --conf spark.sql.shuffle.partitions=8 \
  "$@"
