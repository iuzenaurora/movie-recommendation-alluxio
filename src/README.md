# Xử lý dữ liệu và mô hình

Mã Spark/Python dùng chung cho notebook, worker và kiểm thử.

| File | Chức năng |
|---|---|
| `etl.py` | Làm sạch MovieLens, xử lý trùng và chia train/validation/test |
| `train.py` | Chọn cấu hình ALS, đánh giá baseline/ranking và xuất gói model |
| `retrain.py` | Serving refit với cấu hình và chính sách ứng viên của model cha |
| `merge_snapshot.py` | Ghép snapshot ứng dụng với dataset gốc, giữ ánh xạ ID ổn định |
| `recommend_core.py` | Fold-in, xếp hạng, lọc phim đã đánh giá và fallback |
| `benchmark_als.py` | Workload ALS cố định cho H0/H1/H2 |
| `benchmark.py` | Workload scan/group riêng, không thay thế benchmark ALS |
| `file_checksums.py` | Hàm checksum dùng chung |

Worker gọi Spark qua `scripts/spark.sh`. Đầu vào là Parquet + manifest; đầu ra train là gói model có checksum. Không sửa trực tiếp file bên trong `models/current` để thay phiên bản.

Quy trình thường ngày thực hiện từ [trang điều hành](http://localhost:8080/admin/jobs). Cách tạo dữ liệu/model ban đầu: [notebook](../notebooks/README.md). Cách kiểm chứng: [kiểm thử](../docs/KIEM_THU.md).
