import importlib.util
import json
import tempfile
import unittest
from pathlib import Path

path = Path(__file__).resolve().parents[1] / 'scripts/summarize_als.py'
spec = importlib.util.spec_from_file_location('summary_als', path)
module = importlib.util.module_from_spec(spec); spec.loader.exec_module(module)


class SummaryTests(unittest.TestCase):
    def write(self, root, label, repeat=1, **changes):
        row = {'task': 'als', 'label': label, 'repeat': repeat, 'datasetVersion': 'fixture-v1',
            'check': {'rows': 12}, 'alsParams': {'rank': 20, 'numUserBlocks': 16},
            'environment': {'master': 'local[2]'}, 'factorCounts': {'users': 4, 'movies': 3},
            'timingScope': 'fixture', 'readMaterializeSeconds': 10.0,
            'fitMaterializeSeconds': 20.0, 'readPlusFitSeconds': 30.0}
        row.update(changes)
        (root / ('als-%s-%s.json' % (label, repeat))).write_text(json.dumps(row))

    def test_phase_specific_speedup_and_ignore_scan(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            for r in [1, 2]:
                self.write(root, 'H0', r)
                self.write(root, 'H1', r)
                self.write(root, 'H2', r, readMaterializeSeconds=5.0,
                           fitMaterializeSeconds=20.0, readPlusFitSeconds=25.0)
            (root / 'scan-H0-1.json').write_text('{"task":"scan"}')
            result = module.summarize(root)
            self.assertEqual(len(result['rows']), 9)
            warm = {r['phase']: r for r in result['rows'] if r['label'] == 'H2'}
            self.assertEqual(warm['readMaterializeSeconds']['speedupVsDirect'], 2.0)
            self.assertEqual(warm['fitMaterializeSeconds']['speedupVsDirect'], 1.0)
            self.assertEqual(warm['readPlusFitSeconds']['speedupVsDirect'], 1.2)
            self.assertTrue((root / 'summary-als.csv').exists())

    def test_reject_different_als_config(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            self.write(root, 'H0'); self.write(root, 'H1')
            self.write(root, 'H2', alsParams={'rank': 20, 'numUserBlocks': 2})
            with self.assertRaisesRegex(ValueError, 'alsParams'):
                module.summarize(root)

    def test_reject_different_data(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            self.write(root, 'H0'); self.write(root, 'H1'); self.write(root, 'H2', check={'rows': 13})
            with self.assertRaisesRegex(ValueError, 'check'):
                module.summarize(root)

    def test_reject_missing_repeat(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            for label in ['H0', 'H1', 'H2']: self.write(root, label)
            self.write(root, 'H0', 2)
            with self.assertRaisesRegex(ValueError, 'Unbalanced'):
                module.summarize(root)


if __name__ == '__main__':
    unittest.main()
