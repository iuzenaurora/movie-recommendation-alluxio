import importlib.util
import io
import json
import tempfile
import time
import unittest
from pathlib import Path
from unittest.mock import patch
SCRIPT=Path(__file__).resolve().parents[1]/'scripts/job_worker.py'
spec=importlib.util.spec_from_file_location('job_worker',SCRIPT)
worker=importlib.util.module_from_spec(spec)
spec.loader.exec_module(worker)

class WorkerTests(unittest.TestCase):
    def test_unknown_job_and_unsafe_model_do_not_execute(self):
        w=worker.Worker()
        with self.assertRaises(ValueError):w.execute({'id':'not-a-uuid','kind':'shell'})
        with self.assertRaises(ValueError):w.activate('../current')

    def test_validation_failure_keeps_current_model_and_api_untouched(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp)
            (root/'models/current').mkdir(parents=True)
            (root/'models/current/manifest.json').write_text('{"modelVersion":"old"}')
            (root/'models/als-candidate').mkdir()
            (root/'models/als-candidate/manifest.json').write_text('{"modelVersion":"new"}')
            w=worker.Worker(root)
            calls=[]
            def fail(args,capture=False):
                calls.append(args)
                raise RuntimeError('invalid checksum')
            w.run=fail
            with self.assertRaisesRegex(RuntimeError,'checksum'):w.activate('als-candidate')
            self.assertEqual(json.loads((root/'models/current/manifest.json').read_text())['modelVersion'],'old')
            self.assertEqual(len(calls),1)
            self.assertIn('validate_package.py', ' '.join(calls[0]))

    def test_failed_activation_health_rolls_back_current_directory(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp)
            for name,version in [('current','old'),('als-candidate','new')]:
                folder=root/'models'/name;folder.mkdir(parents=True)
                (folder/'manifest.json').write_text(json.dumps({'modelVersion':version}))
            w=worker.Worker(root);w.run=lambda *args,**kwargs:''
            class Response(io.BytesIO):
                pass
            with patch.object(worker,'urlopen',return_value=Response(b'{"modelVersion":"wrong"}')):
                with self.assertRaisesRegex(RuntimeError,'unexpected'):w.activate('als-candidate')
            self.assertEqual(json.loads((root/'models/current/manifest.json').read_text())['modelVersion'],'old')
            self.assertEqual(len(list((root/'models').glob('failed-*'))),1)

    def test_scheduler_queues_retrain_only_after_revision_threshold(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp)
            with patch.object(worker,'RUNTIME',root),patch.object(worker,'JOBS',root/'jobs'),patch.object(worker,'activity',return_value={'revision':25}),patch.object(worker,'enqueue',return_value={'id':'queued-retrain'}) as enqueue:
                worker.save(root/'automation.json',{'benchmarkHours':0,'retrainRatings':20})
                worker.save(root/'automation-state.json',{'trainedRevision':10,'benchmarkAt':time.time()})
                worker.scheduler()
                enqueue.assert_not_called()
                with patch.object(worker,'activity',return_value={'revision':30}):worker.scheduler()
                enqueue.assert_called_once_with('retrain')
                self.assertEqual(worker.read(root/'automation-state.json')['trainedRevision'],10)
                worker.save(root/'jobs/queued-retrain.json',{'status':'succeeded'})
                worker.scheduler()
                self.assertEqual(worker.read(root/'automation-state.json')['trainedRevision'],30)

    def test_failed_retrain_preserves_untrained_ratings(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp)
            with patch.object(worker,'RUNTIME',root),patch.object(worker,'JOBS',root/'jobs'),patch.object(worker,'activity',return_value={'revision':30}),patch.object(worker,'enqueue') as enqueue:
                worker.save(root/'automation.json',{'benchmarkHours':0,'retrainRatings':20})
                worker.save(root/'automation-state.json',{'trainedRevision':10,'queuedRevision':30,'retrainJob':'failed-job','retrainAt':time.time()})
                worker.save(root/'jobs/failed-job.json',{'status':'failed'})
                worker.scheduler()
                self.assertEqual(worker.read(root/'automation-state.json')['trainedRevision'],10)
                enqueue.assert_not_called()

    def test_stage_updates_preserve_job_and_complete_previous_step(self):
        with tempfile.TemporaryDirectory() as tmp:
            path=Path(tmp)/'job.json'
            worker.save(path,{'id':'example','status':'running'})
            w=worker.Worker();w.job_path=path
            w.stage('snapshot','Snapshot')
            w.stage('train','ALS')
            row=worker.read(path)
            self.assertEqual(row['id'],'example')
            self.assertEqual([s['status'] for s in row['steps']],['succeeded','running'])

    def test_retrain_auto_activates_only_when_enabled(self):
        for enabled in (False,True):
            with self.subTest(enabled=enabled),tempfile.TemporaryDirectory() as tmp:
                root=Path(tmp)
                worker.save(root/'runtime/automation.json',{'autoActivate':enabled})
                w=worker.Worker(root)
                with patch.object(w,'ensure_compute'),patch.object(w,'snapshot',return_value='runtime/snapshot.jsonl'),patch.object(w,'spark'),patch.object(w,'run'),patch.object(w,'activate',return_value={'modelVersion':'new'}) as activate:
                    result=w.retrain('example')
                    self.assertEqual(activate.call_count,1 if enabled else 0)
                    self.assertEqual(result['activation'],'automatic' if enabled else 'awaiting admin approval')

    def test_scheduler_queues_due_benchmark_and_advances_deadline(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp)
            with patch.object(worker,'RUNTIME',root),patch.object(worker,'enqueue') as enqueue:
                worker.save(root/'automation.json',{'benchmarkHours':24,'retrainRatings':0})
                worker.save(root/'automation-state.json',{'benchmarkAt':time.time()-90000})
                worker.scheduler()
                enqueue.assert_called_once_with('benchmark')
                self.assertGreater(worker.read(root/'automation-state.json')['benchmarkAt'],time.time()-10)

if __name__=='__main__':unittest.main()
