# HDFS máy A, Alluxio và Spark máy B

Cấu hình này dùng hai máy vật lý kết nối LAN hoặc Tailscale. Spark vẫn chạy local[2] trên máy B; đây không phải cụm Spark nhiều máy.

| Máy | Dịch vụ |
|---|---|
| A | HDFS NameNode và DataNode |
| B | Alluxio Master/Worker, Spark, MongoDB, API, web và worker điều phối |

Website chạy theo [hướng dẫn chung](../HUONG_DAN_CHAY.md). Các bước dưới đây dành cho thiết lập hạ tầng lần đầu hoặc đổi máy A.

## 1. File cấu hình

| File | Vai trò |
|---|---|
| `compose.storage.yaml` | HDFS trên A |
| `compose.compute.yaml` | Alluxio và Spark trên B |
| `.env.storage.example` | Mẫu cấu hình A |
| `.env.compute.example` | Mẫu cấu hình B |
| `hadoop/server/` | XML cấu hình HDFS server |
| `hadoop/client/` | XML cho Alluxio đọc HDFS |
| `check_storage.py` | Đọc manifest và byte Parquet thật qua HDFS/Alluxio |
| `dc.ps1` | Wrapper Compose tùy chọn |
| `verify-local-dataset.ps1` | Kiểm tra checksum local tùy chọn |

Các lệnh hướng dẫn dùng Docker Compose trực tiếp để không phụ thuộc execution policy của PowerShell. Chạy từ thư mục gốc dự án.

## 2. Chuẩn bị mạng và env

Bật Docker Desktop trên cả hai máy. Lấy địa chỉ máy A có thể truy cập từ B; nếu dùng Tailscale, dùng địa chỉ Tailscale của A.

Tạo file env **nếu chưa tồn tại**:

Trên A:

```powershell
if (!(Test-Path .env)) { Copy-Item .env.example .env }
if (!(Test-Path twohost/.env.storage)) { Copy-Item twohost/.env.storage.example twohost/.env.storage }
```

Trên B:

```powershell
if (!(Test-Path .env)) { Copy-Item .env.example .env }
if (!(Test-Path twohost/.env.compute)) { Copy-Item twohost/.env.compute.example twohost/.env.compute }
```

Sửa `STORAGE_IP` trong **cả hai** file env thành cùng địa chỉ máy A. Ví dụ địa chỉ A là `192.168.1.10`; khi đổi máy cần thay bằng địa chỉ mới. Không ghi IP máy B vào STORAGE_IP.

Giữ các project name đang sử dụng để Compose tiếp tục gắn đúng volumes:
- App: `phatmovies`.
- HDFS: `phatmovies-storage-lan`.
- Compute: `phatmovies-compute-lan`.

## 3. Khởi động HDFS trên máy A

A cần image Hadoop `phatmovies-namenode:latest`. Có thể build tại gốc source trên A:

```powershell
docker compose --profile infra build namenode
docker compose --env-file twohost/.env.storage -f twohost/compose.storage.yaml up -d --wait
docker compose --env-file twohost/.env.storage -f twohost/compose.storage.yaml ps
```

Nếu project name/image khác, đặt HADOOP_IMAGE đúng trong env storage. Cách khác là chuyển image từ B bằng Docker save/load; việc này chỉ chuyển image, không chuyển dữ liệu HDFS trong volumes.

HDFS UI trên A: http://localhost:9870. DataNode UI: http://localhost:9864.

Từ B kiểm tra các cổng, thay IP bằng địa chỉ A thực tế:

```powershell
$A_IP = "192.168.1.10"
Test-NetConnection $A_IP -Port 9000
Test-NetConnection $A_IP -Port 9866
Test-NetConnection $A_IP -Port 9867
```

Cần TcpTestSucceeded=True cho cả ba. Tailscale ping thành công chỉ chứng minh kết nối mạng, không chứng minh HDFS đang mở cổng. Nếu lỗi, kiểm tra container, STORAGE_IP và firewall A cho kết nối từ B.

## 4. Khởi động compute trên máy B

```powershell
docker compose --profile jobs build spark
docker compose --env-file twohost/.env.compute -f twohost/compose.compute.yaml up -d alluxio-master alluxio-worker
docker compose --env-file twohost/.env.compute -f twohost/compose.compute.yaml exec -T alluxio-master alluxio fsadmin report capacity
```

Phải thấy worker. UI Alluxio trên B: http://localhost:19999.

Nếu không thấy:

```powershell
docker compose --env-file twohost/.env.compute -f twohost/compose.compute.yaml ps -a
docker compose --env-file twohost/.env.compute -f twohost/compose.compute.yaml logs --tail 80 alluxio-worker alluxio-master
```

Không bật nhóm infra một máy trong Compose gốc cùng lúc: nhóm này có Alluxio khác và có thể tranh cổng.

## 5. Đưa dataset ban đầu lên HDFS

B cần thư mục local `data/processed/ml32m-full-v1/` đầy đủ và đúng manifest.

Kiểm tra local trước:

```powershell
docker compose --profile jobs run --rm spark python3 scripts/verify_dataset.py --data data/processed/ml32m-full-v1
```

**Chỉ upload khi đích HDFS chưa có dataset**:

```powershell
docker compose --env-file twohost/.env.compute -f twohost/compose.compute.yaml --profile jobs run --rm spark bash scripts/spark.sh scripts/upload_dataset.py --data data/processed/ml32m-full-v1 --destination hdfs://hdfs-storage:9000/datasets/ml32m-full-v1
```

Uploader kiểm tra checksum local, ghi staging, kiểm tra kích thước remote và rename sang đích; từ chối ghi đè đích đã tồn tại. Nếu dữ liệu đã có, chuyển sang kiểm tra thay vì upload lại.

## 6. Kiểm tra đường đọc thật

```powershell
docker compose --env-file twohost/.env.compute -f twohost/compose.compute.yaml --profile jobs run --rm spark bash scripts/spark.sh twohost/check_storage.py
```

Preflight đối chiếu manifest và đọc byte Parquet qua cả HDFS lẫn Alluxio. Chỉ có listing hoặc kết nối TCP thành công chưa đủ xác nhận dữ liệu hợp lệ.

## 7. Sử dụng tự động trên web

Mở `MovieLab.pyw` trên B và đăng nhập admin tại http://localhost:8080/admin/jobs.

- Lịch benchmark/retrain và tự kích hoạt model cấu hình trên web.
- Worker tự kiểm tra compute, snapshot, merge, upload và train.
- Không cần chạy lại các lệnh setup ở trên mỗi lần đánh giá.
- Benchmark mặc định dùng dataset gốc; retrain tạo dataset/model phiên bản mới.
- Khi đổi A, sửa hai env tương ứng và kiểm tra lại dataset trên A mới trước khi chạy job.

H0: Spark đọc HDFS trực tiếp. H1: đọc Alluxio khi cache ban đầu rỗng. H2: đọc Alluxio khi train đã nạp đầy cache. Mỗi nhãn 5 lượt; worker chỉ công bố báo cáo mới sau khi tổng hợp thành công.

Không suy diễn H2 luôn nhanh hơn. Kết quả phụ thuộc mạng, tài nguyên và OS page cache. Báo cáo trên A cũ không đại diện cho A mới.

## 8. Dừng và giữ dữ liệu

Chờ job hoàn tất và dừng worker/app theo hướng dẫn chung trước khi dừng hạ tầng.

Trên B:

```powershell
docker compose --env-file twohost/.env.compute -f twohost/compose.compute.yaml stop
```

Trên A:

```powershell
docker compose --env-file twohost/.env.storage -f twohost/compose.storage.yaml stop
```

Giữ named volumes HDFS và journal. Không format lại NameNode, xóa volume hoặc giải phóng cache khi chưa xác nhận dữ liệu gốc nằm bền vững trên HDFS.
