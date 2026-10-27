import hashlib
import importlib.util
import json
import sys
import tempfile
import unittest
from pathlib import Path
from types import SimpleNamespace

import numpy as np

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'src'))
from evaluation_protocol import (MODES, favorite_genre, load_package, recommend_all,
                                 select_users, summarize_rows, paired_ndcg_intervals)


class EvaluationProtocolTest(unittest.TestCase):
    def test_genre_matches_positive_only_web_policy_and_stable_ties(self):
        movies = {1: {'genres': 'Comedy'}, 2: {'genres': 'Action'}, 3: {'genres': 'Horror'}}
        self.assertEqual(favorite_genre({3: .5, 2: 5, 1: 5}, movies), 'Comedy')
        self.assertEqual(favorite_genre({1: 3, 2: 1}, movies), '')

    def test_all_modes_share_seen_and_candidate_exclusions(self):
        ids = np.array([1, 2, 3, 4])
        factors = np.array([[1.], [1.1], [100.], [1.]])
        movies = {1: {'genres': 'Comedy'}, 2: {'genres': 'Drama'},
                  3: {'genres': 'Comedy'}, 4: {'genres': 'Comedy'}}
        ranked, _, genre = recommend_all({1: 5}, ids, factors, movies, {1, 2, 4},
                                         [(3, 5.), (1, 5.), (2, 4.), (4, 3.)], .1, 10)
        for mode in MODES:
            self.assertFalse({1, 3} & {mid for mid, _ in ranked[mode]})
        self.assertEqual(genre, 'Comedy')
        self.assertEqual([mid for mid, _ in ranked['genre']], [4])

    def test_empty_lists_count_as_zero_not_excluded_users(self):
        rows = [{'mode': mode, 'userId': 1, 'metrics': {'precision': 0., 'recall': 0., 'ndcg': 0.},
                 'recommended': [], 'fallback': False} for mode in MODES]
        summary = summarize_rows(rows, 10)
        self.assertEqual(summary['genre']['rankingUsers'], 1)
        self.assertEqual(summary['genre']['ndcg'], 0)
        self.assertEqual(paired_ndcg_intervals(rows)['hybrid']['upper95'], 0)

    def test_sampling_is_deterministic_and_independent_of_input_order(self):
        self.assertEqual(select_users([4, 3, 2, 1], 2, 42), select_users([1, 2, 3, 4], 2, 42))

    def test_refit_packages_cannot_be_reported_as_held_out(self):
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp)
            (path / 'manifest.json').write_text(json.dumps({'servingRefit': True}))
            with self.assertRaisesRegex(ValueError, 'train-only'):
                load_package(path)


try:
    import pyarrow as pa
    import pyarrow.parquet as pq
except ImportError:
    pa = None


@unittest.skipIf(pa is None, 'Run in the evaluator container with PyArrow')
class HeldOutIntegrationTest(unittest.TestCase):
    def test_changing_test_ratings_changes_metrics_but_never_recommendations(self):
        spec = importlib.util.spec_from_file_location('evaluate_recommendations', ROOT / 'scripts/evaluate_recommendations.py')
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            data, model = root / 'data', root / 'model'
            model.mkdir()
            np.savez(model / 'factors.npz', movie_ids=np.array([1, 2, 3]), movie_factors=np.array([[1.], [.8], [.7]]))
            (model / 'movies.json').write_text(json.dumps([{'movieId': mid, 'genres': 'Comedy'} for mid in [1, 2, 3]]))
            (model / 'candidate_movie_ids.json').write_text('[1,2,3]')
            checks = {p.name: hashlib.sha256(p.read_bytes()).hexdigest() for p in model.iterdir()}
            (model / 'manifest.json').write_text(json.dumps({'datasetVersion': 'test-only', 'modelVersion': 'synthetic',
                 'regParam': .1, 'servingRefit': False, 'candidatePolicy': {'minTrainRatings': 1}, 'checksums': checks}))
            train = [{'userId': 1, 'movieId': 1, 'rating': 5., 'timestamp': 1},
                     {'userId': 2, 'movieId': 2, 'rating': 5., 'timestamp': 1},
                     {'userId': 2, 'movieId': 3, 'rating': 5., 'timestamp': 2}]
            val = [{'userId': 1, 'movieId': 1, 'rating': 4., 'timestamp': 2}]
            test = [{'userId': 1, 'movieId': 2, 'rating': 5., 'timestamp': 3}]
            for split, rows in [('train', train), ('validation', val), ('test', test)]:
                folder = data / 'split' / ('split=' + split)
                folder.mkdir(parents=True)
                pq.write_table(pa.Table.from_pylist(rows), folder / 'part.parquet')
            def manifest():
                checks = {p.relative_to(data).as_posix(): hashlib.sha256(p.read_bytes()).hexdigest() for p in data.rglob('*.parquet')}
                (data / 'manifest.json').write_text(json.dumps({'datasetVersion': 'test-only', 'fileChecksums': checks}))
            outputs = []
            for index, rating in enumerate([5., 1.]):
                test[0]['rating'] = rating
                pq.write_table(pa.Table.from_pylist(test), data / 'split/split=test/part.parquet')
                manifest()
                out = root / ('run' + str(index))
                # Ensure one relevant item remains so report rendering also exercises averages.
                if rating == 1.:
                    test.append({'userId': 1, 'movieId': 3, 'rating': 5., 'timestamp': 4})
                    pq.write_table(pa.Table.from_pylist(test), data / 'split/split=test/part.parquet')
                    manifest()
                module.evaluate(SimpleNamespace(out=str(out), model=str(model), data=str(data), users=1,
                                 seed=42, history_sizes=[0], api_history='full', k=10))
                outputs.append([json.loads(line) for line in (out / 'per-user.jsonl').read_text().splitlines()])
            self.assertEqual([r['recommended'] for r in outputs[0]], [r['recommended'] for r in outputs[1]])
            self.assertNotEqual(outputs[0][0]['metrics'], outputs[1][0]['metrics'])


if __name__ == '__main__':
    unittest.main()
