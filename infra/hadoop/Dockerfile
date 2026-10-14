FROM eclipse-temurin:11-jre-jammy
USER root
RUN apt-get update && apt-get install -y --no-install-recommends curl ca-certificates bash && rm -rf /var/lib/apt/lists/*
ARG HADOOP_VERSION=3.3.6
RUN curl -fL --retry 3 https://archive.apache.org/dist/hadoop/common/hadoop-${HADOOP_VERSION}/hadoop-${HADOOP_VERSION}.tar.gz -o /tmp/hadoop.tgz && \
    curl -fL --retry 3 https://archive.apache.org/dist/hadoop/common/hadoop-${HADOOP_VERSION}/hadoop-${HADOOP_VERSION}.tar.gz.sha512 -o /tmp/hadoop.sha512
# Apache 3.3.6 publishes a BSD checksum with an RC1 filename. Verify the downloaded bytes,
# preserving its official digest while referencing the local archive's actual name.
RUN hadoop_digest="$(sed -n 's/^SHA512 (.*) = //p' /tmp/hadoop.sha512)" && \
    test "${#hadoop_digest}" -eq 128 && \
    printf '%s  /tmp/hadoop.tgz\n' "$hadoop_digest" | sha512sum --check -
RUN tar -xzf /tmp/hadoop.tgz -C /opt && mv /opt/hadoop-${HADOOP_VERSION} /opt/hadoop && rm /tmp/hadoop.tgz /tmp/hadoop.sha512
ENV HADOOP_HOME=/opt/hadoop HADOOP_CONF_DIR=/opt/hadoop/etc/hadoop
ENV PATH=/opt/hadoop/bin:/opt/hadoop/sbin:$PATH
COPY infra/hadoop/core-site.xml infra/hadoop/hdfs-site.xml /opt/hadoop/etc/hadoop/
COPY infra/hadoop/start.sh /start.sh
RUN chmod +x /start.sh
ENTRYPOINT ["/start.sh"]
