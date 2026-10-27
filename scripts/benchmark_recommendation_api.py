"""Closed-loop HTTP load test against an isolated, real FastAPI + MongoDB stack."""
import argparse
import concurrent.futures
import csv
import datetime
import hashlib
import json
import platform
import random
import sys
import time
import uuid
from pathlib import Path

import httpx
import numpy as np
from pymongo import MongoClient

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'src'))
from evaluation_protocol import MODES


def summarize_requests(rows, elapsed):
    ok = [r for r in rows if r['ok']]
    values = [r['clientMs'] for r in ok]
    server = [r['serverMs'] for r in ok]
    return {'requests': len(rows), 'successes': len(ok), 'errors': len(rows) - len(ok),
            'errorRate': (len(rows) - len(ok)) / len(rows) if rows else None,
            'p50Ms': float(np.quantile(values, .5)) if values else None,
            'p95Ms': float(np.quantile(values, .95)) if values else None,
            'serverP50Ms': float(np.quantile(server, .5)) if server else None,
            'serverP95Ms': float(np.quantile(server, .95)) if server else None,
            'elapsedSeconds': elapsed, 'successRequestsPerSecond': len(ok) / elapsed,
            'attemptsPerSecond': len(rows) / elapsed}


def benchmark(args):
    quality_dir = Path(args.quality)
    quality_file = quality_dir / 'summary.json'
    quality = json.loads(quality_file.read_text(encoding='utf-8'))
    contexts = json.loads((quality_dir / 'api-contexts.json').read_text(encoding='utf-8'))[:args.users]
    if not contexts:
        raise ValueError('No held-out history contexts')
    if not args.database.startswith('movies_benchmark_') or not args.database.replace('_', '').isalnum():
        raise ValueError('Only an isolated movies_benchmark_* database is permitted')
    out = Path(args.out)
    out.mkdir(parents=True, exist_ok=False)
    token = Path(args.token_file).read_text().strip()
    client = httpx.Client(base_url=args.base_url, headers={'X-Service-Token': token},
                          timeout=args.timeout, trust_env=False,
                          limits=httpx.Limits(max_connections=max(args.concurrency), max_keepalive_connections=max(args.concurrency)))
    mongo = MongoClient(args.mongo_uri, serverSelectionTimeoutMS=10000)
    health = client.get('/health').raise_for_status().json()
    if health.get('database') != args.database or health['modelVersion'] != quality['modelVersion']:
        raise ValueError('API database/model differs from the isolated evaluation configuration')
    db = mongo[args.database]
    run_id = uuid.uuid4().hex
    users = []
    for context in contexts:
        uid = str(uuid.uuid4())
        db.users.insert_one({'_id': uid, 'benchmarkRun': run_id, 'seedHistory': context['history'],
                             'sourceUserId': context['userId'], 'createdAt': time.time()})
        users.append((uid, context))
    raw, groups = [], []

    def request(mode, ordinal):
        uid, context = users[ordinal % len(users)]
        params = {'method': {'als_fold_in': 'als', 'genre': 'als', 'hybrid': 'hybrid', 'popularity': 'popularity'}[mode], 'k': 10}
        if mode == 'genre':
            params['genre'] = context['genre']
        tick = time.perf_counter()
        row = {'mode': mode, 'ordinal': ordinal, 'sourceUserId': context['userId'], 'ok': False,
               'status': None, 'serverMs': None, 'error': None}
        try:
            response = client.get(f'/users/{uid}/recommendations', params=params)
            row['status'] = response.status_code
            response.raise_for_status()
            body = response.json()
            if body.get('modelVersion') != quality['modelVersion'] or not isinstance(body.get('items'), list):
                raise ValueError('Unexpected model or response schema')
            if not isinstance(body.get('milliseconds'), (int, float)) or not np.isfinite(body['milliseconds']):
                raise ValueError('Missing server timing')
            mids = [item['movieId'] for item in body['items']]
            if len(mids) > 10 or len(mids) != len(set(mids)) or set(mids) & {int(mid) for mid in context['history']}:
                raise ValueError('Invalid list or seen item returned')
            row.update(ok=True, serverMs=float(body['milliseconds']), listLength=len(mids))
        except (httpx.HTTPError, ValueError, KeyError, TypeError) as exc:
            # Never serialize request headers/service credentials in the evidence.
            row['error'] = type(exc).__name__
        row['clientMs'] = (time.perf_counter() - tick) * 1000
        return row

    try:
        for concurrency in args.concurrency:
            for repeat in range(1, args.repeats + 1):
                order = list(MODES)
                random.Random(args.seed + concurrency * 100 + repeat).shuffle(order)
                for mode in order:
                    warm = [request(mode, i) for i in range(args.warmup)]
                    if any(not r['ok'] for r in warm):
                        raise RuntimeError('API warmup failed for ' + mode)
                    tick = time.perf_counter()
                    with concurrent.futures.ThreadPoolExecutor(max_workers=concurrency) as executor:
                        rows = list(executor.map(lambda ordinal: request(mode, ordinal), range(args.requests)))
                    elapsed = time.perf_counter() - tick
                    for row in rows:
                        row.update(concurrency=concurrency, repeat=repeat)
                    raw.extend(rows)
                    group = {'mode': mode, 'concurrency': concurrency, 'repeat': repeat,
                             **summarize_requests(rows, elapsed)}
                    groups.append(group)
                    print(json.dumps(group), flush=True)
    finally:
        # Only records created by this run are removed. Production DB is never selected.
        db.ratings.delete_many({'appUserId': {'$in': [uid for uid, _ in users]}})
        db.users.delete_many({'benchmarkRun': run_id})
        client.close()
        mongo.close()
    aggregates = []
    for concurrency in args.concurrency:
        for mode in MODES:
            selected = [r for r in raw if r['mode'] == mode and r['concurrency'] == concurrency]
            runs = [r for r in groups if r['mode'] == mode and r['concurrency'] == concurrency]
            p95s = [r['p95Ms'] for r in runs if r['p95Ms'] is not None]
            aggregates.append({'mode': mode, 'concurrency': concurrency,
                               **summarize_requests(selected, sum(r['elapsedSeconds'] for r in runs)),
                               'repeats': len(runs),
                               'medianRunP95Ms': float(np.median(p95s)) if p95s else None})
    result = {'createdAtUTC': datetime.datetime.now(datetime.timezone.utc).isoformat(),
              'modelVersion': quality['modelVersion'], 'datasetVersion': quality['datasetVersion'],
              'qualitySummarySHA256': hashlib.sha256(quality_file.read_bytes()).hexdigest(),
              'protocol': {'transport': 'real HTTP to FastAPI with real isolated MongoDB',
                           'scope': 'HTTP client round-trip including API, Mongo history, fold-in, ranking and JSON serialization',
                           'excluded': ['Laravel request/session', 'browser rendering', 'poster downloads', 'HDFS/Alluxio', 'model startup'],
                           'historyScenario': quality['protocol']['apiHistoryScenario'], 'users': len(users),
                           'concurrency': args.concurrency, 'requestsPerModePerRepeat': args.requests,
                           'repeats': args.repeats, 'warmupPerGroup': args.warmup,
                           'loadModel': 'closed-loop fixed concurrency, no think time', 'seed': args.seed,
                           'percentile': 'NumPy linear quantile over successful requests; errors reported separately',
                           'groupOrder': 'deterministically shuffled each repeat',
                           'popularitySource': 'serving package full-data popularity; timing only, not quality evaluation'},
              'environment': {'python': platform.python_version(), 'platform': platform.platform(),
                              'httpx': httpx.__version__, 'apiThreads': 'default FastAPI sync threadpool',
                              'blasThreads': '1 in isolated API compose configuration'},
              'groups': groups, 'aggregates': aggregates,
              'limitations': ['API-level benchmark, not end-to-end browser latency.',
                              'One local Docker host and one API process; do not generalize as production capacity.',
                              'Quality and latency use matching history scenarios but different popularity sources as documented.',
                              'Raw latencies are machine/load dependent; success RPS is achieved throughput, not maximum capacity.']}
    (out / 'summary.json').write_text(json.dumps(result, indent=2, ensure_ascii=False), encoding='utf-8')
    for name, values in [('requests.csv', raw), ('groups.csv', groups), ('latency.csv', aggregates)]:
        fields = list(dict.fromkeys(key for row in values for key in row))
        with (out / name).open('w', newline='', encoding='utf-8-sig') as stream:
            writer = csv.DictWriter(stream, fieldnames=fields)
            writer.writeheader()
            writer.writerows(values)
    report = ['# Benchmark API gợi ý', '',
              'Đo HTTP thật với FastAPI và MongoDB tách biệt. Không bao gồm Laravel hoặc trình duyệt.', '',
              '| Đồng thời | Phương pháp | Request | Lỗi | p50 (ms) | p95 (ms) | Thành công/giây |',
              '|---:|---|---:|---:|---:|---:|---:|']
    for r in aggregates:
        p50 = f"{r['p50Ms']:.2f}" if r['p50Ms'] is not None else 'N/A'
        p95 = f"{r['p95Ms']:.2f}" if r['p95Ms'] is not None else 'N/A'
        report.append(f"| {r['concurrency']} | {r['mode']} | {r['requests']} | {r['errors']} | {p50} | {p95} | {r['successRequestsPerSecond']:.2f} |")
    report += ['', '## Giới hạn', '', *['- ' + x for x in result['limitations']]]
    (out / 'report.md').write_text('\n'.join(report) + '\n', encoding='utf-8')
    if any(r['errors'] for r in aggregates):
        raise RuntimeError('Measured API errors: evidence saved, but experiment is not accepted as complete')
    print('Published ' + str(out / 'summary.json'), flush=True)


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--quality', required=True)
    parser.add_argument('--out', required=True)
    parser.add_argument('--base-url', default='http://api:8000')
    parser.add_argument('--mongo-uri', default='mongodb://mongo:27017')
    parser.add_argument('--database', default='movies_benchmark_report')
    parser.add_argument('--token-file', default='/evaluation-runtime/service-token')
    parser.add_argument('--concurrency', type=int, nargs='+', default=[1, 4])
    parser.add_argument('--repeats', type=int, default=3)
    parser.add_argument('--requests', type=int, default=50)
    parser.add_argument('--warmup', type=int, default=3)
    parser.add_argument('--users', type=int, default=50)
    parser.add_argument('--seed', type=int, default=42)
    parser.add_argument('--timeout', type=float, default=60)
    args = parser.parse_args()
    if min(args.concurrency + [args.repeats, args.requests, args.users]) < 1 or args.warmup < 1:
        parser.error('Positive concurrency, repeats, requests, users and warmup required')
    benchmark(args)
