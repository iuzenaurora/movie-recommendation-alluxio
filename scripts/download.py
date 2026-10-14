"""Download official MovieLens; validate ZIP entries, keep the raw archive."""
import argparse, urllib.request, zipfile, hashlib, json
from pathlib import Path
def main():
    p=argparse.ArgumentParser(); p.add_argument('--dataset', choices=['small','32m'],default='small'); p.add_argument('--out',default='data/raw'); a=p.parse_args()
    name='ml-latest-small' if a.dataset=='small' else 'ml-32m'
    root=Path(a.out); root.mkdir(parents=True,exist_ok=True)
    z=root/(name+'.zip'); url=f'https://files.grouplens.org/datasets/movielens/{name}.zip'
    if not z.exists():
        print('Downloading:',url,flush=True)
        temp=z.with_suffix('.part'); urllib.request.urlretrieve(url,temp); temp.replace(z)
    with zipfile.ZipFile(z) as f:
        if f.testzip(): raise ValueError('ZIP corrupted')
        for n in f.namelist():
            if not (root/n).resolve().is_relative_to(root.resolve()): raise ValueError('Unsafe ZIP path')
        f.extractall(root)
    h=hashlib.sha256()
    with z.open('rb') as f:
        for b in iter(lambda:f.read(1024*1024),b''): h.update(b)
    (root/(name+'-source.json')).write_text(json.dumps({'source':url,'archiveSHA256':h.hexdigest()},indent=2))
    print(root/name)
if __name__=='__main__': main()
