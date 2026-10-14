FROM eclipse-temurin:11-jre-jammy
USER root
RUN apt-get update && apt-get install -y --no-install-recommends python3 python3-pip curl ca-certificates bash && rm -rf /var/lib/apt/lists/*
RUN pip3 install --no-cache-dir pyspark==3.5.6 numpy==1.26.4
RUN mkdir -p /opt/alluxio && curl -fL --retry 3 https://repo.maven.apache.org/maven2/org/alluxio/alluxio-shaded-client/2.9.5/alluxio-shaded-client-2.9.5.jar -o /opt/alluxio/client.jar
ENV PYSPARK_PYTHON=python3 PYTHONPATH=/workspace/src HADOOP_USER_NAME=root
WORKDIR /workspace
CMD ["bash"]
