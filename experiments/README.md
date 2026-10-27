# Kết Quả Thực Nghiệm (MovieLab Experimental Results)

Thư mục này lưu trữ các tệp số liệu, tệp nhật ký (logs), biểu đồ trực quan hóa và bảng tổng hợp của các đợt thực nghiệm trong dự án. Toàn bộ kết quả thực nghiệm được theo dõi bằng mã băm kiểm định SHA-256 và được phép đồng bộ lên kho lưu trữ GitHub để phục vụ thẩm định và báo cáo.

---

## 1. Cấu trúc các thư mục kết quả

| Thư mục | Nội dung và Bằng chứng thực nghiệm |
|---|---|
| [`als-benchmark/ml32m-als-20261011-121602/`](als-benchmark/ml32m-als-20261011-121602/) | **Benchmark ALS trên 1 máy (Single-host)**: Đo đạc H0/H1/H2 với 5 lần lặp trên tập MovieLens 32M (25,5 triệu dòng train). Bảng `summary-als.csv` và `summary-als.json`. |
| [`als-benchmark/ml32m-vpn-als-20261012-205245/`](als-benchmark/ml32m-vpn-als-20261012-205245/) | **Benchmark ALS trên 2 máy (Two-host qua Tailscale VPN)**: Đo đạc H0/H1/H2 với 5 lần lặp. Báo cáo `metrics-summary-v1/report.md` chứng minh Alluxio Warm Cache giúp giảm 39.51% thời gian đọc và triệt tiêu 100% I/O mạng từ HDFS. |
| [`recommendation-evaluation/report-20261020-v2/`](recommendation-evaluation/report-20261020-v2/) | **Đánh giá chất lượng gợi ý & Benchmark HTTP API**: Đánh giá 4 phương pháp (ALS fold-in, Hybrid, Theo gu, Phổ biến) trên 500 người dùng với 3 mức lịch sử (`last-5`, `last-20`, `full`) và 1.200 request HTTP API đo đạc. Chứa `quality.csv`, `summary.json`, `per-user.jsonl`, `serving-parity.json`, và biểu đồ `figures/`. |
| [`results/`](results/) | **Benchmark tác vụ truy vấn cơ bản**: Đo đạc thời gian quét bảng (`scan`) và gom nhóm (`group`) qua H0, H1, H2 trên 1 máy cục bộ (`summary.csv`). |

---

## 2. Giao thức và Báo cáo tổng hợp

- Giao thức thực nghiệm chuẩn: [docs/THUC_NGHIEM.md](../docs/THUC_NGHIEM.md).
- Báo cáo kết quả tổng hợp chính thức: [docs/reports/2026-10-20/bao-cao-thuc-nghiem.md](../docs/reports/2026-10-20/bao-cao-thuc-nghiem.md).
- Kịch bản chạy lại thực nghiệm tự động: [scripts/run_report_experiments.ps1](../scripts/run_report_experiments.ps1).
