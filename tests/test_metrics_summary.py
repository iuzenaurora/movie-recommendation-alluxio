import importlib.util
import json
import tempfile
import unittest
from pathlib import Path

SCRIPT = Path(__file__).resolve().parents[1] / 'scripts/summarize_alluxio_metrics.py'
spec = importlib.util.spec_from_file_location('metrics_summary', SCRIPT)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)


def metric_text(cache, rpc, ufs):
    return '\n'.join(f'Cluster.{name}  (Type: COUNTER, Value: {value})'
                     for name, value in zip(m.METRICS, (cache, rpc, ufs))) + '\n'


def fixture(root, repeats=5):
    # Entire fixture is synthetic; these timings are not user benchmark results.
    manifest = {'datasetVersion': 'synthetic-fixture', 'splitCounts': {'train': 12}}
    (root / 'dataset-manifest.json').write_text(json.dumps(manifest))
    (root / 'settings.json').write_text(json.dumps({'repeats': repeats}))
    for label in ('H0', 'H1', 'H2'):
        for i in range(1, repeats + 1):
            read = {'H0': 10, 'H1': 15, 'H2': 5}[label] + i
            row = {'task': 'als', 'label': label, 'repeat': i, 'datasetVersion': 'synthetic-fixture',
                   'check': {'rows': 12}, 'expectedTrainRows': 12, 'alsParams': {'rank': 3},
                   'environment': {'sparkVersion': 'fixture'}, 'factorCounts': {'users': 4, 'movies': 3},
                   'timingScope': 'synthetic', 'readMaterializeSeconds': read,
                   'fitMaterializeSeconds': 20, 'readPlusFitSeconds': read + 20}
            (root / f'als-{label}-{i}.json').write_text(json.dumps(row))
            if label == 'H0':
                continue
            for side in ('before', 'after'):
                warm = label == 'H2' or side == 'after'
                cached = 1024 if warm else 0
                (root / f'{label}-{i}-{side}-cache.txt').write_text(
                    f'File Size In Alluxio Path\n1024 {cached} (100%) /datasets/fixture/split/split=train\n')
                values = ('1.00MB', '2.00MB', '3.00MB')
                if side == 'after':
                    values = ('2.00MB', '3.00MB', '3.00MB' if label == 'H2' else '4.00MB')
                (root / f'{label}-{i}-{side}-metrics.txt').write_text(metric_text(*values), encoding='utf-8-sig')


class MetricsTests(unittest.TestCase):
    def test_user_repeat_one_counter_values(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            for name, values in {
                'h1-before': ('5.01MB', '7.01MB', '34.87MB'),
                'h1-after': ('37.88MB', '254.67MB', '499.61MB'),
                'h2-before': ('37.88MB', '254.67MB', '499.61MB'),
                'h2-after': ('268.03MB', '484.82MB', '499.61MB'),
            }.items():
                (root / name).write_text(metric_text(*values))
            h1 = m.counter_delta(m.read_counters(root/'h1-before'), m.read_counters(root/'h1-after'))
            h2 = m.counter_delta(m.read_counters(root/'h2-before'), m.read_counters(root/'h2-after'))
            self.assertEqual([h1[k]['deltaMiBApprox'] for k in m.METRICS], [32.87, 247.66, 464.74])
            self.assertEqual([h2[k]['deltaMiBApprox'] for k in m.METRICS], [230.15, 230.15, 0.0])
            self.assertEqual(h2['BytesReadUfsAll']['status'], 'noIncreaseAtDisplayedPrecision')
            self.assertGreater(h2['BytesReadUfsAll']['roundingUpperMiB'], 0)

    def test_units_bom_and_whitespace(self):
        with tempfile.TemporaryDirectory() as tmp:
            p = Path(tmp)/'metrics.txt'
            p.write_text(metric_text('1.00GB', '1024 KB', '0B'), encoding='utf-8-sig')
            counters = m.read_counters(p)
            self.assertEqual(counters['BytesReadCache']['bytesApprox'] / m.MIB, 1024)
            self.assertEqual(counters['BytesReadRemote']['bytesApprox'] / m.MIB, 1)

    def test_missing_and_duplicate_counter_rejected(self):
        with tempfile.TemporaryDirectory() as tmp:
            p = Path(tmp)/'metrics.txt'
            p.write_text(metric_text('1MB', '2MB', '3MB').splitlines()[0])
            with self.assertRaisesRegex(ValueError, 'Missing'):
                m.read_counters(p)
            p.write_text(metric_text('1MB', '2MB', '3MB') + metric_text('1MB', '2MB', '3MB'))
            with self.assertRaisesRegex(ValueError, 'Duplicate'):
                m.read_counters(p)

    def test_counter_reset_rejected(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            (root/'before').write_text(metric_text('2MB', '2MB', '3MB'))
            (root/'after').write_text(metric_text('1MB', '2MB', '3MB'))
            with self.assertRaisesRegex(ValueError, 'decreased'):
                m.counter_delta(m.read_counters(root/'before'), m.read_counters(root/'after'))

    def test_full_report_and_no_overwrite(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp); fixture(root)
            report = m.build_report(root)
            self.assertEqual(len(report['metricTrials']), 10)
            self.assertEqual(len(report['metricSummary']), 6)
            self.assertEqual(len(report['timingSummary']), 9)
            self.assertEqual(report['evidenceCounts']['warmRunsNoDisplayedUfsIncrease'], 5)
            self.assertFalse(report['warnings'])
            out = root/'output'; m.save_report(report, out)
            self.assertEqual(len(list(out.iterdir())), 5)
            self.assertIn('không kết luận cache hit chính xác 100%', (out/'report.md').read_text(encoding='utf-8'))
            with self.assertRaisesRegex(ValueError, 'Output exists'):
                m.save_report(report, out)

    def test_missing_repeat_rejected(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp); fixture(root)
            (root/'als-H0-5.json').unlink()
            with self.assertRaisesRegex(ValueError, 'trial set differs'):
                m.build_report(root)

    def test_missing_snapshot_rejected(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp); fixture(root)
            (root/'H2-4-after-metrics.txt').unlink()
            with self.assertRaises(FileNotFoundError):
                m.build_report(root)

    def test_mixed_dataset_rejected(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp); fixture(root)
            p = root/'als-H2-3.json'; row = json.loads(p.read_text()); row['datasetVersion'] = 'other'
            p.write_text(json.dumps(row))
            with self.assertRaisesRegex(ValueError, 'datasetVersion'):
                m.build_report(root)

    def test_warm_cache_not_full_rejected(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp); fixture(root)
            (root/'H2-2-before-cache.txt').write_text('1024 512 (50%) /datasets/fixture/split/split=train\n')
            with self.assertRaisesRegex(ValueError, 'all train bytes cached'):
                m.build_report(root)

    def test_changed_als_params_rejected(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp); fixture(root)
            p = root/'als-H1-5.json'; row = json.loads(p.read_text()); row['alsParams']['rank'] = 30
            p.write_text(json.dumps(row))
            with self.assertRaisesRegex(ValueError, 'alsParams'):
                m.build_report(root)


if __name__ == '__main__':
    unittest.main()
