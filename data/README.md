# Dữ liệu

| Thư mục | Nội dung |
|---|---|
| `raw/ml-32m/` | CSV MovieLens gốc; links.csv ánh xạ IMDb |
| `processed/ml32m-full-v1/` | Dataset Parquet gốc và manifest |
| `processed/retrain-<UUID>/` | Dataset mới được worker tạo từ snapshot ứng dụng |

Các thư mục có thể chưa tồn tại trong bản clone. CSV/Parquet không nằm trong Git. Không xóa dataset gốc sau khi có retrain: worker tiếp tục ghép snapshot đầy đủ với dataset gốc, không cộng dồn mù qua các bản retrain.

Manifest chứa phiên bản, thống kê, split và checksum. Bản HDFS dùng cho compute phải trùng với manifest local. Snapshot nằm ở `runtime/`; MongoDB/SQLite nằm trong Docker volumes, không nằm trong data/.

Cách chuẩn bị: [notebook](../notebooks/README.md). Cách đưa lên HDFS: [hai máy](../twohost/README.md).
