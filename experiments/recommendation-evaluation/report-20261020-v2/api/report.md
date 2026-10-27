# Benchmark API gợi ý

Đo HTTP thật với FastAPI và MongoDB tách biệt. Không bao gồm Laravel hoặc trình duyệt.

| Đồng thời | Phương pháp | Request | Lỗi | p50 (ms) | p95 (ms) | Thành công/giây |
|---:|---|---:|---:|---:|---:|---:|
| 1 | als_fold_in | 150 | 0 | 74.43 | 167.68 | 11.28 |
| 1 | hybrid | 150 | 0 | 154.02 | 448.21 | 5.16 |
| 1 | genre | 150 | 0 | 99.24 | 170.71 | 9.20 |
| 1 | popularity | 150 | 0 | 96.21 | 171.76 | 9.21 |
| 4 | als_fold_in | 150 | 0 | 294.00 | 775.49 | 10.59 |
| 4 | hybrid | 150 | 0 | 537.50 | 1185.53 | 6.15 |
| 4 | genre | 150 | 0 | 414.55 | 848.09 | 8.53 |
| 4 | popularity | 150 | 0 | 430.34 | 981.01 | 7.93 |

## Giới hạn

- API-level benchmark, not end-to-end browser latency.
- One local Docker host and one API process; do not generalize as production capacity.
- Quality and latency use matching history scenarios but different popularity sources as documented.
- Raw latencies are machine/load dependent; success RPS is achieved throughput, not maximum capacity.
