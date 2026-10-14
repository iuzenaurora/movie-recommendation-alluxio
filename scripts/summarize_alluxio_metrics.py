"""Summarize completed H0/H1/H2 ALS trials and rounded Alluxio snapshots.

Read-only inputs. No Spark session, training, cache mutation or invented results.
"""
import argparse
import csv
import json
import math
import re
import statistics
from decimal import Decimal
from pathlib import Path

METRICS = ('BytesReadCache', 'BytesReadRemote', 'BytesReadUfsAll')
FIELDS = dict(zip(METRICS, ('cacheReadMiBApprox', 'rpcReadMiBApprox', 'ufsReadMiBApprox')))
FACTORS = {unit: Decimal(1024) ** i for i, unit in enumerate(('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'))}
MIB = FACTORS['MB']
LINE = re.compile(r'^\s*Cluster\.(BytesReadCache|BytesReadRemote|BytesReadUfsAll)\s+\(Type:\s*COUNTER,\s*Value:\s*([0-9]+(?:\.[0-9]+)?)\s*([KMGTPE]?B)\)\s*$', re.M)
CACHE = re.compile(r'^\s*(\d+)\s+(\d+)\s+\([^)]*%\)\s+(.+?)\s*$', re.M)
PHASES = ('readMaterializeSeconds', 'fitMaterializeSeconds', 'readPlusFitSeconds')
IDENTITY = ('datasetVersion', 'check', 'alsParams', 'environment', 'factorCounts', 'timingScope')


def read_json(path):
    return json.loads(Path(path).read_text(encoding='utf-8-sig'))


def read_counters(path):
    text = Path(path).read_text(encoding='utf-8-sig')
    values = {}
    for name, number, unit in LINE.findall(text):
        if name in values:
            raise ValueError(f'Duplicate counter {name} in {path}')
        value = Decimal(number) * FACTORS[unit]
        # Alluxio CLI rounds displayed sizes. Bound only this formatting error,
        # not heartbeat lag, concurrent activity or counter resets.
        quantum = (Decimal(0) if unit == 'B' and '.' not in number else
                   Decimal(1).scaleb(Decimal(number).as_tuple().exponent) * FACTORS[unit])
        values[name] = {'bytesApprox': value, 'halfQuantum': quantum / 2,
                        'display': number + unit}
    missing = set(METRICS) - set(values)
    if missing:
        raise ValueError(f'Missing/unsupported counter(s) in {path}: {sorted(missing)}')
    return values


def counter_delta(before, after):
    result = {}
    for name in METRICS:
        a, b = before[name], after[name]
        delta = b['bytesApprox'] - a['bytesApprox']
        if delta < 0:
            raise ValueError(f'{name} decreased: {a["display"]} -> {b["display"]}. '
                             'Inspect reset/restart, snapshots and rounding; do not clamp to zero.')
        # Bounds assume nearest rounding and monotonic counters. They do not
        # make a no-change display into proof of an exact zero-byte read.
        low = max(Decimal(0), delta - a['halfQuantum'] - b['halfQuantum'])
        high = delta + a['halfQuantum'] + b['halfQuantum']
        result[name] = {'deltaMiBApprox': float(delta / MIB),
                        'roundingLowerMiB': float(low / MIB),
                        'roundingUpperMiB': float(high / MIB),
                        'status': 'noIncreaseAtDisplayedPrecision' if delta == 0 else 'observedIncrease',
                        'beforeDisplay': a['display'], 'afterDisplay': b['display']}
    return result


def read_cache(path):
    matches = CACHE.findall(Path(path).read_text(encoding='utf-8-sig'))
    if len(matches) != 1:
        raise ValueError(f'Expected exactly one raw-byte du summary in {path}')
    total, cached, location = matches[0]
    total, cached = int(total), int(cached)
    if total <= 0 or cached < 0 or cached > total or not location.rstrip('/').endswith('/split/split=train'):
        raise ValueError(f'Invalid/incorrect train cache summary in {path}')
    return {'totalBytes': total, 'cachedBytes': cached, 'percent': 100 * cached / total}


def median_iqr(values):
    median = statistics.median(values)
    q = statistics.quantiles(values, n=4, method='inclusive') if len(values) > 1 else [median] * 3
    return median, q[2] - q[0]


def build_report(root, repeats=5):
    root = Path(root)
    if repeats < 1:
        raise ValueError('repeats must be positive')
    expected = {f'als-{label}-{i}.json' for label in ('H0', 'H1', 'H2') for i in range(1, repeats + 1)}
    actual = {p.name for p in root.glob('als-*.json')}
    if actual != expected:
        raise ValueError(f'ALS trial set differs. Missing={sorted(expected-actual)}; extra={sorted(actual-expected)}')
    trials = {}
    first = None
    for label in ('H0', 'H1', 'H2'):
        for i in range(1, repeats + 1):
            row = read_json(root / f'als-{label}-{i}.json')
            if row.get('task') != 'als' or row.get('label') != label or row.get('repeat') != i:
                raise ValueError(f'Incorrect trial identity in als-{label}-{i}.json')
            if first is None:
                first = row
            for key in IDENTITY:
                if key not in row or row[key] != first[key]:
                    raise ValueError('Cannot compare different/missing ' + key)
            if row.get('expectedTrainRows') != row['check'].get('rows') or row['check']['rows'] <= 0:
                raise ValueError('Invalid train row count')
            if not all(math.isfinite(row[p]) and row[p] > 0 for p in PHASES):
                raise ValueError('Invalid timing')
            if not math.isclose(row[PHASES[2]], row[PHASES[0]] + row[PHASES[1]], rel_tol=1e-9, abs_tol=1e-6):
                raise ValueError('readPlusFitSeconds does not equal read + fit')
            trials[label, i] = row
    manifest = read_json(root / 'dataset-manifest.json')
    if manifest.get('datasetVersion') != first['datasetVersion'] or manifest['splitCounts']['train'] != first['check']['rows']:
        raise ValueError('Dataset manifest does not match ALS trials')
    settings_path = root / 'settings.json'
    if settings_path.exists():
        settings = read_json(settings_path)
        if settings.get('repeats') != repeats:
            raise ValueError('settings.json repeats differs from requested repeats')
    else:
        settings = {}
    rows, warnings = [], []
    total_bytes = None
    for label in ('H1', 'H2'):
        for i in range(1, repeats + 1):
            prefix = f'{label}-{i}'
            before_cache = read_cache(root / f'{prefix}-before-cache.txt')
            after_cache = read_cache(root / f'{prefix}-after-cache.txt')
            for cache in (before_cache, after_cache):
                if total_bytes is None:
                    total_bytes = cache['totalBytes']
                if cache['totalBytes'] != total_bytes:
                    raise ValueError('Train byte size differs between snapshots')
            if label == 'H1' and before_cache['cachedBytes'] != 0:
                raise ValueError(f'{prefix}: H1 did not start with empty train cache')
            if label == 'H2' and before_cache['cachedBytes'] != before_cache['totalBytes']:
                raise ValueError(f'{prefix}: H2 did not start with all train bytes cached')
            before_name, after_name = f'{prefix}-before-metrics.txt', f'{prefix}-after-metrics.txt'
            deltas = counter_delta(read_counters(root / before_name), read_counters(root / after_name))
            row = {'label': label, 'repeat': i, 'cacheAtStartPercent': before_cache['percent'],
                   'cacheAtEndPercent': after_cache['percent'],
                   **{FIELDS[k]: deltas[k]['deltaMiBApprox'] for k in METRICS},
                   'ufsRoundingUpperMiB': deltas['BytesReadUfsAll']['roundingUpperMiB'],
                   'ufsStatus': deltas['BytesReadUfsAll']['status'],
                   'counterDetails': deltas, 'snapshotFiles': [before_name, after_name]}
            if label == 'H2' and row['cacheReadMiBApprox'] == 0:
                warnings.append(f'{prefix}: no displayed cache-read increase; inspect collection/heartbeat lag.')
            if label == 'H2' and row['ufsReadMiBApprox'] > 0:
                warnings.append(f'{prefix}: warm run has displayed UFS reads; inspect lag, other activity and cache.')
            if label == 'H2' and after_cache['cachedBytes'] != total_bytes:
                warnings.append(f'{prefix}: train cache is no longer full at the after snapshot.')
            rows.append(row)
    metrics_summary = []
    for label in ('H1', 'H2'):
        for metric in METRICS:
            values = [r[FIELDS[metric]] for r in rows if r['label'] == label]
            median, iqr = median_iqr(values)
            metrics_summary.append({'label': label, 'metric': metric, 'runs': len(values),
                                    'medianMiBApprox': median, 'iqrMiBApprox': iqr})
    timings = []
    for phase in PHASES:
        direct = statistics.median(trials['H0', i][phase] for i in range(1, repeats + 1))
        for label in ('H0', 'H1', 'H2'):
            median, iqr = median_iqr([trials[label, i][phase] for i in range(1, repeats + 1)])
            timings.append({'phase': phase, 'label': label, 'runs': repeats, 'medianSeconds': median,
                            'iqrSeconds': iqr, 'speedupVsH0': direct / median,
                            'timeReductionVsH0Percent': 100 * (1 - median / direct)})
    warm = [r for r in rows if r['label'] == 'H2']
    cold = [r for r in rows if r['label'] == 'H1']
    return {'experiment': root.name, 'datasetVersion': first['datasetVersion'],
            'trainCheck': first['check'], 'alsParams': first['alsParams'], 'environment': first['environment'],
            'settings': settings, 'repeatsPerLabel': repeats, 'trainBytes': total_bytes,
            'units': 'MiB approximations; displayed B/KB/MB/GB use powers of 1024; rounded CLI input',
            'metricTrials': rows, 'metricSummary': metrics_summary, 'timingSummary': timings,
            'evidenceCounts': {'warmRunsNoDisplayedUfsIncrease': sum(r['ufsReadMiBApprox'] == 0 for r in warm),
                               'warmRunsPositiveCacheDelta': sum(r['cacheReadMiBApprox'] > 0 for r in warm),
                               'coldRunsPositiveUfsDelta': sum(r['ufsReadMiBApprox'] > 0 for r in cold)},
            'warnings': warnings,
            'limitations': ['Snapshots are cluster-wide and heartbeat-delayed; deltas may include other activity.',
                            'Zero displayed delta is not proof of exactly zero bytes or an exact 100% cache hit rate.',
                            'Rounding bounds account only for display precision, not collection lag.',
                            'BytesReadRemote is client-worker RPC traffic, not equivalent to HDFS reads.',
                            'Cache and RPC counters overlap; do not sum them as disjoint reads.',
                            'UFS counter does not measure direct H0 reads.',
                            'ALS read timer includes persist and aggregate, not pure I/O.',
                            'Warm preload and Spark startup are excluded; OS page cache is uncontrolled.']}


def write_csv(path, rows):
    if not rows:
        raise ValueError('No rows to export')
    with Path(path).open('w', encoding='utf-8-sig', newline='') as f:
        writer = csv.DictWriter(f, fieldnames=list(rows[0]))
        writer.writeheader()
        writer.writerows(rows)


def save_report(report, out):
    out = Path(out)
    if out.exists():
        raise ValueError('Output exists; preserve it or choose a new --out: ' + str(out))
    out.mkdir(parents=True, exist_ok=False)
    (out / 'metrics-summary.json').write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding='utf-8')
    flat = [{k: v for k, v in row.items() if k not in ('counterDetails', 'snapshotFiles')} for row in report['metricTrials']]
    write_csv(out / 'metrics-trials.csv', flat)
    write_csv(out / 'metrics-summary.csv', report['metricSummary'])
    write_csv(out / 'timing-summary.csv', report['timingSummary'])
    lines = ['# Tổng hợp thực nghiệm Alluxio', '', f"Thư mục: {report['experiment']}",
             f"Dataset: {report['datasetVersion']}; train: {report['trainCheck']['rows']:,} dòng.",
             f"Số lần mỗi trường hợp: {report['repeatsPerLabel']}.", '',
             '## Thời gian trung vị / IQR (giây)', '',
             '| Giai đoạn | H0 | H1 | H2 | Tăng tốc H2/H0 | Giảm thời gian H2 |',
             '|---|---:|---:|---:|---:|---:|']
    titles = dict(zip(PHASES, ('Đọc + persist + aggregate', 'Fit + materialize vector', 'Tổng đọc + fit')))
    lookup = {(r['phase'], r['label']): r for r in report['timingSummary']}
    for phase in PHASES:
        cells = [f"{lookup[phase, label]['medianSeconds']:.2f} / {lookup[phase, label]['iqrSeconds']:.2f}" for label in ('H0', 'H1', 'H2')]
        h2 = lookup[phase, 'H2']
        lines.append(f"| {titles[phase]} | " + ' | '.join(cells) + f" | {h2['speedupVsH0']:.3f}× | {h2['timeReductionVsH0Percent']:.2f}% |")
    lines += ['', '## Lượng đọc phát sinh từng lần (MiB xấp xỉ)', '',
              '| Nhãn | Lần | Cache đầu (%) | Cache cuối (%) | Đọc cache | RPC | UFS |',
              '|---|---:|---:|---:|---:|---:|---:|']
    for row in report['metricTrials']:
        lines.append(f"| {row['label']} | {row['repeat']} | {row['cacheAtStartPercent']:.2f} | {row['cacheAtEndPercent']:.2f} | {row['cacheReadMiBApprox']:.3f} | {row['rpcReadMiBApprox']:.3f} | {row['ufsReadMiBApprox']:.3f} |")
    e = report['evidenceCounts']; n = report['repeatsPerLabel']
    lines += ['', '## Bằng chứng được ghi nhận', '',
              f"- H2 có lượng đọc cache tăng: {e['warmRunsPositiveCacheDelta']}/{n} lần.",
              f"- H2 không tăng UFS ở độ chính xác hiển thị: {e['warmRunsNoDisplayedUfsIncrease']}/{n} lần.",
              f"- H1 có lượng đọc UFS tăng: {e['coldRunsPositiveUfsDelta']}/{n} lần.",
              '- Các số đếm trên mô tả snapshot; không kết luận cache hit chính xác 100%.', '',
              '## Lưu ý và cảnh báo', '']
    lines += ['- ' + warning for warning in report['warnings']] or ['- Không có cảnh báo từ các kiểm tra đã thực hiện.']
    lines += ['', 'Giới hạn đo:', ''] + ['- ' + note for note in report['limitations']]
    (out / 'report.md').write_text('\n'.join(lines) + '\n', encoding='utf-8')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--results', required=True)
    parser.add_argument('--repeats', type=int, default=5)
    parser.add_argument('--out', help='Default: RESULTS/metrics-summary-v1; existing output is refused')
    args = parser.parse_args()
    out = Path(args.out) if args.out else Path(args.results) / 'metrics-summary-v1'
    if out.exists():
        parser.error('Output already exists; choose a new --out folder.')
    try:
        report = build_report(args.results, args.repeats)
        save_report(report, out)
    except (ValueError, KeyError, OSError, json.JSONDecodeError) as exc:
        parser.exit(1, 'ERROR: ' + str(exc) + '\n')
    print(json.dumps({'output': str(out), 'datasetVersion': report['datasetVersion'],
                      'repeatsPerLabel': report['repeatsPerLabel'],
                      'evidenceCounts': report['evidenceCounts'], 'warnings': report['warnings']},
                     ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()
