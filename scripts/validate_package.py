import argparse,json,hashlib
from pathlib import Path
import numpy as np
def validate(root):
    root=Path(root); meta=json.loads((root/'manifest.json').read_text())
    required={'factors.npz','movies.json','profiles.json','popularity.json'}
    if meta.get('candidatePolicy'): required.add('candidate_movie_ids.json')
    if not required.issubset(meta.get('checksums',{})): raise ValueError('Required checksum missing')
    for name,digest in meta['checksums'].items():
        if Path(name).name!=name: raise ValueError('Unsafe file name')
        if hashlib.sha256((root/name).read_bytes()).hexdigest()!=digest: raise ValueError('Checksum mismatch: '+name)
    with np.load(root/'factors.npz',allow_pickle=False) as f:
        for prefix in ['movie','user']:
            ids=f[prefix+'_ids']; q=f[prefix+'_factors']
            if q.ndim!=2 or len(ids)!=len(q) or len(set(ids.tolist()))!=len(ids) or not np.isfinite(q).all(): raise ValueError('Invalid '+prefix+' factors')
        if f['movie_factors'].shape[1]!=f['user_factors'].shape[1]: raise ValueError('Rank mismatch')
        movies={int(row['movieId']) for row in json.loads((root/'movies.json').read_text())}
        factor_ids={int(mid) for mid in f['movie_ids']}
        if not factor_ids.issubset(movies): raise ValueError('Factor metadata missing')
        if meta.get('candidatePolicy'):
            candidates=json.loads((root/'candidate_movie_ids.json').read_text())
            if len(candidates)!=len(set(candidates)) or not set(candidates).issubset(factor_ids): raise ValueError('Invalid candidate ids')
    print('Valid model package:',meta['modelVersion'])
if __name__=='__main__':
    p=argparse.ArgumentParser(); p.add_argument('--model',required=True); validate(p.parse_args().model)
