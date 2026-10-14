import unittest,sys
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'src'))
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'serving'))
import numpy as np
import mongomock
from fastapi.testclient import TestClient
import api

class ApiContractTest(unittest.TestCase):
    def setUp(self):
        db=mongomock.MongoClient().movies
        db.ratings.create_index([('appUserId',1),('movieId',1)],unique=True)
        api.state.clear()
        api.state.update(db=db,ids=np.array([10,20,30]),q=np.array([[1.,0.],[0.,1.],[1.,1.]]),profiles={'1':{'10':4.}},pop=[{'movieId':30,'mean':4.5},{'movieId':20,'mean':3.}],movies={i:{'movieId':i,'title':str(i),'genres':'Comedy'} for i in [10,20,30]},meta={'regParam':.1,'modelVersion':'test-only','datasetVersion':'synthetic-test'})
        # No lifespan: the endpoint contract uses a mocked MongoDB, not a real service.
        self.client=TestClient(api.app)
    def tearDown(self): self.client.close()
    def create(self,body):
        response=self.client.post('/users',json=body)
        self.assertEqual(response.status_code,200)
        return response.json()['appUserId']
    def test_new_user_fallback_then_dynamic(self):
        uid=self.create({})
        self.assertTrue(self.client.get(f'/users/{uid}/recommendations').json()['fallback'])
        a=self.client.put(f'/users/{uid}/ratings',json={'movieId':10,'rating':4}).json()
        r=self.client.get(f'/users/{uid}/recommendations').json()
        self.assertFalse(r['fallback']); self.assertNotIn(10,[x['movieId'] for x in r['items']])
        b=self.client.put(f'/users/{uid}/ratings',json={'movieId':10,'rating':1}).json()
        self.assertNotEqual(a['historyVersion'],b['historyVersion'])
        h=self.client.get(f'/users/{uid}/history').json()['items']
        self.assertEqual(len(h),1); self.assertEqual(h[0]['rating'],1)
    def test_seed_persists_across_package_change(self):
        uid=self.create({'sourceProfileId':1}); api.state['profiles']={}
        self.assertEqual(self.client.get(f'/users/{uid}/history').json()['items'][0]['rating'],4)
    def test_invalid_rating_and_missing_profile(self):
        uid=self.create({})
        for payload in [{'movieId':10,'rating':1.2},{'movieId':999,'rating':4},{'movieId':10,'rating':6}]:
            self.assertEqual(self.client.put(f'/users/{uid}/ratings',json=payload).status_code,422)
        self.assertEqual(self.client.get('/users/absent/history').status_code,404)
    def test_unknown_genre_empty(self):
        uid=self.create({'sourceProfileId':1})
        self.assertEqual(self.client.get(f'/users/{uid}/recommendations?genre=Unknown').json()['items'],[])
    def test_detail_looks_up_movie_id_not_title(self):
        self.assertEqual(self.client.get('/movies/20').json()['movieId'],20)
        self.assertEqual(self.client.get('/movies/999').status_code,404)

    def test_requested_popularity_still_excludes_seen(self):
        uid=self.create({'sourceProfileId':1})
        result=self.client.get(f'/users/{uid}/recommendations?method=popularity').json()
        self.assertEqual(result['method'],'popularity')
        self.assertEqual([row['movieId'] for row in result['items']],[30,20])

    def test_hybrid_is_explicit_and_uses_current_rating_history(self):
        uid=self.create({})
        api.state['movies'][10]['genres']='Animation'
        api.state['movies'][20]['genres']='Documentary'
        api.state['movies'][30]['genres']='Animation'
        self.client.put(f'/users/{uid}/ratings',json={'movieId':10,'rating':5})
        result=self.client.get(f'/users/{uid}/recommendations?method=hybrid').json()
        self.assertEqual(result['method'],'ALS fold-in + genre reranking')
        self.assertEqual(result['supportedRatings'],1)
        self.assertNotIn(10,[row['movieId'] for row in result['items']])
        before=result['historyVersion']
        self.client.put(f'/users/{uid}/ratings',json={'movieId':10,'rating':1})
        after=self.client.get(f'/users/{uid}/recommendations?method=hybrid').json()
        self.assertNotEqual(after['historyVersion'],before)
        api.state['candidate_ids']={20}
        self.assertEqual([row['movieId'] for row in self.client.get(f'/users/{uid}/recommendations?method=hybrid').json()['items']],[20])

    def test_account_id_is_idempotent_and_keeps_ratings(self):
        uid=self.create({'appUserId':'10000000-0000-4000-8000-000000000001'})
        self.client.put(f'/users/{uid}/ratings',json={'movieId':10,'rating':4})
        self.assertEqual(uid,self.create({'appUserId':uid}))
        self.assertEqual(self.client.get(f'/users/{uid}/history').json()['items'][0]['rating'],4)
        self.assertEqual(api.state['db'].users.count_documents({}),1)

    def test_internal_routes_require_service_token(self):
        api.state['service_token']='test-secret'
        self.assertEqual(self.client.get('/movies').status_code,401)
        self.assertEqual(self.client.get('/movies',headers={'X-Service-Token':'test-secret'}).status_code,200)

    def test_delete_seed_and_override_then_rate_again(self):
        uid=self.create({'sourceProfileId':1})
        before=self.client.get(f'/users/{uid}/history').json()['historyVersion']
        deleted=self.client.delete(f'/users/{uid}/ratings/10').json()
        self.assertNotEqual(before,deleted['historyVersion'])
        self.assertEqual(self.client.get(f'/users/{uid}/history').json()['items'],[])
        self.assertTrue(self.client.get(f'/users/{uid}/recommendations').json()['fallback'])
        self.client.put(f'/users/{uid}/ratings',json={'movieId':10,'rating':3.5})
        items=self.client.get(f'/users/{uid}/history').json()['items']
        self.assertEqual(items[0]['rating'],3.5)
        self.client.delete(f'/users/{uid}/ratings/10')
        self.assertEqual(self.client.get(f'/users/{uid}/history').json()['items'],[])
        self.assertEqual(self.client.delete('/users/missing/ratings/10').status_code,404)
    def test_candidate_policy_applies_to_fallback_and_als_without_dropping_history(self):
        api.state['candidate_ids']={20}
        uid=self.create({})
        fallback=self.client.get(f'/users/{uid}/recommendations').json()
        self.assertEqual([x['movieId'] for x in fallback['items']],[20])
        self.assertEqual(self.client.put(f'/users/{uid}/ratings',json={'movieId':10,'rating':4}).status_code,200)
        dynamic=self.client.get(f'/users/{uid}/recommendations').json()
        self.assertFalse(dynamic['fallback'])
        self.assertEqual([x['movieId'] for x in dynamic['items']],[20])
        self.assertEqual(self.client.get(f'/users/{uid}/history').json()['items'][0]['movieId'],10)

if __name__=='__main__': unittest.main()
