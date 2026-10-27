"""Held-out evaluation helpers; no demo profiles or full-dataset popularity."""
import hashlib
import json
from pathlib import Path

import numpy as np
from recommend_core import fold_in, rank_movies, rank_hybrid, ranking_metrics

MODES = ('als_fold_in', 'hybrid', 'genre', 'popularity')


def favorite_genre(history, movies):
    """Match Laravel RecommendationController: positive ratings only, stable ties."""
    weights = {}
    for mid, rating in sorted(history.items()):
        if rating <= 3:
            continue
        genres = movies.get(int(mid), {}).get('genres', '')
        genres = genres if isinstance(genres, list) else genres.split('|')
        for genre in genres:
            if genre and genre != '(no genres listed)':
                weights[genre] = weights.get(genre, 0.) + (rating - 3.) / np.sqrt(max(1, len(genres)))
    return max(weights, key=weights.get) if weights else ''


def select_users(ids, limit, seed):
    return sorted((int(uid) for uid in set(ids)),
                  key=lambda uid: (hashlib.sha256(f'{seed}:{uid}'.encode()).digest(), uid))[:limit]


def load_package(folder):
    root = Path(folder)
    meta = json.loads((root / 'manifest.json').read_text(encoding='utf-8'))
    if meta.get('servingRefit') or meta.get('parentModelVersion'):
        raise ValueError('Held-out evaluation requires the original train-only model, not a serving retrain/refit')
    required = {'factors.npz', 'movies.json', 'candidate_movie_ids.json'}
    if not required.issubset(meta.get('checksums', {})):
        raise ValueError('Checksummed factors, movie metadata and candidate ids are required')
    for name, digest in meta['checksums'].items():
        if Path(name).name != name or hashlib.sha256((root / name).read_bytes()).hexdigest() != digest:
            raise ValueError('Model checksum mismatch: ' + name)
    with np.load(root / 'factors.npz', allow_pickle=False) as data:
        ids, factors = data['movie_ids'].copy(), data['movie_factors'].copy()
    if len(set(ids.tolist())) != len(ids) or factors.ndim != 2 or len(ids) != len(factors) or not np.isfinite(factors).all():
        raise ValueError('Invalid movie factors')
    movies = {int(row['movieId']): row for row in json.loads((root / 'movies.json').read_text(encoding='utf-8'))}
    candidates = set(json.loads((root / 'candidate_movie_ids.json').read_text()))
    if not candidates or not candidates.issubset(set(ids.tolist())):
        raise ValueError('Invalid candidate policy')
    return meta, ids, factors, movies, candidates


def recommend_all(history, ids, factors, movies, candidates, popular, reg, k=10):
    vector = fold_in(history, ids, factors, reg)
    genre = favorite_genre(history, movies)
    genre_ids = {mid for mid in candidates if not genre or genre in movies.get(mid, {}).get('genres', '').split('|')}
    fallback = [(mid, mean) for mid, mean in popular if mid not in history and mid in candidates][:k]
    if vector is None:
        # Filter before truncation, exactly as the serving endpoint does.
        genre_fallback = [(mid, mean) for mid, mean in popular if mid not in history and mid in genre_ids][:k]
        return {'als_fold_in': fallback, 'hybrid': fallback, 'genre': genre_fallback,
                'popularity': fallback}, vector, genre
    return {
        'als_fold_in': rank_movies(vector, ids, factors, history, k, candidates),
        'hybrid': rank_hybrid(vector, ids, factors, history, movies, k, candidates),
        'genre': rank_movies(vector, ids, factors, history, k, genre_ids),
        'popularity': fallback,
    }, vector, genre


def summarize_rows(rows, k):
    """All methods use the same positive-test users; never drop short/empty lists."""
    result = {}
    for mode in MODES:
        selected = [row for row in rows if row['mode'] == mode]
        valid = [row for row in selected if row['metrics'] is not None]
        result[mode] = {
            'users': len(selected), 'rankingUsers': len(valid),
            'usersWithoutTestPositives': len(selected) - len(valid),
            'precision': float(np.mean([r['metrics']['precision'] for r in valid])) if valid else None,
            'recall': float(np.mean([r['metrics']['recall'] for r in valid])) if valid else None,
            'ndcg': float(np.mean([r['metrics']['ndcg'] for r in valid])) if valid else None,
            'meanListLength': float(np.mean([len(r['recommended']) for r in selected])) if selected else 0.,
            'fullListRate': sum(len(r['recommended']) == k for r in selected) / len(selected) if selected else 0.,
            'fallbackUsers': sum(r['fallback'] for r in selected),
            'catalogRecommended': len({mid for r in selected for mid in r['recommended']}),
        }
    return result


def paired_ndcg_intervals(rows, seed=42, samples=2000):
    """Paired user bootstrap, exploratory 95% percentile interval versus fold-in."""
    by_mode = {mode: {r['userId']: r['metrics']['ndcg'] for r in rows
                      if r['mode'] == mode and r['metrics'] is not None} for mode in MODES}
    users = sorted(by_mode['als_fold_in'])
    if not users:
        return {}
    rng = np.random.default_rng(seed)
    result = {}
    for mode in MODES[1:]:
        delta = np.array([by_mode[mode][uid] - by_mode['als_fold_in'][uid] for uid in users])
        boot = np.mean(delta[rng.integers(0, len(delta), (samples, len(delta)))], axis=1)
        result[mode] = {'deltaMean': float(delta.mean()), 'lower95': float(np.quantile(boot, .025)),
                        'upper95': float(np.quantile(boot, .975)), 'users': len(users), 'bootstrapSamples': samples}
    return result
