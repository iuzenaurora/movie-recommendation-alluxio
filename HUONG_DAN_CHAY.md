# Hướng Dẫn Vận Hành & Thực Nghiệm MovieLab

Tài liệu này hướng dẫn chi tiết cách thiết lập, vận hành và tái lập các thực nghiệm trong hệ thống MovieLab. Tất cả các lệnh dưới đây được thực hiện trên PowerShell tại thư mục gốc của dự án trên máy B (máy tính toán), trừ các phần ghi rõ thực hiện trên máy A (máy lưu trữ HDFS).

---

## 1. Yêu Cầu Môi Trường Tiên Quyết

- **Hệ điều hành**: Windows 10/11 (khuyến nghị có WSL2) hoặc Linux (Ubuntu 20.04/22.04).
- **Phần mềm bắt buộc**:
  - [Docker Desktop](https://www.docker.com/products/docker-desktop/) đang hoạt động ở chế độ Linux Containers.
  - [Python 3.10+](https://www.python.org/) trên máy chủ để chạy launcher và script điều phối (không cần cài đặt PHP hay Java trực tiếp trên Windows vì toàn bộ dịch vụ đã được container hóa).
- **Cổng mạng cần giải phóng**: `8080` (Web), `8000` (FastAPI), `27018` (MongoDB Compass), `19999` (Alluxio Web UI).

Khởi tạo môi trường tại thư mục dự án:
```powershell
# Di chuyển vào thư mục dự án
cd C:\BigData\movie-recommendation-alluxio

# Kiểm tra phiên bản công cụ
docker version
docker compose version
python --version

# Sao chép tệp cấu hình môi trường mẫu nếu chưa có
if (!(Test-Path .env)) { Copy-Item .env.example .env }
docker compose config --quiet

# Cài đặt thư viện Python bổ trợ cho việc chạy script
pip install -r requirements.txt
```
> **Lưu ý quan trọng**: Tuyệt đối không thay đổi `COMPOSE_PROJECT_NAME` trong file `.env` vì tên project quyết định việc ánh xạ đúng Docker volumes chứa tài khoản SQLite và cơ sở dữ liệu MongoDB.

---

## 2. Hướng Dẫn Cho Bản Clone Mới (Chưa Có Dữ Liệu Nặng)

Khi bạn clone mã nguồn từ GitHub về máy tính mới, do chính sách bảo mật và dung lượng, các tệp dataset lớn (`data/raw/`, `data/processed/`), ma trận vector (`factors.npz`) và credentials (`runtime/`) **không được lưu trữ trên Git**.

Bạn hãy lựa chọn **1 trong 3 phương án** dưới đây tùy theo nhu cầu và tài nguyên máy tính:

```
┌────────────────────────────────────────────────────────────────────────┐
│                        LỰA CHỌN PHƯƠNG ÁN KHỞI TẠO                     │
│                                                                        │
│  [PHƯƠNG ÁN 1] Dùng Dataset Nhỏ (ml-latest-small ~1MB)               │
│  ➜ Phù hợp: Chạy thử nghiệm nhanh, kiểm thử chức năng trên 1 máy laptop.│
│                                                                        │
│  [PHƯƠNG ÁN 2] Huấn luyện Dataset Lớn 32M trên Google Colab          │
│  ➜ Phù hợp: Máy cá nhân yếu nhưng muốn tự xử lý tập MovieLens 32M.     │
│                                                                        │
│  [PHƯƠNG ÁN 3] Sử dụng Gói Model & Dữ liệu có sẵn từ Google Drive     │
│  ➜ Phù hợp: Thành viên trong nhóm muốn chạy ngay đầy đủ đồ án 32M.     │
└────────────────────────────────────────────────────────────────────────┘
```

---

### PHƯƠNG ÁN 1: Chạy Thử Nghiệm Nhẹ với Dataset Nhỏ (`ml-latest-small`)
*Dành cho người dùng muốn chạy kiểm thử trọn vẹn toàn bộ pipeline mà không tốn dung lượng (chỉ tải ~1MB, xử lý xong trong 2–3 phút).*

#### Bước 1.1: Tải tự động dataset nhỏ
Chạy script tải chính thức bộ MovieLens Small từ GroupLens:
```powershell
python scripts/download.py --dataset small
```
*Dữ liệu gốc giải nén tại `data/raw/ml-latest-small/` gồm 100.000 đánh giá.*

#### Bước 1.2: Tiền xử lý dữ liệu và chia tập bằng PySpark
Chạy container Spark thông qua Docker Compose (không cần cài Java hay Spark trên Windows):
```powershell
docker compose --profile jobs run --rm spark python3 src/etl.py --raw data/raw/ml-latest-small --out data/processed/demo-v1 --version demo-v1
```
*Spark sẽ lọc, khử trùng lặp và chia tập theo thời gian 80/10/10, xuất ra Parquet tại `data/processed/demo-v1/`.*

#### Bước 1.3: Huấn luyện Explicit ALS bằng Spark MLlib
```powershell
docker compose --profile jobs run --rm spark python3 src/train.py --data data/processed/demo-v1 --out models/als-demo-v1 --rank 10 --maxIter 5 --reg 0.1
```
*Mô hình sẽ học các vector người dùng và phim, xuất ra thư mục `models/als-demo-v1/` (kích thước chỉ ~1.5 MB).*

#### Bước 1.4: Kích hoạt Model làm gói phục vụ (`models/current/`)
```powershell
if (!(Test-Path models/current)) { New-Item -ItemType Directory -Path models/current }
Copy-Item -Recurse -Force models/als-demo-v1/* models/current/
```

#### Bước 1.5: Khởi chạy ứng dụng và kiểm thử
```powershell
# Khởi động dịch vụ web, api và worker nền
python scripts/start_app.py --build

# Chạy benchmark kiểm tra độ trễ và thông lượng HTTP API thật
python scripts/benchmark_recommendation_api.py --url http://localhost:8000
```
*Sau khi chạy, truy cập **http://localhost:8080** để trải nghiệm ứng dụng với mô hình demo.*

---

### PHƯƠNG ÁN 2: Huấn Luyện Dữ Liệu Lớn MovieLens 32M trên Google Colab
*Dành cho trường hợp muốn làm việc với toàn bộ 32 triệu dòng đánh giá nhưng máy tính cá nhân không đủ RAM hoặc CPU.*

Trong thư mục [`notebooks/`](notebooks/README.md) của dự án đã chuẩn bị sẵn 4 sổ tay Jupyter hoàn chỉnh:

1. Mở **Google Colab** và mở lần lượt các notebook:
   - `00_ChuanBi_Colab.ipynb`: Thiết lập môi trường PySpark trên Google Colab.
   - `01_KhaoSat_MovieLens.ipynb`: Khảo sát phân bố thống kê EDA trên tập MovieLens 32M.
   - `02_TienXuLy.ipynb`: PySpark ETL làm sạch 32M dòng và xuất ra tập Parquet nén.
   - `03_HuanLuyen_ALS.ipynb`: Huấn luyện Explicit ALS (`rank=20, regParam=0.1, maxIter=10`) trên tài nguyên Colab, xuất gói model gồm `factors.npz`, `movies.json`, `manifest.json`.
2. **Chuyển gói model về máy cá nhân**:
   - Tải gói model từ Colab về (~25 MB).
   - Giải nén toàn bộ các tệp vào thư mục `models/current/` trên máy của bạn.
   - Khởi động hệ thống: `python scripts/start_app.py`.
   - Web và API sẽ ngay lập tức phục vụ các đề xuất chất lượng cao từ mô hình 32M!

> **Giải thích khoa học**: Colab được sử dụng để tận dụng tài nguyên mạnh của Cloud phục vụ **tiền xử lý (ETL) và huấn luyện (Training)**. Việc đo đạc hiệu năng lưu trữ phân tán Alluxio vs HDFS (H0/H1/H2) bắt buộc phải thực hiện trên môi trường Docker/mạng thực tế (1 máy hoặc 2 máy), vì trên 1 máy ảo đơn lẻ như Colab không có hiện tượng nghẽn băng thông mạng giữa nút tính toán và nút lưu trữ.

---

### PHƯƠNG ÁN 3: Sử Dụng Gói Dữ Liệu & Model Đã Xử Lý Sẵn (Từ Google Drive)
*Khuyên dùng cho các thành viên trong nhóm hoặc giảng viên chấm bài muốn kiểm tra nhanh toàn bộ hệ thống MovieLens 32M mà không mất thời gian huấn luyện lại.*

1. **Tải các gói tài nguyên từ link Google Drive của nhóm**:
   - Gói model: Thư mục `current/` (chứa `factors.npz`, `movies.json`, `candidate_movie_ids.json`, `manifest.json`).
   - Gói dataset Parquet: Thư mục `data/processed/ml32m-full-v1/`.
   - Tệp liên kết IMDb: `data/raw/ml-32m/links.csv` (phục vụ lấy ảnh poster phim).
2. **Đặt vào đúng cấu trúc thư mục của dự án**:
   ```text
   movie-recommendation-alluxio/
   ├── models/current/                <-- Đặt các file model vào đây
   ├── data/
   │   ├── raw/ml-32m/links.csv       <-- Đặt file links.csv vào đây
   │   └── processed/ml32m-full-v1/   <-- Đặt thư mục Parquet vào đây
   ```
3. **Khởi động ngay hệ thống**:
   ```powershell
   python scripts/start_app.py --build
   ```
   Hệ thống sẽ nạp trực tiếp mô hình MovieLens 32M và sẵn sàng cho việc chấm điểm, gợi ý và điều hành tự động.

---

## 3. Khởi Chạy Và Vận Hành Chương Trình

### Cách 1: Khởi chạy bằng 1 cú nhấp chuột (Khuyên dùng trên Windows)
1. Bật **Docker Desktop** và chờ Docker Engine chuyển sang trạng thái sẵn sàng (Running).
2. Nhấp đúp vào tệp **`MovieLab.pyw`** ở thư mục gốc của dự án.
3. Cửa sổ launcher sẽ tự động khởi động các container (`web`, `api`, `mongo`), kích hoạt tiến trình Worker nền và tự động mở trình duyệt web.

### Cách 2: Khởi chạy bằng dòng lệnh
```powershell
# Chạy lần đầu hoặc sau khi chỉnh sửa mã nguồn
python scripts/start_app.py --build

# Các lần chạy bình thường tiếp theo
python scripts/start_app.py
```

---

## 4. Tài Khoản, Truy Cập & Kịch Bản Demo

### Các cổng dịch vụ:
| Địa chỉ truy cập | Dịch vụ | Ghi chú |
|---|---|---|
| **http://localhost:8080** | Giao diện Web MovieLab | Đăng ký tài khoản mới tại `/register` |
| **http://localhost:8080/admin/jobs** | Trung tâm điều hành tự động | Đăng nhập tài khoản admin |
| **http://localhost:8080/admin/benchmark** | Xem kết quả benchmark H0/H1/H2 | Truy cập qua tài khoản admin |
| **http://localhost:8080/admin/eda** | Thống kê dữ liệu MovieLens 32M | Truy cập qua tài khoản admin |
| **http://localhost:8000/health** | Kiểm tra trạng thái API gợi ý | Công khai (Public) |
| **http://localhost:19999** | Giao diện Alluxio Web UI | Quản lý bộ nhớ đệm RAM |
| **`mongodb://127.0.0.1:27018`** | Kết nối trực tiếp MongoDB Compass | Database: `movies` |

*Thông tin tài khoản Admin ban đầu được tạo tự động tại tệp `runtime/admin-credentials.json`.*

### Kịch bản Demo chuẩn cho Giảng viên:
1. **Trải nghiệm người dùng & 4 chiến lược gợi ý**:
   - Đăng ký một tài khoản mới tinh $\rightarrow$ Hệ thống áp dụng **Gợi ý Phổ biến (Baseline)** giải quyết Cold-start.
   - Tìm kiếm và chấm điểm 3 – 4 bộ phim cụ thể (ví dụ phim Hoạt hình mức 5 sao, phim Hành động mức 1 sao).
   - Vào mục **Dành cho bạn** và chuyển qua lại giữa 4 tab để so sánh:
     - **ALS fold-in (Thuần)**: Tích vô hướng vector người dùng và vector phim.
     - **Hybrid (ALS + gu thể loại)**: Điểm ALS kết hợp độ phù hợp thể loại (+0.75 * Affinity).
     - **Theo gu (Lọc thể loại + ALS)**: Lọc trước theo thể loại yêu thích nhất rồi xếp hạng bằng ALS.
     - **Phổ biến**: Baseline cộng đồng.
2. **Chứng minh tốc độ đáp ứng tức thời (<100ms)**:
   - Cuộn xuống dưới danh sách phim, bấm vào dòng: **`Vì sao có những gợi ý này? · Xem cách tính`**.
   - Chỉ cho Thầy/Cô thấy dòng: **Thời gian xử lý tại API** (chỉ mất ~50ms – 100ms nhờ cơ chế giải Fold-in trong RAM bằng NumPy).
3. **Trung tâm điều hành Big Data & Tự động hóa**:
   - Đăng nhập Admin vào **http://localhost:8080/admin/jobs**.
   - Cho xem **Bộ đếm đánh giá mới (Pending ratings count)**.
   - Mở mục *Chạy ngay và quản lý phiên bản* $\rightarrow$ Bấm *Chụp dữ liệu đánh giá (Snapshot)* để chứng minh luồng trích xuất dữ liệu hoạt động trực tiếp.
4. **Bằng chứng hiệu năng Alluxio vs HDFS**:
   - Mở **http://localhost:8080/admin/benchmark**: Chỉ vào kết quả **Alluxio Warm Cache (H2) giảm 39.51% thời gian đọc của Spark** trên cụm 2 máy qua Tailscale VPN.

---

## 5. Thiết Lập Tự Động Hóa & Huấn Luyện Lại (Auto-Retrain)

Tại **Admin → Điều hành → Thiết lập tự động**:

| Thiết lập | Giá trị mặc định | Ý nghĩa |
|---|---:|---|
| **Benchmark mỗi (giờ)** | 24 | Định kỳ đo đạc H0/H1/H2 (5 lượt lặp mỗi nhãn) |
| **Retrain sau số thay đổi đánh giá** | 20 | Ngưỡng tích lũy thêm/sửa/xóa điểm trên web để kích hoạt retrain |
| **Tự cập nhật model sau retrain** | Bật | Tự động xác minh checksum, đổi phiên bản model và kiểm tra API |

- **Cơ chế bảo vệ (Pre-flight Check)**: Trước khi chạy tác vụ nặng phân tán (Retrain/Benchmark), Worker sẽ kiểm tra kết nối với Máy A (HDFS qua Tailscale). Nếu Máy A chưa kết nối, tác vụ sẽ dừng an toàn và bảo toàn Model cũ, không làm gián đoạn người dùng web.
- Muốn kích hoạt tác vụ thủ công bất kỳ lúc nào: Mở mục **Chạy ngay và quản lý phiên bản** trên trang Admin.

---

## 6. Kiểm Tra Kết Nối Hạ Tầng Phân Tán 2 Máy (Tailscale / HDFS)

Khi thực nghiệm trên 2 máy vật lý (Máy A: HDFS; Máy B: Spark + Alluxio):
1. **Kiểm tra cổng mạng từ máy B sang máy A** (thay `100.118.141.95` bằng IP Tailscale thực tế của máy A):
   ```powershell
   $A_IP = "100.118.141.95"
   Test-NetConnection $A_IP -Port 9000   # NameNode RPC
   Test-NetConnection $A_IP -Port 9866   # DataNode Data
   Test-NetConnection $A_IP -Port 9867   # DataNode IPC
   ```
2. **Khởi động cụm Alluxio trên máy B**:
   ```powershell
   docker compose --env-file twohost/.env.compute -f twohost/compose.compute.yaml up -d alluxio-master alluxio-worker
   ```
3. **Chạy script kiểm tra đọc dữ liệu Parquet thực tế**:
   ```powershell
   docker compose --env-file twohost/.env.compute -f twohost/compose.compute.yaml --profile jobs run --rm spark bash scripts/spark.sh twohost/check_storage.py
   ```
   *Kết quả in ra `Storage preflight passed.` nghĩa là kết nối qua Tailscale và Alluxio đã hoàn toàn thành công.*

---

## 7. Dừng Hệ Thống An Toàn

Để đảm bảo không làm mất dữ liệu tài khoản và các tương tác người dùng trong MongoDB:

1. Dừng Worker nền:
   ```powershell
   New-Item -ItemType File -Path runtime/jobs/stop -Force
   ```
2. Dừng các dịch vụ container:
   ```powershell
   docker compose --profile app stop web api mongo
   ```
3. Dừng cụm Alluxio (nếu có bật):
   ```powershell
   docker compose --env-file twohost/.env.compute -f twohost/compose.compute.yaml stop
   ```
> **CẢNH BÁO**: Tuyệt đối **không** sử dụng lệnh `docker compose down -v` vì cờ `-v` sẽ xóa sạch các Docker volume lưu trữ tài khoản người dùng và cơ sở dữ liệu MongoDB.

---

## 8. Bảng Xử Lý Các Sự Cố Thường Gặp

| Triệu chứng | Nguyên nhân | Cách khắc phục |
|---|---|---|
| `UnknownHostException: alluxio-master` | Container `alluxio-master` chưa bật hoặc bị tắt | Chạy `docker compose --env-file twohost/.env.compute -f twohost/compose.compute.yaml up -d alluxio-master` |
| `Kiểm tra máy A và kết nối HDFS / Alluxio thất bại` | Máy A chưa online hoặc chưa kết nối Tailscale | Bật Tailscale trên cả 2 máy và kiểm tra lệnh `Test-NetConnection` cổng 9000; hoặc chạy demo Snapshot trên máy local |
| `permission denied while trying to connect to docker API` | Docker Desktop chưa bật | Bật Docker Desktop, chờ biểu tượng chuyển sang màu xanh rồi mở lại |
| `Web vẫn hiển thị giao diện cũ` | Bộ nhớ đệm trình duyệt / Docker chưa build lại | Chạy `python scripts/start_app.py --build`, sau đó nhấn `Ctrl + F5` trên trình duyệt |
| `MongoDB Compass không kết nối được` | Sai cổng kết nối | Sử dụng cổng kết nối **`27018`** (`mongodb://127.0.0.1:27018`) |
