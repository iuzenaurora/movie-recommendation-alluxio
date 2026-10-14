"""Opt-in HDFS publication acceptance test; writes only a new UUID namespace."""
import importlib.util
import json
import os
import sys
import tempfile
import unittest
import uuid
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'src'))
from file_checksums import parquet_checksums

@unittest.skipUnless(os.environ.get('LIVE_HDFS')=='1','Opt-in: needs the connected HDFS machine')
class UploadAcceptanceTest(unittest.TestCase):
    def test_publish_is_complete_and_refuses_overwrite(self):
        from pyspark.sql import SparkSession
        script=Path(__file__).resolve().parents[1]/'scripts/upload_dataset.py'
        spec=importlib.util.spec_from_file_location('upload_dataset',script)
        module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
        destination='hdfs://hdfs-storage:9000/acceptance/uploads/'+uuid.uuid4().hex
        with tempfile.TemporaryDirectory() as temp:
            source=Path(temp)/'fixture';source.mkdir()
            spark=SparkSession.builder.appName('UploadAcceptanceFixture').getOrCreate()
            spark.createDataFrame([(1,10,4.0,1)],'userId INT,movieId INT,rating DOUBLE,timestamp LONG').write.parquet(str(source/'ratings'))
            (source/'manifest.json').write_text(json.dumps({'datasetVersion':'synthetic-upload-only','fileChecksums':parquet_checksums(source)}))
            spark.stop()
            with patch.object(sys,'argv',['upload','--data',str(source),'--destination',destination]):
                module.main()
            with patch.object(sys,'argv',['upload','--data',str(source),'--destination',destination]):
                with self.assertRaisesRegex(ValueError,'already exists'):module.main()
        print('UPLOAD_ACCEPTANCE_NAMESPACE',destination)

if __name__=='__main__':unittest.main()
