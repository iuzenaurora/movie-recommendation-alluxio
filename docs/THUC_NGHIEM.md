# Giao thức và Kế hoạch Thực nghiệm (MovieLab)

Tài liệu này quy chuẩn toàn bộ phương pháp, giao thức đo lường và cách thiết kế thực nghiệm cho 3 bài toán chính của hệ thống:
1. **Hiệu năng tầng lưu trữ phân tán**: Đánh giá tác động của Alluxio cache đối với Spark trên nền HDFS (kịch bản H0, H1, H2).
2. **Chất lượng thuật toán gợi ý Offline**: Đánh giá xếp hạng (Precision, Recall, NDCG@10) và sai số dự đoán điểm sao (RMSE, MAE) cho 4 chiến lược gợi ý trên tập Test bất biến.
3. **Độ trễ và thông lượng phục vụ trực tuyến**: Benchmark tải HTTP API thực tế (FastAPI + MongoDB tmpfs).

Số liệu và biểu đồ thực tế chi tiết xem tại: [Báo cáo thực nghiệm ngày 20/10/2026](reports/2026-10-20/bao-cao-thuc-nghiem.md).

---

## 1. Giao thức đo tầng lưu trữ (Storage Benchmark: H0, H1, H2)

### 1.1. Các kịch bản đo đạc
- **H0 (HDFS Direct)**: Spark đọc dữ liệu trực tiếp từ HDFS (`hdfs://hdfs-storage:9000/...`), không đi qua tầng cache Alluxio.
- **H1 (Alluxio Cold Cache)**: Spark đọc dữ liệu qua Alluxio (`alluxio://alluxio-master:19998/...`) khi bộ nhớ cache Alluxio ban đầu ở trạng thái rỗng (0%).
- **H2 (Alluxio Warm Cache)**: Spark đọc dữ liệu qua Alluxio khi dữ liệu đã được nạp đầy vào bộ nhớ RAM của Alluxio Worker (100%).

### 1.2. Môi trường triển khai
- **Cấu hình 1 máy (Single-host)**: Toàn bộ HDFS, Alluxio và Spark chạy chung trên cùng một máy chủ vật lý qua Docker Compose.
- **Cấu hình 2 máy (Two-host qua Tailscale)**: Máy A chạy HDFS; Máy B chạy Spark và Alluxio. Hai máy kết nối qua mạng riêng ảo WireGuard của Tailscale.

### 1.3. Quy chuẩn thu thập dữ liệu
- Thực hiện **$\ge 5$ lượt đo lặp lại** cho mỗi kịch bản để loại trừ đột biến.
- Ghi nhận thời gian **Trung vị (Median)** và **Khoảng tứ phân vị (IQR)** qua 3 giai đoạn:
  1. *Đọc + persist + aggregate*: Nạp Parquet và tính tổng kiểm tra.
  2. *Fit ALS + materialize*: Huấn luyện Explicit ALS và tính vector.
  3. *Tổng đọc + fit*: Tổng thời gian hoàn thành pipeline.
- Đo lưu lượng I/O: Lượng bytes đọc từ tầng lưu trữ nền UFS (HDFS) và Alluxio Cache.
- **Lưu ý**: Giai đoạn huấn luyện ALS (Fit) chủ yếu tiêu tốn CPU và shuffle nội bộ của Spark, do đó Alluxio phát huy tác dụng chủ yếu ở giai đoạn đọc dữ liệu.

---

## 2. Giao thức đánh giá chất lượng gợi ý Offline (Recommendation Quality Protocol)

### 2.1. Thiết lập dữ liệu và mô hình
- Dùng mô hình huấn luyện ban đầu (`als-4033f5cd6298`, `servingRefit=false`). Tuyệt đối từ chối mô hình đã qua retrain/refit để tránh rò rỉ dữ liệu (data leakage).
- Kiểm tra tính toàn vẹn qua mã băm SHA-256 của model manifest, candidate IDs và toàn bộ tệp phân chia (`split/`).
- Tập Train, Validation và Test giữ bất biến theo quy tắc chia thời gian 80/10/10 của từng người dùng.

### 2.2. Mẫu thử nghiệm và mô phỏng lịch sử
- Lấy mẫu cố định **500 người dùng** từ tập Test theo thứ tự băm SHA-256 của chuỗi `seed:userId` (`seed=42`).
- Giữ cùng một nhóm người dùng (cohort) cho mọi kịch bản và mọi phương pháp gợi ý.
- **3 kịch bản mức độ lịch sử**:
  - `last-5`: Tối đa 5 đánh giá mới nhất (mô phỏng người dùng mới).
  - `last-20`: Tối đa 20 đánh giá mới nhất (mô phỏng người dùng trung bình).
  - `full`: Toàn bộ lịch sử đánh giá trước thời điểm Test (từ 18 đến 2.356 đánh giá/người).
- Người dùng có ít hơn ngưỡng quy định sẽ sử dụng toàn bộ lịch sử sẵn có của mình.

### 2.3. Ground Truth và Chính sách ứng viên
- **Ground Truth**: Tất cả các phim trong tập Test được người dùng đánh giá $\ge 4.0$ sao. Tuyệt đối không dùng điểm Test để tính fold-in hay xác định thể loại.
- **Candidate Policy**: Áp dụng cùng tập **10.507 phim ứng viên** (phim có $\ge 100$ lượt đánh giá trong Train) cho cả 4 phương pháp, chỉ loại bỏ các phim đã có trong lịch sử được cung cấp.
- **Phương pháp Phổ biến (Baseline)**: Xếp hạng theo số lượt đánh giá trong tập Train; không dùng toàn bộ dataset để tránh rò rỉ thông tin.

### 2.4. Chỉ số đánh giá và kiểm định thống kê
- **Precision@10**: Chia cho $K=10$ kể cả khi danh sách bị ngắn.
- **Recall@10**: Giữ toàn bộ positive test trong mẫu số, kể cả phim không có vector hoặc bị bộ lọc thể loại loại bỏ.
- **NDCG@10**: Đánh giá thứ tự xếp hạng ưu tiên với binary relevance (nhị phân).
- **RMSE & MAE**: Tính trên các cặp người dùng - phim có dự đoán điểm sao của thuật toán ALS Fold-in, so sánh trực tiếp với baseline điểm trung bình của phim (Movie Mean Baseline).
- **Kiểm định Bootstrap ghép cặp**: Chạy 2.000 lần lấy mẫu lại (seed cố định) để tính khoảng tin cậy 95% (95% CI) cho độ chênh lệch $\Delta\text{NDCG}$ giữa các phương pháp.

---

## 3. Giao thức đo tải và độ trễ HTTP API (API Benchmark Protocol)

### 3.1. Thiết lập môi trường đo riêng biệt
- Sử dụng cấu hình `compose.evaluation.yaml` khởi tạo stack `movielab-evaluation` độc lập.
- MongoDB chạy trên `tmpfs` (RAM), không ghi đĩa và tách biệt hoàn toàn khỏi database sản phẩm.
- API giới hạn tài nguyên: 1 tiến trình worker, 1.00 GiB RAM, 1 luồng BLAS.
- Driver kiểm tra endpoint `/health` xác nhận đúng model và database benchmark trước khi nạp dữ liệu thử.

### 3.2. Kịch bản đo tải
- Sử dụng 50 hồ sơ người dùng ngẫu nhiên ở kịch bản `last-20`.
- Chạy **1.200 request HTTP thật** (50 requests $\times$ 4 phương pháp $\times$ 3 lượt lặp gộp) ở 2 mức đồng thời: **concurrency = 1** và **concurrency = 4**.
- Thứ tự các phương pháp gợi ý được xáo trộn ngẫu nhiên với seed cố định qua từng lượt để tránh thiên lệch thứ tự.
- Đo lường thời gian đáp ứng client round-trip:
  - **p50, p95 (ms)**: Bao gồm giao tiếp HTTP, đọc MongoDB, giải Fold-in trong RAM, xếp hạng, tuần tự hóa JSON và kiểm tra phản hồi ở client.
  - **Throughput (RPS)**: Số lượng request thành công chia cho tổng thời gian thực hiện.
  - **Tỷ lệ lỗi (Error Rate)**: Đếm riêng; có lỗi thì không công nhận bài đo hoàn thành.

---

## 4. Tự động hóa và Tái lập Thực nghiệm

Để tự động hóa toàn bộ quá trình đánh giá chất lượng gợi ý và đo tải API từ đầu đến cuối, chạy script:

```powershell
.\scripts\run_report_experiments.ps1
```

Script sẽ tự động:
1. Xây dựng môi trường đánh giá offline.
2. Đánh giá chất lượng 4 phương pháp gợi ý trên 500 người dùng với 3 mức lịch sử.
3. Khởi động Docker stack đo tải API riêng biệt.
4. Thực hiện 1.200 request HTTP đo đạc.
5. Kiểm tra đối chiếu tính toàn vẹn (Serving Parity) giữa API và script offline.
6. Vẽ biểu đồ (`api-latency.png`, `ndcg.png`) và xuất báo cáo tổng hợp Markdown/CSV vào thư mục `experiments/recommendation-evaluation/<run>/`.
7. Dừng và giải phóng stack đo riêng biệt.
