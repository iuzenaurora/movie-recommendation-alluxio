#!/usr/bin/env bash
set -euo pipefail
export HADOOP_HEAPSIZE=384
case "${1:-}" in
  namenode)
    mkdir -p /hadoop/name
    if [ ! -f /hadoop/name/current/VERSION ]; then
      hdfs namenode -format -nonInteractive -clusterid phat-movies
    fi
    exec hdfs namenode ;;
  datanode) mkdir -p /hadoop/data; exec hdfs datanode ;;
  *) exec "$@" ;;
esac
