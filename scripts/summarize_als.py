"""Summarize only matching successful ALS trials; never mix scan/group results."""
import argparse
import csv
import json
import math
import statistics
from pathlib import Path


def summarize(root):
    trials = [json.loads(p.read_text(encoding='utf-8')) for p in sorted(Path(root).glob('als-*.json'))]
    if not trials:
        raise ValueError('No successful ALS benchmark results')
    first = trials[0]
    identity = ['datasetVersion', 'check', 'alsParams', 'environment', 'factorCounts', 'timingScope']
    seen, groups = set(), {}
    for row in trials:
        if row.get('task') != 'als' or row['label'] not in ['H0', 'H1', 'H2', 'L0', 'L1', 'L2']:
            raise ValueError('Unexpected task or label')
        for key in identity:
            if row[key] != first[key]:
                raise ValueError('Cannot compare different ' + key)
        pair = row['label'], row['repeat']
        if pair in seen:
            raise ValueError('Duplicate label/repeat')
        seen.add(pair)
        for key in ['readMaterializeSeconds', 'fitMaterializeSeconds', 'readPlusFitSeconds']:
            if not math.isfinite(row[key]) or row[key] <= 0:
                raise ValueError('Invalid timing')
        groups.setdefault(row['label'], []).append(row)
    rows = []
    for backend in ['H', 'L']:
        labels = [backend + str(i) for i in range(3)]
        if not any(label in groups for label in labels):
            continue
        if any(label not in groups for label in labels):
            raise ValueError('Missing direct/cold/warm trial for ' + backend)
        repeats = [{r['repeat'] for r in groups[label]} for label in labels]
        if not all(r == repeats[0] for r in repeats):
            raise ValueError('Unbalanced repeats for ' + backend)
        for key in ['readMaterializeSeconds', 'fitMaterializeSeconds', 'readPlusFitSeconds']:
            direct = statistics.median(r[key] for r in groups[backend + '0'])
            for label in labels:
                values = [r[key] for r in groups[label]]
                median = statistics.median(values)
                q = statistics.quantiles(values, n=4, method='inclusive') if len(values) > 1 else [median] * 3
                rows.append({'phase': key, 'label': label, 'runs': len(values),
                             'medianSeconds': median, 'iqrSeconds': q[2] - q[0],
                             'speedupVsDirect': direct / median})
    report = {'datasetVersion': first['datasetVersion'], 'alsParams': first['alsParams'],
              'environment': first['environment'], 'check': first['check'], 'rows': rows,
              'note': 'One repeat is a single observation; do not infer variability or stable speedups.'}
    root = Path(root)
    (root / 'summary-als.json').write_text(json.dumps(report, indent=2), encoding='utf-8')
    with (root / 'summary-als.csv').open('w', newline='', encoding='utf-8') as f:
        writer = csv.DictWriter(f, fieldnames=list(rows[0]))
        writer.writeheader(); writer.writerows(rows)
    return report


if __name__ == '__main__':
    p = argparse.ArgumentParser(); p.add_argument('--results', required=True)
    print(json.dumps(summarize(p.parse_args().results), indent=2))
