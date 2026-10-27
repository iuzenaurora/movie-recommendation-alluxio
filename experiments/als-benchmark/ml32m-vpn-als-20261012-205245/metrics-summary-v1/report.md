# Tổng hợp thực nghiệm Alluxio

Thư mục: ml32m-vpn-als-20261012-205245
Dataset: ml-5b41ebbd9d02; train: 25,520,897 dòng.
Số lần mỗi trường hợp: 5.

## Thời gian trung vị / IQR (giây)

| Giai đoạn | H0 | H1 | H2 | Tăng tốc H2/H0 | Giảm thời gian H2 |
|---|---:|---:|---:|---:|---:|
| Đọc + persist + aggregate | 50.98 / 3.53 | 90.80 / 8.25 | 30.84 / 3.31 | 1.653× | 39.51% |
| Fit + materialize vector | 268.34 / 9.71 | 270.11 / 4.19 | 264.50 / 8.18 | 1.015× | 1.43% |
| Tổng đọc + fit | 319.32 / 6.77 | 363.55 / 10.68 | 298.94 / 11.75 | 1.068× | 6.38% |

## Lượng đọc phát sinh từng lần (MiB xấp xỉ)

| Nhãn | Lần | Cache đầu (%) | Cache cuối (%) | Đọc cache | RPC | UFS |
|---|---:|---:|---:|---:|---:|---:|
| H1 | 1 | 0.00 | 100.00 | 32.870 | 247.660 | 464.740 |
| H1 | 2 | 0.00 | 100.00 | 32.870 | 167.880 | 389.700 |
| H1 | 3 | 0.00 | 100.00 | 32.870 | 258.120 | 479.850 |
| H1 | 4 | 0.00 | 100.00 | 32.870 | 263.020 | 492.580 |
| H1 | 5 | 0.00 | 100.00 | 32.870 | 248.450 | 480.650 |
| H2 | 1 | 100.00 | 100.00 | 230.150 | 230.150 | 0.000 |
| H2 | 2 | 100.00 | 100.00 | 197.260 | 197.260 | 0.000 |
| H2 | 3 | 100.00 | 100.00 | 263.020 | 262.010 | 0.000 |
| H2 | 4 | 100.00 | 100.00 | 263.020 | 263.010 | 0.000 |
| H2 | 5 | 100.00 | 100.00 | 263.020 | 262.020 | 0.000 |

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
