import unittest,sys
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'src'))
import numpy as np
from recommend_core import fold_in,rank_movies,rank_hybrid,genre_preferences,history_hash,ranking_metrics
class RecommendationTest(unittest.TestCase):
    def setUp(self): self.ids=np.array([10,20,30,40]); self.q=np.array([[1.,0.],[0.,1.],[1.,1.],[1.,1.]])
    def test_fold_objective_stationary(self):
        h={10:4.,20:2.}; v=fold_in(h,self.ids,self.q,.1)
        residual=self.q[:2].T@(self.q[:2]@v-np.array([4.,2.]))+.1*2*v
        np.testing.assert_allclose(residual,np.zeros(2),atol=1e-12)
    def test_seen_removed_ties_stable(self):
        r=rank_movies(np.array([1.,1.]),self.ids,self.q,{10,20},10)
        self.assertEqual([x[0] for x in r],[30,40])
    def test_rating_edit_replaces_old(self):
        h={10:1.}; a=fold_in(h,self.ids,self.q,.1); h[10]=5.; b=fold_in(h,self.ids,self.q,.1)
        self.assertGreater(b[0],a[0]); self.assertEqual(len(h),1)
    def test_unknown_and_empty_fallback(self):
        self.assertIsNone(fold_in({},self.ids,self.q,.1)); self.assertIsNone(fold_in({999:5},self.ids,self.q,.1))
    def test_history_canonical_version(self):
        self.assertEqual(history_hash({10:1,20:2}),history_hash({20:2,10:1})); self.assertNotEqual(history_hash({10:1}),history_hash({10:2}))
    def test_genre_candidate_filter(self):
        self.assertEqual(rank_movies(np.array([1.,1.]),self.ids,self.q,set(),10,{20})[0][0],20)
    def test_metrics(self):
        m=ranking_metrics([10,20],{10,30},2); self.assertEqual(m['precision'],.5); self.assertEqual(m['recall'],.5)
        self.assertIsNone(ranking_metrics([10],set()))
    def test_hybrid_reduces_unrelated_genre_bias_without_blacklisting_documentaries(self):
        ids=np.array([10,20,30]);q=np.array([[1.],[1.1],[1.]])
        movies={10:{'genres':'Animation'},20:{'genres':'Documentary'},30:{'genres':'Animation'}}
        v=np.array([1.])
        self.assertEqual(rank_movies(v,ids,q,{10},2)[0][0],20)
        ranked=rank_hybrid(v,ids,q,{10:5},movies,2)
        self.assertEqual([mid for mid,_ in ranked],[30,20])
        movies[10]['genres']='Documentary'
        self.assertEqual(rank_hybrid(v,ids,q,{10:5},movies,2)[0][0],20)
    def test_hybrid_respects_seen_allowed_ties_and_negative_feedback(self):
        movies={10:{'genres':'Animation'},20:{'genres':'Documentary'},30:{'genres':'Animation'},40:{'genres':'Animation'}}
        ranked=rank_hybrid(np.array([1.,1.]),self.ids,self.q,{10:1},movies,10,{30,40})
        self.assertEqual([mid for mid,_ in ranked],[30,40])
        self.assertLess(genre_preferences({10:1},movies)['Animation'],0)
        self.assertEqual(genre_preferences({10:3},movies),{})
    def test_hybrid_neutral_or_missing_genres_preserves_raw_ranking(self):
        h={10:3}
        self.assertEqual(rank_hybrid(np.array([1.,1.]),self.ids,self.q,h,{},10),
                         rank_movies(np.array([1.,1.]),self.ids,self.q,h,10))
if __name__=='__main__': unittest.main()
