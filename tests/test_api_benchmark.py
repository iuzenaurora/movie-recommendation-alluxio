import hashlib
import importlib.util
import json
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def load(name):
    spec = importlib.util.spec_from_file_location(name, ROOT / 'scripts' / (name + '.py'))
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


try:
    import httpx
    import pymongo
    benchmark = load('benchmark_recommendation_api')
except ImportError:
    benchmark = None


@unittest.skipIf(benchmark is None, 'Run in evaluator container with HTTPX and PyMongo')
class ApiMeasurementTest(unittest.TestCase):
    def test_errors_are_counted_not_hidden_in_latency_percentiles(self):
        rows = [{'ok': True, 'clientMs': 10., 'serverMs': 5.},
                {'ok': True, 'clientMs': 30., 'serverMs': 15.},
                {'ok': False, 'clientMs': 60000., 'serverMs': None}]
        result = benchmark.summarize_requests(rows, 2.)
        self.assertEqual(result['errors'], 1)
        self.assertAlmostEqual(result['errorRate'], 1 / 3)
        self.assertEqual(result['p50Ms'], 20.)
        self.assertEqual(result['p95Ms'], 29.)
        self.assertEqual(result['successRequestsPerSecond'], 1.)

    def test_all_failed_workload_has_no_success_percentile(self):
        result = benchmark.summarize_requests([{'ok': False, 'clientMs': 5., 'serverMs': None}], 1.)
        self.assertIsNone(result['p95Ms'])
        self.assertEqual(result['errorRate'], 1.)


class ReportPublicationTest(unittest.TestCase):
    def test_modified_quality_cannot_be_combined_with_stale_api_results(self):
        module = load('summarize_recommendation_report')
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            (root / 'api').mkdir()
            (root / 'summary.json').write_text(json.dumps({'modelVersion': 'model-b'}))
            (root / 'api/summary.json').write_text(json.dumps({'modelVersion': 'model-a', 'qualitySummarySHA256': 'wrong'}))
            with self.assertRaisesRegex(ValueError, 'provenance'):
                module.publish(root)
            self.assertFalse((root / 'acceptance.json').exists())

    def test_api_errors_prevent_complete_report(self):
        module = load('summarize_recommendation_report')
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            (root / 'api').mkdir()
            quality = root / 'summary.json'
            quality.write_text(json.dumps({'modelVersion': 'model-a'}))
            (root / 'api/summary.json').write_text(json.dumps({'modelVersion': 'model-a',
                'qualitySummarySHA256': hashlib.sha256(quality.read_bytes()).hexdigest(),
                'protocol': {'concurrency': [1], 'repeats': 1}, 'groups': [{}, {}, {}, {}],
                'aggregates': [{'errors': 1, 'requests': 2, 'successes': 1}]}))
            with self.assertRaisesRegex(ValueError, 'errors'):
                module.publish(root)
            self.assertFalse((root / 'acceptance.json').exists())


if __name__ == '__main__':
    unittest.main()
