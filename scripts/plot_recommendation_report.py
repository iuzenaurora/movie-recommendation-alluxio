"""Export data-derived figures for the report; no synthetic measurements."""
import argparse
import json
from pathlib import Path

import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import numpy as np


def plot(root):
    root = Path(root)
    quality = json.loads((root / 'summary.json').read_text(encoding='utf-8'))
    api = json.loads((root / 'api/summary.json').read_text(encoding='utf-8'))
    target = root / 'figures'
    target.mkdir(exist_ok=True)
    modes = ['als_fold_in', 'hybrid', 'genre', 'popularity']
    labels = ['ALS fold-in', 'Hybrid', 'Theo gu', 'Phổ biến']
    colors = ['#2563eb', '#d97706', '#0d9488', '#7c3aed']
    scenarios = list(quality['scenarios'])
    x = np.arange(len(scenarios))
    width = .18
    fig, ax = plt.subplots(figsize=(10, 5.5), layout='constrained')
    for i, (mode, label, color) in enumerate(zip(modes, labels, colors)):
        values = [quality['scenarios'][name]['modes'][mode]['ndcg'] for name in scenarios]
        bars = ax.bar(x + (i - 1.5) * width, values, width, label=label, color=color)
        ax.bar_label(bars, fmt='%.4f', fontsize=8, padding=3)
    ax.set(xticks=x, xticklabels=['Tối đa 5 đánh giá', 'Tối đa 20 đánh giá', 'Toàn bộ lịch sử'], ylabel='NDCG@10 (cao hơn tốt hơn)',
           title='Chất lượng xếp hạng trên cùng mẫu người dùng và tập test')
    ax.set_ylim(0, ax.get_ylim()[1] * 1.16)
    ax.legend(ncols=4, frameon=False, loc='upper left')
    ax.spines[['top', 'right']].set_visible(False)
    ax.yaxis.grid(True, alpha=.2)
    ax.set_axisbelow(True)
    for extension in ['png', 'svg']:
        fig.savefig(target / ('ndcg.' + extension), dpi=180)
    plt.close(fig)
    concurrencies = api['protocol']['concurrency']
    fig, axes = plt.subplots(1, len(concurrencies), figsize=(12, 5), layout='constrained', squeeze=False, sharey=True)
    latency_max = max(r['p95Ms'] for r in api['aggregates']) * 1.15
    for ax, concurrency in zip(axes[0], concurrencies):
        rows = {r['mode']: r for r in api['aggregates'] if r['concurrency'] == concurrency}
        x = np.arange(len(modes))
        for offset, key, label, color in [(-.18, 'p50Ms', 'p50', '#93c5fd'), (.18, 'p95Ms', 'p95', '#1d4ed8')]:
            bars = ax.bar(x + offset, [rows[m][key] for m in modes], .36, label=label, color=color)
            ax.bar_label(bars, fmt='%.0f', fontsize=8, padding=3)
        ax.set(xticks=x, xticklabels=labels, ylabel='Thời gian HTTP (ms)', title=f'{concurrency} request đồng thời')
        ax.set_ylim(0, latency_max)
        ax.legend(frameon=False)
        ax.spines[['top', 'right']].set_visible(False)
        ax.yaxis.grid(True, alpha=.2)
        ax.set_axisbelow(True)
    fig.suptitle('Độ trễ API thật trên Docker; không bao gồm Laravel và trình duyệt')
    for extension in ['png', 'svg']:
        fig.savefig(target / ('api-latency.' + extension), dpi=180)
    plt.close(fig)
    print(target)


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--results', required=True)
    plot(parser.parse_args().results)
