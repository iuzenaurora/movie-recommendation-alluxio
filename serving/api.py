"""Local demonstration API. Profile identities are created by Laravel sessions."""
import os,json,hashlib,uuid,time,logging,secrets,csv
from pathlib import Path
from contextlib import asynccontextmanager
import numpy as np
from fastapi import FastAPI,HTTPException,Query
from pydantic import BaseModel,Field
from pymongo import MongoClient,ASCENDING
from recommend_core import fold_in,rank_movies,rank_hybrid,history_hash
MODEL=Path(os.environ.get('MODEL_DIR','models/current')); RESULTS=Path(os.environ.get('RESULT_DIR','experiments/results'))
state={}
log=logging.getLogger('uvicorn.error')
@asynccontextmanager
async def lifespan(app):
    token_path=os.environ.get('SERVICE_TOKEN_FILE')
    if token_path:
        token_file=Path(token_path)
        token_file.parent.mkdir(parents=True,exist_ok=True)
        try:
            with token_file.open('x') as output: output.write(secrets.token_hex(32))
        except FileExistsError: pass
        state['service_token']=token_file.read_text().strip()
        if len(state['service_token'])<32:raise RuntimeError('Invalid service token file')
    meta=json.loads((MODEL/'manifest.json').read_text())
    for name,digest in meta['checksums'].items():
        if Path(name).name!=name or hashlib.sha256((MODEL/name).read_bytes()).hexdigest()!=digest: raise RuntimeError('Model checksum mismatch: '+name)
    f=np.load(MODEL/'factors.npz',allow_pickle=False)
    for prefix in ('movie','user'):
        ids,q=f[prefix+'_ids'],f[prefix+'_factors']
        if ids.ndim!=1 or q.ndim!=2 or len(ids)!=len(q) or len(set(ids.tolist()))!=len(ids) or not np.isfinite(q).all():
            raise RuntimeError('Invalid '+prefix+' factors')
    if f['movie_factors'].shape[1]!=f['user_factors'].shape[1]:
        raise RuntimeError('Factor rank mismatch')
    log.info('Loaded model %s',meta['modelVersion'])
    state.update(meta=meta,ids=f['movie_ids'],q=f['movie_factors'],profiles=json.loads((MODEL/'profiles.json').read_text()),pop=json.loads((MODEL/'popularity.json').read_text()))
    state['movies']={int(x['movieId']):x for x in json.loads((MODEL/'movies.json').read_text())}
    factor_ids={int(mid) for mid in state['ids']}
    state['factor_ids']=factor_ids
    for mid,movie in state['movies'].items():
        movie['hasFactor']=mid in factor_ids
    links=Path(os.environ.get('LINKS_FILE','data/raw/ml-32m/links.csv'))
    if links.is_file():
        with links.open(encoding='utf-8') as source:
            for row in csv.DictReader(source):
                mid=int(row['movieId'])
                if mid in state['movies'] and row.get('imdbId','').isdigit():
                    imdb='tt'+row['imdbId'].zfill(7)
                    state['movies'][mid].update(imdbId=imdb,poster='https://images.metahub.space/poster/medium/'+imdb+'/img')
    for row in state['pop']:
        mid=int(row['movieId'])
        if mid in state['movies']:
            state['movies'][mid].update(avgRating=float(row['mean']),ratingCount=int(row['count']))
    policy=meta.get('candidatePolicy',{})
    candidate_file=MODEL/'candidate_movie_ids.json'
    if policy and ('candidate_movie_ids.json' not in meta['checksums'] or not candidate_file.exists()):
        raise RuntimeError('Candidate policy file missing or not checksummed')
    state['candidate_ids']=set(json.loads(candidate_file.read_text())) if policy else None
    if state['candidate_ids'] is not None and not state['candidate_ids'].issubset({int(mid) for mid in state['ids']}):
        raise RuntimeError('Candidate ids do not match model factors')
    client=MongoClient(os.environ.get('MONGO_URI','mongodb://localhost:27017'),serverSelectionTimeoutMS=10000)
    db=client.movies; db.command('ping'); db.ratings.create_index([('appUserId',ASCENDING),('movieId',ASCENDING)],unique=True)
    db.movies.create_index([('movieId',ASCENDING),('datasetVersion',ASCENDING)])
    # Separate catalog snapshots by dataset version; never delete user ratings on model activation.
    docs=[{**x,'datasetVersion':meta['datasetVersion']} for x in state['movies'].values()]
    for offset in range(0,len(docs),1000):
        from pymongo import UpdateOne
        db.movies.bulk_write([UpdateOne({'movieId':x['movieId'],'datasetVersion':x['datasetVersion']},{'$set':x},upsert=True) for x in docs[offset:offset+1000]])
        log.info('Catalog synced: %d/%d',min(offset+1000,len(docs)),len(docs))
    state['db']=db; state['client']=client
    yield
    client.close(); f.close()
app=FastAPI(title='Phat Movie Recommendation',lifespan=lifespan)
@app.middleware('http')
async def service_guard(request,call_next):
    token=state.get('service_token')
    if token and request.url.path!='/health' and not secrets.compare_digest(request.headers.get('X-Service-Token',''),token):
        from fastapi.responses import JSONResponse
        return JSONResponse({'detail':'Service authentication required'},status_code=401)
    return await call_next(request)
def user(uid):
    u=state['db'].users.find_one({'_id':uid})
    if not u: raise HTTPException(404,'Profile not found')
    return u
def history(uid):
    u=user(uid); seed=u.get('seedHistory',{})
    result={int(k):float(v) for k,v in seed.items()}
    for r in state['db'].ratings.find({'appUserId':uid}):
        if r.get('deleted'): result.pop(r['movieId'],None)
        else: result[r['movieId']]=r['rating']
    return result
@app.get('/health')
def health():
    state['db'].command('ping')
    return {'ok':True,'modelVersion':state['meta']['modelVersion']}
@app.get('/profiles')
def profiles(): return {'profiles':[int(x) for x in state['profiles']]}
class Profile(BaseModel):
    sourceProfileId:int|None=None
    appUserId:uuid.UUID|None=None
@app.post('/users')
def create_user(body:Profile):
    if body.sourceProfileId is not None and str(body.sourceProfileId) not in state['profiles']: raise HTTPException(422,'Unknown source profile')
    uid=str(body.appUserId or uuid.uuid4())
    state['db'].users.update_one({'_id':uid},{'$setOnInsert':{'sourceProfileId':body.sourceProfileId,'seedHistory':state['profiles'].get(str(body.sourceProfileId),{}),'createdAt':time.time()}},upsert=True)
    return {'appUserId':uid}
@app.get('/movies')
def movies(q:str='',genre:str='',page:int=Query(1,ge=1)):
    values=[x for x in state['movies'].values() if q.casefold() in x['title'].casefold() and (not genre or genre in (x.get('genres') or '').split('|'))]
    values.sort(key=lambda x:x['movieId']); start=(page-1)*24
    return {'items':values[start:start+24],'total':len(values),'page':page}
@app.get('/movies/{movie_id}')
def movie_detail(movie_id:int):
    if movie_id not in state['movies']: raise HTTPException(404,'Movie not found')
    return state['movies'][movie_id]
class Rating(BaseModel):
    movieId:int=Field(gt=0)
    rating:float=Field(ge=.5,le=5)
@app.put('/users/{uid}/ratings')
def rate(uid:str,body:Rating):
    user(uid)
    if body.movieId not in state['movies']: raise HTTPException(422,'Unknown movie')
    if body.rating*2!=int(body.rating*2): raise HTTPException(422,'Use half-star increments')
    state['db'].ratings.update_one({'appUserId':uid,'movieId':body.movieId},{'$set':{'rating':body.rating,'timestamp':time.time(),'deleted':False}},upsert=True)
    state['db'].operations.update_one({'_id':'ratings'},{'$inc':{'revision':1}},upsert=True)
    return {'ok':True,'historyVersion':history_hash(history(uid))}
@app.delete('/users/{uid}/ratings/{movie_id}')
def delete_rating(uid:str,movie_id:int):
    user(uid)
    if movie_id not in state['movies']: raise HTTPException(422,'Unknown movie')
    # A tombstone also hides a seeded rating without modifying the immutable seed.
    state['db'].ratings.update_one({'appUserId':uid,'movieId':movie_id},
        {'$set':{'deleted':True,'timestamp':time.time()},'$unset':{'rating':''}},upsert=True)
    state['db'].operations.update_one({'_id':'ratings'},{'$inc':{'revision':1}},upsert=True)
    return {'ok':True,'historyVersion':history_hash(history(uid))}
@app.get('/users/{uid}/history')
def get_history(uid:str):
    h=history(uid)
    return {'items':[dict(state['movies'].get(mid,{'movieId':mid,'title':'Unknown'}),rating=r) for mid,r in sorted(h.items())],'historyVersion':history_hash(h)}
@app.get('/users/{uid}/recommendations')
def recommendations(uid:str,k:int=Query(10,ge=1,le=50),genre:str='',method:str=Query('als',pattern='^(als|hybrid|popularity)$')):
    start=time.perf_counter(); h=history(uid); v=fold_in(h,state['ids'],state['q'],state['meta']['regParam'])
    allowed={mid for mid,m in state['movies'].items() if not genre or genre in (m.get('genres') or '').split('|')}
    if state.get('candidate_ids') is not None: allowed &= state['candidate_ids']
    ranked=(rank_hybrid(v,state['ids'],state['q'],h,state['movies'],k,allowed)
            if method=='hybrid' else rank_movies(v,state['ids'],state['q'],h,k,allowed))
    fallback=v is None
    popularity=fallback or method=='popularity'
    if popularity: ranked=[(int(x['movieId']),float(x['mean'])) for x in state['pop'] if int(x['movieId']) not in h and int(x['movieId']) in allowed][:k]
    name='popularity' if popularity else ('ALS fold-in + genre reranking' if method=='hybrid' else 'ALS fold-in')
    factor_ids=state.get('factor_ids')
    if factor_ids is None:factor_ids={int(mid) for mid in state['ids']}
    supported=sum(mid in factor_ids for mid in h)
    return {'items':[dict(state['movies'][mid],score=score) for mid,score in ranked if mid in state['movies']],'fallback':fallback,'method':name,'historySize':len(h),'supportedRatings':supported,'modelVersion':state['meta']['modelVersion'],'datasetVersion':state['meta']['datasetVersion'],'historyVersion':history_hash(h),'milliseconds':(time.perf_counter()-start)*1000}
@app.get('/dashboard')
def dashboard():
    path=RESULTS/'summary.json'
    return {'model':state['meta'],'experiments':json.loads(path.read_text()) if path.exists() else [],'notice':'No experiment results means benchmarks have not been run.'}

@app.get('/internal/activity')
def activity():
    row=state['db'].operations.find_one({'_id':'ratings'}) or {}
    return {'revision':row.get('revision',0),'modelVersion':state['meta']['modelVersion']}
