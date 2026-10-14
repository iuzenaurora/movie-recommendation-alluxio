import hashlib
from pathlib import Path
def parquet_checksums(root):
    root=Path(root); result={}
    for p in sorted(root.rglob('*.parquet')):
        h=hashlib.sha256()
        with p.open('rb') as f:
            for chunk in iter(lambda:f.read(1024*1024),b''): h.update(chunk)
        result[p.relative_to(root).as_posix()]=h.hexdigest()
    return result
