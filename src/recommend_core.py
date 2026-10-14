"""Shared fold-in and ranking. Pure NumPy, used by API and tests."""
import hashlib,json
import numpy as np
def history_hash(history):
    canonical=sorted((int(k),float(v)) for k,v in history.items())
    return hashlib.sha256(json.dumps(canonical,separators=(',',':')).encode()).hexdigest()[:16]
def fold_in(history, movie_ids, factors, reg):
    mapping={int(mid):i for i,mid in enumerate(movie_ids)}
    known=[(mapping[int(mid)],float(r)) for mid,r in history.items() if int(mid) in mapping]
    if not known: return None
    idx,ratings=zip(*known); q=np.asarray(factors[list(idx)],dtype=np.float64)
    # Spark ALS-WR scales regularization by the number of observed ratings.
    return np.linalg.solve(q.T@q + float(reg)*len(known)*np.eye(q.shape[1]),q.T@np.asarray(ratings))
def rank_movies(vector,movie_ids,factors,seen,k=10,allowed=None):
    if vector is None: return []
    scores=np.asarray(factors,dtype=np.float64)@np.asarray(vector,dtype=np.float64)
    mask=np.isfinite(scores)&np.array([int(mid) not in seen and (allowed is None or int(mid) in allowed) for mid in movie_ids])
    ix=np.flatnonzero(mask)
    order=np.lexsort((np.asarray(movie_ids)[ix],-scores[ix]))[:k]
    return [(int(movie_ids[i]),float(scores[i])) for i in ix[order]]

def genre_preferences(history,movies):
    """Signed genre profile: 3 stars neutral, shared credit for multi-genre films."""
    weights={}
    for mid,rating in history.items():
        genres=movies.get(int(mid),{}).get('genres','')
        genres=genres if isinstance(genres,list) else genres.split('|')
        genres=[g for g in genres if g and g!='(no genres listed)']
        for genre in genres:
            weights[genre]=weights.get(genre,0.)+(float(rating)-3.)/np.sqrt(len(genres))
    norm=float(np.linalg.norm(list(weights.values())))
    return {g:w/norm for g,w in weights.items()} if norm else {}

def rank_hybrid(vector,movie_ids,factors,history,movies,k=10,allowed=None,genre_weight=.75):
    """Explicit serving reranker; raw ALS training/evaluation stays unchanged."""
    ranked=rank_movies(vector,movie_ids,factors,history,len(movie_ids),allowed)
    preferences=genre_preferences(history,movies)
    result=[]
    for mid,als_score in ranked:
        genres=movies.get(mid,{}).get('genres','')
        genres=genres if isinstance(genres,list) else genres.split('|')
        genres=[g for g in genres if g and g!='(no genres listed)']
        affinity=sum(preferences.get(g,0.) for g in genres)/np.sqrt(len(genres)) if genres else 0.
        result.append((mid,float(als_score+genre_weight*affinity)))
    return sorted(result,key=lambda row:(-row[1],row[0]))[:k]
def ranking_metrics(recommended,relevant,k=10):
    if not relevant: return None
    hits=[int(mid in relevant) for mid in recommended[:k]]
    dcg=sum(h/np.log2(i+2) for i,h in enumerate(hits))
    ideal=sum(1/np.log2(i+2) for i in range(min(k,len(relevant))))
    return {'precision':sum(hits)/k,'recall':sum(hits)/len(relevant),'ndcg':float(dcg/ideal)}
