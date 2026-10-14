"""Run only while web AND API are stopped; MongoDB remains running."""
import os,json,time,argparse
from pathlib import Path
from pymongo import MongoClient
def main():
    p=argparse.ArgumentParser(); p.add_argument('--out',default='/runtime/app-snapshot.jsonl'); a=p.parse_args()
    meta=json.loads(Path(os.environ.get('MODEL_DIR','/models/current'),'manifest.json').read_text())
    client=MongoClient(os.environ.get('MONGO_URI','mongodb://mongo:27017')); db=client.movies
    mapping_path=Path('/runtime/user-mapping.json'); mapping=json.loads(mapping_path.read_text()) if mapping_path.exists() else {}
    floor=max(int(meta.get('maxSourceUserId',0)),max(mapping.values(),default=0))
    users=list(db.users.find().sort('_id',1)); cutoff=time.time()
    out=Path(a.out)
    if out.exists(): raise ValueError('Snapshot already exists: archive or choose a new name')
    with out.open('w') as f:
        for u in users:
            uid=u['_id']
            if uid not in mapping:
                floor+=1; mapping[uid]=floor
            if mapping[uid]>=2147483647: raise ValueError('ALS INT ID exhausted')
            h={int(k):(float(v),0) for k,v in u.get('seedHistory',{}).items()}
            for r in db.ratings.find({'appUserId':uid}):
                if r.get('deleted'): h.pop(r['movieId'],None)
                else: h[r['movieId']]=(r['rating'],int(r['timestamp']))
            for mid,(rating,stamp) in sorted(h.items()):
                f.write(json.dumps({'userId':mapping[uid],'movieId':mid,'rating':rating,'timestamp':stamp})+'\n')
    temp=mapping_path.with_suffix('.tmp'); temp.write_text(json.dumps(mapping,indent=2)); temp.replace(mapping_path)
    out.with_suffix('.meta.json').write_text(json.dumps({'cutoffUTCUnix':cutoff,'appUsers':len(users),'rowsSource':'full canonical history per app user; immutable while app stopped','mappingFile':'runtime/user-mapping.json','sourceModel':meta['modelVersion']},indent=2))
    client.close(); print(out)
if __name__=='__main__': main()
