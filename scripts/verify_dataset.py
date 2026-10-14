import sys,json,argparse
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'src'))
from file_checksums import parquet_checksums
if __name__=='__main__':
    p=argparse.ArgumentParser(); p.add_argument('--data',required=True); a=p.parse_args()
    expected=json.loads(Path(a.data,'manifest.json').read_text())['fileChecksums']
    actual=parquet_checksums(a.data)
    if not expected or actual!=expected: raise ValueError('Parquet checksums differ from manifest')
    print('Dataset checksums verified:',len(actual),'files')
