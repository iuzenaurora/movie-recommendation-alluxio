# Hạ tầng Docker

Cấu hình hạ tầng dùng để dựng môi trường Hadoop, Spark và Alluxio. Ứng dụng thường ngày chạy bằng [launcher](../MovieLab.pyw); hướng dẫn cấu hình hai máy ở [twohost/README.md](../twohost/README.md).

## Thành phần

| Đường dẫn | Vai trò |
|---|---|
| `hadoop/Dockerfile` và file cùng thư mục | Image Hadoop cho NameNode/DataNode |
| `spark/Dockerfile` | Spark 3.5.6, Java 11, NumPy và Alluxio client 2.9.5 |
| `alluxio-master.sh` | Khởi tạo journal mới một lần và chạy Master |
| `../compose.yaml` | App, image build và cấu hình hạ tầng một máy |
| `../twohost/compose.storage.yaml` | HDFS máy A |
| `../twohost/compose.compute.yaml` | Alluxio/Spark máy B |

Không chạy đồng thời Alluxio một máy và Alluxio hai máy nếu dùng cùng cổng 19999. Worker hiện sử dụng cấu hình hai máy cho benchmark/retrain.

## Build image phục vụ hai máy

Chạy tại gốc dự án trên máy B:

```powershell
docker compose --profile jobs build spark
docker compose --profile infra build namenode
```

Tên image phụ thuộc Compose project. Mẫu `.env.example` dùng `COMPOSE_PROJECT_NAME=phatmovies`, tương ứng `phatmovies-spark:latest` và `phatmovies-namenode:latest`.

## Lưu trữ và tài nguyên

HDFS dùng named volumes NameNode/DataNode; Alluxio dùng journal volume và tmpfs cache. Spark mount source/dataset/model từ project. Không format lại NameNode hoặc xóa volumes để khắc phục lỗi khởi động thông thường.

Compute hiện cấu hình heap Spark 2g, container Spark 3584m, cache Alluxio 512MB. Đây là cấu hình lab hiện tại, không phải bảo đảm cho mọi quy mô dữ liệu. Theo dõi RAM và dung lượng khi tạo phiên bản retrain.

Lustre chưa được triển khai trong các Compose này. Xem [docs/LUSTRE_LINUX.md](../docs/LUSTRE_LINUX.md) nếu yêu cầu đồ án bắt buộc Lustre thật.
