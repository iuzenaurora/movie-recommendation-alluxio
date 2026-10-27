"""Publish a combined report only after complete quality and HTTP evidence exists."""
import argparse
import datetime
import hashlib
import json
from pathlib import Path

NAMES = {'als_fold_in': 'ALS fold-in', 'hybrid': 'ALS + gu (Hybrid)',
         'genre': 'Theo gu (lọc thể loại + ALS)', 'popularity': 'Phổ biến (baseline)'}


def publish(root):
    root = Path(root)
    quality_file, latency_file = root / 'summary.json', root / 'api/summary.json'
    q = json.loads(quality_file.read_text(encoding='utf-8'))
    a = json.loads(latency_file.read_text(encoding='utf-8'))
    if q['modelVersion'] != a['modelVersion'] or a['qualitySummarySHA256'] != hashlib.sha256(quality_file.read_bytes()).hexdigest():
        raise ValueError('Quality/API provenance differs')
    if len(a['groups']) != 4 * len(a['protocol']['concurrency']) * a['protocol']['repeats']:
        raise ValueError('Incomplete API groups')
    if any(row['errors'] or row['successes'] != row['requests'] for row in a['aggregates']):
        raise ValueError('API errors remain; do not publish a complete report')
    expected_groups = {(mode, c, repeat) for mode in NAMES for c in a['protocol']['concurrency']
                       for repeat in range(1, a['protocol']['repeats'] + 1)}
    if {(r['mode'], r['concurrency'], r['repeat']) for r in a['groups']} != expected_groups:
        raise ValueError('API workload groups are duplicated or missing')
    if q['protocol']['k'] != 10:
        raise ValueError('This report template requires K=10')
    parity = json.loads((root / 'serving-parity.json').read_text(encoding='utf-8'))
    if parity['modelVersion'] != q['modelVersion'] or not parity['checks'] or not all(r['exactTopKMatch'] for r in parity['checks']):
        raise ValueError('Offline/HTTP parity is not verified')
    per_user = [json.loads(line) for line in (root / 'per-user.jsonl').read_text(encoding='utf-8').splitlines()]
    if len(per_user) != q['protocol']['usersSampled'] * 4 * len(q['scenarios']):
        raise ValueError('Incomplete per-user quality evidence')
    local_time = datetime.datetime.fromisoformat(q['createdAtUTC']).astimezone(datetime.timezone(datetime.timedelta(hours=7)))
    lines = ['# Kết quả thực nghiệm bổ sung cho báo cáo MovieLab', '',
             f"Thời điểm hoàn tất quality: {local_time.strftime('%d/%m/%Y %H:%M:%S')} (UTC+7). Model: `{q['modelVersion']}`. Dataset: `{q['datasetVersion']}`.",
             '', '## 1. Giao thức đánh giá chất lượng', '',
             f"- Lấy mẫu cố định {q['protocol']['usersSampled']} người dùng từ tập test bằng SHA256, seed={q['protocol']['seed']}.",
             '- Dùng model gốc train-only. Kiểm tra SHA256 model và toàn bộ file split trước khi đo.',
             '- Lịch sử chỉ lấy từ train + validation; ground truth là phim test được chấm từ 4 sao.',
             '- Ba mức lịch sử: tối đa 5 đánh giá gần nhất, tối đa 20 đánh giá gần nhất và toàn bộ lịch sử trước test.',
             f"- Mỗi cách gợi ý dùng cùng {q['protocol']['candidateMovies']} phim ứng viên theo train; chỉ loại phim đã có trong lịch sử được cung cấp.",
             '- Theo gu chọn thể loại từ đánh giá >3 sao như Laravel. Hybrid giữ trọng số 0,75, không chỉnh theo test.',
             '- Phổ biến xếp theo số lượt đánh giá trong train; không dùng popularity.json toàn dataset để đánh giá chất lượng.',
             '- Recall giữ toàn bộ positive test trong mẫu số, kể cả phim không có vector hoặc bị lọc thể loại.',
             '- Danh sách rỗng/ngắn không bị bỏ khỏi kết quả. Người không có positive test được báo riêng.',
             '', '## 2. Kết quả chất lượng Top 10', '']
    for scenario, values in q['scenarios'].items():
        observed = [r['historySize'] for r in per_user if r['scenario'] == scenario and r['mode'] == 'als_fold_in']
        lines += ['### Lịch sử ' + scenario, '',
                  f'Lịch sử thực tế: {min(observed)}–{max(observed)} đánh giá/người. Người có ít hơn ngưỡng dùng toàn bộ lịch sử sẵn có.', '',
                  '| Cách gợi ý | Người đánh giá | Precision@10 | Recall@10 | NDCG@10 | Độ dài TB | Tỷ lệ đủ 10 |',
                  '|---|---:|---:|---:|---:|---:|---:|']
        for mode, row in values['modes'].items():
            lines.append(f"| {NAMES[mode]} | {row['rankingUsers']} | {row['precision']:.6f} | {row['recall']:.6f} | {row['ndcg']:.6f} | {row['meanListLength']:.2f} | {row['fullListRate']:.2%} |")
        winner = max(values['modes'], key=lambda mode: values['modes'][mode]['ndcg'])
        missing = values['modes']['als_fold_in']['usersWithoutTestPositives']
        lines += ['', f"Nhận xét: {NAMES[winner]} có NDCG cao nhất trong mẫu và kịch bản này. "
                  f"Có {missing} người không có positive test; không tính họ vào trung bình Precision/Recall/NDCG.", '']
        for mode, row in values['pairedNdcgVsFoldIn'].items():
            label = 'khoảng chứa 0, chưa đủ bằng chứng về hướng chênh lệch' if row['lower95'] <= 0 <= row['upper95'] else 'khoảng không chứa 0 trong phép bootstrap này'
            lines.append(f"- {NAMES[mode]} so với ALS fold-in: ΔNDCG={row['deltaMean']:+.6f}; "
                         f"CI 95% [{row['lower95']:+.6f}, {row['upper95']:+.6f}]; {label}.")
        lines += ['', 'Khoảng tin cậy ghép cặp theo người dùng, 2.000 lần bootstrap; mang tính thăm dò, chưa hiệu chỉnh so sánh nhiều lần.', '']
    if (root / 'figures/ndcg.png').exists():
        lines += ['![So sánh NDCG theo lượng lịch sử](figures/ndcg.png)', '']
    lines += ['## 3. Sai số dự đoán sao của ALS fold-in', '',
              '| Lịch sử | RMSE ALS | MAE ALS | RMSE trung bình phim | MAE trung bình phim | Cặp có dự đoán / tổng | Coverage |',
              '|---|---:|---:|---:|---:|---:|---:|']
    for scenario, values in q['scenarios'].items():
        r = values['ratingPrediction']
        lines.append(f"| {scenario} | {r['alsFoldInRMSE']:.6f} | {r['alsFoldInMAE']:.6f} | {r['movieMeanRMSE']:.6f} | {r['movieMeanMAE']:.6f} | {r['scoredPairs']}/{r['totalPairs']} | {r['coverage']:.2%} |")
    lines += ['', 'Dùng điểm dự đoán chưa clip về 0,5–5 sao. Baseline được so trên đúng cùng cặp test.',
              'Không gán RMSE/MAE cho điểm Hybrid hoặc điểm sau lọc thể loại vì đây là điểm xếp hạng, chưa được hiệu chỉnh thành sao.',
              '', '## 4. Benchmark API thật', '',
              f"Dùng {a['protocol']['users']} hồ sơ từ kịch bản {a['protocol']['historyScenario']}; "
              f"{a['protocol']['requestsPerModePerRepeat']} request/cách/lượt; {a['protocol']['repeats']} lượt; "
              f"{a['protocol']['warmupPerGroup']} request làm nóng mỗi nhóm.",
              'HTTP thật qua Docker tới FastAPI, MongoDB thật tách biệt, 1 API process. BLAS 1 luồng.',
              'Thời gian client gồm HTTP, đọc lịch sử MongoDB, tính fold-in/ranking, JSON và kiểm tra response ở client.',
              'Không bao gồm Laravel, session web, trình duyệt, ảnh poster, startup model hoặc HDFS/Alluxio.', '',
              '| Đồng thời | Cách gợi ý | Request | Lỗi | p50 HTTP (ms) | p95 HTTP (ms) | Request thành công/giây |',
              '|---:|---|---:|---:|---:|---:|---:|']
    for r in a['aggregates']:
        lines.append(f"| {r['concurrency']} | {NAMES[r['mode']]} | {r['requests']} | {r['errors']} | {r['p50Ms']:.2f} | {r['p95Ms']:.2f} | {r['successRequestsPerSecond']:.2f} |")
    if (root / 'figures/api-latency.png').exists():
        lines += ['', '![Độ trễ API theo concurrency](figures/api-latency.png)', '']
    environment_file = root / 'environment.json'
    if environment_file.exists():
        env = json.loads(environment_file.read_text(encoding='utf-8-sig'))
        lines += ['', f"Môi trường: Docker {env['serverVersion']}, {env['cpus']} CPU khả dụng trong Docker VM, "
                  f"RAM VM {env['dockerMemoryBytes'] / 1024 ** 3:.2f} GiB; API giới hạn "
                  f"{env['apiMemoryLimitBytes'] / 1024 ** 3:.2f} GiB. Đây không phải thông số RAM vật lý của host.", '']
    for c in a['protocol']['concurrency']:
        rows = [r for r in a['aggregates'] if r['concurrency'] == c]
        fastest = min(rows, key=lambda r: r['p95Ms'])
        slowest = max(rows, key=lambda r: r['p95Ms'])
        lines += [f"Nhận xét ở concurrency {c}: {NAMES[fastest['mode']]} có p95 thấp nhất ({fastest['p95Ms']:.2f} ms); "
                  f"{NAMES[slowest['mode']]} cao nhất ({slowest['p95Ms']:.2f} ms). "
                  'Đây là số đo đạt được trên môi trường hiện tại, không phải thứ tự tốc độ đảm bảo ở mọi máy.', '']
    lines += ['', 'p50/p95 tính trên request thành công của các lượt gộp lại. RPS là thông lượng đạt được ở tải thử, không phải công suất tối đa.',
              'Các chế độ có thứ tự được trộn cố định mỗi lượt để giảm thiên lệch thứ tự. Xem api/groups.csv để đánh giá dao động giữa các lượt.',
              'Popularity của API dùng gói phục vụ hiện tại (có toàn dữ liệu), chỉ dùng đo tốc độ. Bảng chất lượng phía trên dùng train-only để tránh leakage.',
              '', '## 5. Liên hệ với benchmark HDFS/Alluxio đã có', '',
              '| Đợt đo | Thời gian đọc H2 so H0 | Tổng đọc + train H2 so H0 |', '|---|---:|---:|']
    storage_sources = [
        ('Một máy, 11/10/2026', Path('experiments/als-benchmark/ml32m-als-20261011-121602/summary-als.json'), 'rows'),
        ('Hai máy, 12/10/2026', Path('experiments/als-benchmark/ml32m-vpn-als-20261012-205245/metrics-summary-v1/metrics-summary.json'), 'timingSummary')]
    for label, path, key in storage_sources:
        if not path.exists():
            continue
        rows = json.loads(path.read_text(encoding='utf-8'))[key]
        changes = []
        for phase in ('readMaterializeSeconds', 'readPlusFitSeconds'):
            h0 = next(r['medianSeconds'] for r in rows if r['label'] == 'H0' and r['phase'] == phase)
            h2 = next(r['medianSeconds'] for r in rows if r['label'] == 'H2' and r['phase'] == phase)
            changes.append(f'{100 * (h2 / h0 - 1):+.2f}%')
        lines.append(f'| {label} | {changes[0]} | {changes[1]} |')
    lines += ['', 'Giá trị âm là giảm thời gian, dương là tăng. Hai đợt đo khác ngày và điều kiện máy; không dùng để chứng minh hai máy luôn nhanh hơn một máy.',
              'Alluxio hỗ trợ đường đọc dữ liệu Spark. Các request API từ vector trong RAM không đi qua HDFS/Alluxio.',
              '', '## 6. Giới hạn và kết luận có thể sử dụng', '',
              '- Đã bổ sung phép so sánh offline ghép cặp cho 4 cách gợi ý và đo tải HTTP thật cho cùng 4 chế độ.',
              '- Đây là mô phỏng phản hồi ít/nhiều trên người dùng MovieLens; chưa phải đánh giá người dùng mới hoàn toàn vì item factors đã học từ train gốc.',
              '- Khác mẫu và giao thức với bảng Spark ALS trước đây; không lấy chênh lệch hai bảng để tuyên bố model tăng chất lượng.',
              '- Ground truth chỉ gồm các đánh giá quan sát được; phim chưa được chấm không đồng nghĩa người dùng không thích.',
              '- Không kết luận gu thể loại tốt hơn chỉ vì danh sách nhìn hợp gu. Dùng bảng test và khoảng tin cậy để diễn giải.',
              '- Chưa có đo toàn bộ đường Laravel/trình duyệt hoặc nghiên cứu mức hài lòng người dùng.',
              '', '## 7. Bằng chứng và tái lập', '',
              '- summary.json: giao thức, seed, cohort, kết quả và SHA256 nguồn.',
              '- quality.csv: bảng chất lượng; per-user.jsonl: kết quả từng người/cách/kịch bản.',
              '- api/requests.csv: từng request; api/groups.csv: từng lượt; api/latency.csv: bảng tổng hợp.',
              f"- serving-parity.json: {len(parity['checks'])} đối chiếu Top 10 giữa evaluator và HTTP thật, khớp hoàn toàn.",
              '- environment.json: cấu hình Docker và phiên bản image của đợt đo (nếu chạy bằng script điều phối).',
              '- scripts/run_report_experiments.ps1: tự build, đánh giá, đo API, tổng hợp và dừng stack riêng.', '']
    lines += [f'Bằng chứng gốc của đợt này: `{root.as_posix()}`.', '']
    report = root / 'complete-report.md'
    report.write_text('\n'.join(lines), encoding='utf-8')
    evidence = {'quality': hashlib.sha256(quality_file.read_bytes()).hexdigest(),
                'api': hashlib.sha256(latency_file.read_bytes()).hexdigest(),
                'report': hashlib.sha256(report.read_bytes()).hexdigest()}
    for file in sorted((root / 'figures').glob('*')):
        evidence[file.relative_to(root).as_posix()] = hashlib.sha256(file.read_bytes()).hexdigest()
    (root / 'acceptance.json').write_text(json.dumps({'complete': True, 'scope': 'offline quality and API HTTP, not browser E2E',
              'createdAtUTC': datetime.datetime.now(datetime.timezone.utc).isoformat(), 'checksums': evidence}, indent=2), encoding='utf-8')
    print(report)


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--results', required=True)
    publish(parser.parse_args().results)
