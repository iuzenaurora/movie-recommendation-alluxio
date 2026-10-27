"""Compare held-out offline lists with actual HTTP responses, outside timed load."""
import argparse
import json
import uuid
from pathlib import Path

import httpx
from pymongo import MongoClient


def check(root, limit=10):
    root = Path(root)
    summary = json.loads((root / 'summary.json').read_text(encoding='utf-8'))
    contexts = json.loads((root / 'api-contexts.json').read_text(encoding='utf-8'))[:limit]
    expected = {}
    for line in (root / 'per-user.jsonl').read_text(encoding='utf-8').splitlines():
        row = json.loads(line)
        if row['scenario'] == summary['protocol']['apiHistoryScenario']:
            expected[row['userId'], row['mode']] = row['recommended']
    token = Path('/evaluation-runtime/service-token').read_text().strip()
    results = []
    with httpx.Client(base_url='http://api:8000', headers={'X-Service-Token': token}, timeout=60, trust_env=False) as client, MongoClient('mongodb://mongo:27017') as mongo:
        health = client.get('/health').raise_for_status().json()
        database = health.get('database', '')
        if not database.startswith('movies_benchmark_') or health['modelVersion'] != summary['modelVersion']:
            raise ValueError('Parity check requires matching isolated API')
        db = mongo[database]
        for context in contexts:
            uid = str(uuid.uuid4())
            db.users.insert_one({'_id': uid, 'seedHistory': context['history'], 'benchmarkRun': 'parity-' + uid})
            try:
                for mode in ['als_fold_in', 'hybrid', 'genre']:
                    params = {'method': 'hybrid' if mode == 'hybrid' else 'als', 'k': 10}
                    if mode == 'genre':
                        params['genre'] = context['genre']
                    body = client.get(f'/users/{uid}/recommendations', params=params).raise_for_status().json()
                    actual = [row['movieId'] for row in body['items']]
                    if actual != expected[context['userId'], mode]:
                        raise ValueError(f'Offline/HTTP ranking mismatch: user {context["userId"]}, {mode}')
                    results.append({'sourceUserId': context['userId'], 'mode': mode, 'exactTopKMatch': True})
            finally:
                db.users.delete_one({'_id': uid, 'benchmarkRun': 'parity-' + uid})
    evidence = {'modelVersion': summary['modelVersion'], 'checks': results,
                'scope': 'personalized modes only; popularity intentionally differs (train-only offline vs serving full package)'}
    (root / 'serving-parity.json').write_text(json.dumps(evidence, indent=2), encoding='utf-8')
    print(f'Passed {len(results)} exact offline/HTTP Top-K comparisons')


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--results', required=True)
    parser.add_argument('--users', type=int, default=10)
    args = parser.parse_args()
    if args.users < 1:
        parser.error('users must be positive')
    check(args.results, args.users)
