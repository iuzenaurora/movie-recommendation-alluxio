import sys
import unittest
from pathlib import Path
from types import SimpleNamespace

import numpy as np
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'src'))
try:
    from pyspark.sql import SparkSession
    from train import candidate_ids, choose_trial, evaluate_ranking, ranking_context
except ModuleNotFoundError as exc:
    if exc.name != 'pyspark':
        raise
    SparkSession = None


@unittest.skipIf(SparkSession is None, 'Install pyspark==3.5.6 and Java 17 for Spark protocol tests')
class TrainRankingTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.spark = SparkSession.builder.master('local[2]').appName('RankingProtocolTest') \
            .config('spark.ui.enabled', 'false').config('spark.sql.shuffle.partitions', '2').getOrCreate()
        cls.spark.sparkContext.setLogLevel('ERROR')

    @classmethod
    def tearDownClass(cls):
        cls.spark.stop()

    def test_selection_uses_ndcg_and_validation_rmse_tie_break(self):
        good_rmse = {'rmse': .7, 'rankingAt10': {'ndcg': .01}}
        good_ranking = {'rmse': .9, 'rankingAt10': {'ndcg': .05}}
        self.assertTrue(choose_trial(good_ranking, good_rmse, 'ndcg'))
        self.assertFalse(choose_trial(good_ranking, good_rmse, 'rmse'))
        tied = {'rmse': .8, 'rankingAt10': {'ndcg': .05}}
        self.assertTrue(choose_trial(tied, good_ranking, 'ndcg'))

    def test_threshold_is_inclusive_and_only_known_ids(self):
        self.assertEqual(candidate_ids({10: 99, 20: 100, 30: 1000}, [10, 20], 100), {20})

    def test_validation_never_reads_test_history(self):
        split = self.spark.createDataFrame(
            [(1, 10, 5., 'train'), (1, 20, 4., 'validation'), (1, 30, 5., 'test')],
            'userId INT, movieId INT, rating DOUBLE, split STRING')
        val = ranking_context(split, 'validation', 200, 42)
        test = ranking_context(split, 'test', 200, 42)
        self.assertEqual(val['seen'][1], {10})
        self.assertEqual(val['truth'][1], {20})
        self.assertEqual(test['seen'][1], {10, 20})
        self.assertEqual(test['truth'][1], {30})

    def test_shared_candidates_and_filtered_positives_stay_in_recall(self):
        model = SimpleNamespace(
            itemFactors=self.spark.createDataFrame(
                [(10, [1.]), (20, [2.]), (30, [100.])], 'id INT, features ARRAY<DOUBLE>'),
            userFactors=self.spark.createDataFrame([(1, [1.])], 'id INT, features ARRAY<DOUBLE>'))
        context = {'selected': [1, 2], 'seen': {1: {10}, 2: set()},
                   'truth': {1: {20, 30, 999}, 2: {20}}, 'split': 'validation'}
        result = evaluate_ranking(model, context, {10: 100, 20: 100, 30: 1}, 100)
        self.assertEqual(result['rankingUsersEvaluated'], 1)
        self.assertEqual(result['usersExcludedNoFactor'], 1)
        self.assertEqual(result['candidateMovies'], 2)
        self.assertAlmostEqual(result['rankingAt10']['recall'], 1 / 3)
        self.assertEqual(result['rankingAt10']['precision'], .1)
        self.assertEqual(result['rankingAt10'], result['popularityAt10'])
        self.assertEqual(result['avgALSListLength'], 1.)


if __name__ == '__main__':
    unittest.main()
