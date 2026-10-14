import json,csv,statistics,argparse
from pathlib import Path
def main():
    p=argparse.ArgumentParser(); p.add_argument('--results',default='experiments/results'); p.add_argument('--all-labels',action='store_true'); a=p.parse_args(); root=Path(a.results)
    groups={}; checks={}; versions=set()
    for path in root.glob('*.json'):
        row=json.loads(path.read_text())
        if not isinstance(row,dict) or not {'label','task','seconds','check'}<=row.keys(): continue
        if not a.all_labels and row['label'] not in {'H0','H1','H2','L0','L1','L2'}: continue
        versions.add(row.get('datasetVersion','unspecified'))
        key=(row['task'],row['label']); groups.setdefault(key,[]).append(row['seconds']); checks.setdefault(row['task'],[]).append(row['check'])
    if len(versions)>1: raise ValueError('Multiple dataset versions: isolate experiment folders')
    for task,values in checks.items():
        if any(v!=values[0] for v in values): raise ValueError(f'Correctness differs for {task}; no valid speedup comparison')
    rows=[]
    for (task,label),values in sorted(groups.items()):
        q=statistics.quantiles(values,n=4,method='inclusive') if len(values)>1 else [values[0]]*3
        direct=groups.get((task,'H0' if label.startswith('H') else 'L0'))
        rows.append({'task':task,'label':label,'runs':len(values),'medianSeconds':statistics.median(values),'iqrSeconds':q[2]-q[0],'speedupVsDirect':statistics.median(direct)/statistics.median(values) if direct else None})
    if not rows: raise ValueError('No benchmark result files')
    with (root/'summary.csv').open('w',newline='') as f:
        w=csv.DictWriter(f,fieldnames=list(rows[0])); w.writeheader(); w.writerows(rows)
    (root/'summary.json').write_text(json.dumps(rows,indent=2)); print(json.dumps(rows,indent=2))
if __name__=='__main__': main()
