# Tổng hợp thực nghiệm Alluxio

Thư mục: auto-3cbd84b1-1989-4ab5-8dff-7f3a6dca9bbd
Dataset: ml-5b41ebbd9d02; train: 25,520,897 dòng.
Số lần mỗi trường hợp: 5.

## Thời gian trung vị / IQR (giây)

| Giai đoạn | H0 | H1 | H2 | Tăng tốc H2/H0 | Giảm thời gian H2 |
|---|---:|---:|---:|---:|---:|
| Đọc + persist + aggregate | 49.03 / 5.61 | 79.77 / 1.87 | 31.90 / 2.10 | 1.537× | 34.93% |
| Fit + materialize vector | 299.05 / 4.30 | 297.83 / 1.34 | 296.51 / 0.89 | 1.009× | 0.85% |
| Tổng đọc + fit | 349.76 / 3.40 | 377.60 / 3.71 | 330.23 / 3.42 | 1.059× | 5.59% |

## Lượng đọc phát sinh từng lần (MiB xấp xỉ)

| Nhãn | Lần | Cache đầu (%) | Cache cuối (%) | Đọc cache | RPC | UFS |
|---|---:|---:|---:|---:|---:|---:|
| H1 | 1 | 0.00 | 100.00 | 40.960 | 256.000 | 468.400 |
| H1 | 2 | 0.00 | 100.00 | 40.960 | 235.520 | 426.710 |
| H1 | 3 | 0.00 | 100.00 | 30.720 | 266.240 | 481.410 |
| H1 | 4 | 0.00 | 100.00 | 30.720 | 256.000 | 485.400 |
| H1 | 5 | 0.00 | 100.00 | 30.720 | 256.000 | 486.890 |
| H2 | 1 | 100.00 | 100.00 | 256.000 | 266.240 | 0.000 |
| H2 | 2 | 100.00 | 100.00 | 256.000 | 266.240 | 0.000 |
| H2 | 3 | 100.00 | 100.00 | 266.240 | 256.000 | 0.000 |
| H2 | 4 | 100.00 | 100.00 | 266.240 | 266.240 | 0.000 |
| H2 | 5 | 100.00 | 100.00 | 266.240 | 266.240 | 0.000 |

## Bằng chứng được ghi nhận

- H2 có lượng đọc cache tăng: 5/5 lần.
- H2 không tăng UFS ở độ chính xác hiển thị: 5/5 lần.
- H1 có lượng đọc UFS tăng: 5/5 lần.
- Các số đếm trên mô tả snapshot; không kết luận cache hit chính xác 100%.

## Lưu ý và cảnh báo

- Không có cảnh báo từ các kiểm tra đã thực hiện.

Giới hạn đo:

- Snapshots are cluster-wide and heartbeat-delayed; deltas may include other activity.
- Zero displayed delta is not proof of exactly zero bytes or an exact 100% cache hit rate.
- Rounding bounds account only for display precision, not collection lag.
- BytesReadRemote is client-worker RPC traffic, not equivalent to HDFS reads.
- Cache and RPC counters overlap; do not sum them as disjoint reads.
- UFS counter does not measure direct H0 reads.
- ALS read timer includes persist and aggregate, not pure I/O.
- Warm preload and Spark startup are excluded; OS page cache is uncontrolled.
