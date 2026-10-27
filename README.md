# MovieLab — Hệ Thống Gợi Ý Phim Phân Tán với Spark ALS, Alluxio và HDFS

MovieLab là hệ thống Big Data kết hợp nền tảng gợi ý phim trực tuyến thời gian thực với pipeline xử lý và phân tích dữ liệu lớn. Người dùng có thể đăng ký tài khoản, khám phá catalog phim, xem trailer YouTube, chấm điểm sao và nhận danh sách gợi ý phim Top 10 được cá nhân hóa ngay lập tức. Trang quản trị viên (Admin Dashboard) hỗ trợ theo dõi tiến trình nền, tự động tạo snapshot, tái huấn luyện mô hình (retrain), theo dõi benchmark hiệu năng và quản lý các phiên bản mô hình.

---

## Báo Cáo Thực Nghiệm và Kết Quả

Hệ thống đã hoàn thành đầy đủ các đợt thực nghiệm lớn trên tập dữ liệu **MovieLens 32M** (hơn 32 triệu tương tác đánh giá):
- **Hiệu năng tầng lưu trữ Alluxio vs HDFS**: Thực nghiệm trên cả 1 máy và 2 máy vật lý kết nối qua Tailscale VPN. Kết quả cho thấy Alluxio Warm Cache giúp **giảm 39.51% thời gian đọc dữ liệu** của Spark và **triệt tiêu 100% I/O mạng từ HDFS**.
- **Chất lượng thuật toán gợi ý**: Đánh giá ghép cặp 4 chiến lược (ALS fold-in, Hybrid, Theo gu, Phổ biến) trên 500 người dùng test độc lập với 3 mức lịch sử (`last-5`, `last-20`, `full`), kiểm định Bootstrap 95% CI và đo đạc 1.200 request HTTP API thực tế (tỷ lệ lỗi 0%).
- Chi tiết kết quả và biểu đồ trực quan: [Báo cáo thực nghiệm chính thức](docs/reports/2026-10-20/bao-cao-thuc-nghiem.md) và [Giao thức thực nghiệm](docs/THUC_NGHIEM.md).

> **Lưu ý về quản lý mã nguồn (Git Policy)**: 
> - **Dataset lớn không tải lên GitHub**: Các tệp dataset gốc (`data/raw/`), dữ liệu phân tán Parquet (`data/processed/`), và ma trận vector nhị phân (`*.npz`, `models/*`) được cấu hình trong `.gitignore` để lưu trữ an toàn trên máy cục bộ, tuyệt đối không đẩy lên Git.
> - **Cho phép tải các tệp kết quả lên GitHub**: Toàn bộ tệp số liệu thực nghiệm, bảng tổng hợp CSV, báo cáo Markdown, tệp nhật ký (logs) và biểu đồ trực quan trong thư mục [`experiments/`](experiments/README.md) được phép theo dõi trên Git để phục vụ thẩm định và nghiệm thu.

---

## Cấu Trúc Dự Án và Giải Thích Chi Tiết Từng Thư Mục

Sơ đồ cấu trúc tổng thể của kho lưu trữ:

```text
movie-recommendation-alluxio/
├── MovieLab.pyw                     # Trình khởi chạy giao diện nhanh bằng 1 click trên Windows
├── compose.yaml                     # Docker Compose chính: Web, API, MongoDB, Spark local
├── compose.evaluation.yaml          # Docker Compose phục vụ benchmark tải API độc lập
├── .env.example                     # Tệp mẫu cấu hình môi trường
├── HUONG_DAN_CHAY.md                # Tài liệu hướng dẫn vận hành và khắc phục sự cố
├── README.md                        # Tài liệu tổng quan và hướng dẫn triển khai
│
├── web/                             # [Laravel Web Application]
├── serving/                         # [FastAPI Serving Service]
├── src/                             # [Core Data & ML Pipeline]
├── twohost/                         # [Two-Host Distributed Deployment]
├── infra/                           # [Hadoop, Spark & Alluxio Dockerfiles]
├── notebooks/                       # [EDA & Training Notebooks on Colab]
├── scripts/                         # [Automation & Management Scripts]
├── docs/                            # [Technical Documentation & Protocols]
├── experiments/                     # [Benchmark & Evaluation Results]
├── tests/                           # [Automated Test Suite]
├── data/                            # [Dataset Directory - Local Only]
├── models/                          # [Model Artifacts - Local Only]
└── runtime/                         # [Runtime Credentials & Logs - Local Only]
```

### Bảng giải thích chi tiết và liên kết tới tài liệu từng thư mục

| Thư mục | Vai trò và Chức năng chi tiết | Tài liệu chi tiết |
|---|---|:---:|
| [`web/`](web/README.md) | **Cổng thông tin người dùng và Quản trị (Laravel 11, Blade, Tailwind CSS)**:<br>• Đăng ký, đăng nhập tài khoản an toàn với SQLite và bcrypt.<br>• Giao diện duyệt catalog phim phong cách Cinematic, tìm kiếm, phân trang và xem trailer YouTube.<br>• Chấm điểm sao tương tác (0.5 – 5.0 sao) và quản lý lịch sử đánh giá.<br>• Trang điều hành Admin (`/admin/jobs`): Giám sát tiến trình worker, lịch tự động retrain/benchmark, thống kê EDA và xem kết quả benchmark trực quan. | [**`web/README.md`**](web/README.md) |
| [`serving/`](serving/README.md) | **Dịch vụ gợi ý trực tuyến thời gian thực (FastAPI + NumPy)**:<br>• Nạp ma trận item factors vào RAM để chấm điểm ứng viên dưới 100ms.<br>• Triển khai thuật toán **ALS Fold-in** giải hệ phương trình tuyến tính tức thời khi có điểm sao mới mà không cần kích hoạt lại Spark job.<br>• Tích hợp 4 chiến lược gợi ý: ALS thuần, Hybrid (ALS + Gu thể loại), Theo gu (Lọc thể loại + ALS), và Phổ biến (Baseline).<br>• Xuất dữ liệu snapshot nhất quán từ MongoDB phục vụ retrain. | [**`serving/README.md`**](serving/README.md) |
| [`src/`](src/README.md) | **Mã nguồn xử lý dữ liệu và thuật toán lõi (PySpark & MLlib)**:<br>• `etl.py`: Làm sạch MovieLens 32M, khử trùng lặp qua Window Functions và chia tập theo thời gian 80/10/10.<br>• `train.py`: Huấn luyện mô hình Explicit ALS, tối ưu hóa siêu tham số trên Validation và xuất gói model.<br>• `recommend_core.py`: Thuật toán Fold-in NumPy thuần, tính hồ sơ thể loại có dấu (Signed Genre Profile), Hybrid reranking và chỉ số ranking.<br>• `benchmark_als.py` & `benchmark.py`: Workload đo lường I/O và thời gian huấn luyện cho H0/H1/H2.<br>• `merge_snapshot.py` & `retrain.py`: Cơ chế ghép dữ liệu mới và tái huấn luyện mô hình. | [**`src/README.md`**](src/README.md) |
| [`twohost/`](twohost/README.md) | **Kiến trúc triển khai phân tán trên 2 máy vật lý**:<br>• **Máy A (Storage Node)**: Chạy HDFS NameNode và DataNode lưu trữ Parquet bền vững.<br>• **Máy B (Compute Node)**: Chạy Alluxio In-Memory Cache, Spark, MongoDB, API và Web.<br>• Kết nối bảo mật và định tuyến giữa hai máy qua mạng ảo **Tailscale VPN (WireGuard)**. | [**`twohost/README.md`**](twohost/README.md) |
| [`infra/`](infra/README.md) | **Cấu hình hạ tầng container hóa (Dockerfiles & Scripts)**:<br>• Dockerfile đóng gói Hadoop 3.3.6 (NameNode/DataNode) và Spark 3.5.1.<br>• Cấu hình tích hợp Alluxio Client jar vào classpath của Spark.<br>• Cấu hình môi trường đo tải riêng biệt trong thư mục `evaluation/`. | [**`infra/README.md`**](infra/README.md) |
| [`notebooks/`](notebooks/README.md) | **Sổ tay nghiên cứu và tiền xử lý (Jupyter Notebooks trên Google Colab)**:<br>• `00_ChuanBi_Colab.ipynb`: Thiết lập môi trường và cấu hình Drive.<br>• `01_KhaoSat_MovieLens.ipynb`: Phân tích thống kê EDA trên tập MovieLens 32M.<br>• `02_TienXuLy.ipynb`: Tiền xử lý dữ liệu lớn và phân chia train/val/test.<br>• `03_HuanLuyen_ALS.ipynb`: Huấn luyện thử nghiệm Explicit ALS trên tài nguyên GPU/TPU Colab. | [**`notebooks/README.md`**](notebooks/README.md) |
| [`scripts/`](scripts/README.md) | **Kịch bản tự động hóa và tiện ích điều phối**:<br>• `MovieLab.pyw` & `start_app.py`: Trình khởi chạy hệ thống thông minh.<br>• `job_worker.py`: Tiến trình worker daemon giám sát lịch trình và tự động retrain/rollback.<br>• `run_report_experiments.ps1`: Tự động hóa đánh giá chất lượng và đo tải API.<br>• `check_source.py`: Công cụ rà soát an toàn mã nguồn trước khi commit Git. | [**`scripts/README.md`**](scripts/README.md) |
| [`docs/`](docs/README.md) | **Tài liệu kỹ thuật, kiến trúc và kiểm thử**:<br>• `THUC_NGHIEM.md`: Toàn văn giao thức thực nghiệm lưu trữ, chất lượng gợi ý và tải API.<br>• `reports/`: Chứa các báo cáo thực nghiệm chính thức kèm biểu đồ.<br>• `ThietKe_DuLieu.md`: Thiết kế lược đồ dữ liệu và định danh.<br>• `KIEM_THU.md`: Báo cáo kết quả kiểm thử và checklist nghiệm thu.<br>• `LUSTRE_LINUX.md` & `NGUON_THAM_KHAO.md`: Nghiên cứu mở rộng và tài liệu tham khảo. | [**`docs/README.md`**](docs/README.md) |
| [`experiments/`](experiments/README.md) | **Kho lưu trữ kết quả và bằng chứng thực nghiệm**:<br>• `als-benchmark/`: Dữ liệu đo đạc H0/H1/H2 trên 1 máy và 2 máy qua Tailscale VPN.<br>• `recommendation-evaluation/`: Kết quả đánh giá chất lượng Top 10 và benchmark API HTTP.<br>• `results/`: Kết quả kiểm thử các tác vụ scan và group cơ bản. | [**`experiments/README.md`**](experiments/README.md) |
| [`tests/`](tests/README.md) | **Bộ kiểm thử tự động (Test Suite)**:<br>• Kiểm thử đơn vị cho các thuật toán gợi ý, API endpoint, logic worker, bộ tổng hợp metrics và kiểm định tính toàn vẹn mã nguồn. | [**`tests/README.md`**](tests/README.md) |
| [`data/`](data/README.md) | **Thư mục quản lý dữ liệu lớn (Local Only)**:<br>• `data/raw/`: Chứa các tệp CSV gốc của MovieLens (nằm ngoài Git).<br>• `data/processed/`: Chứa các phân vùng Parquet và tệp manifest (nằm ngoài Git). | [**`data/README.md`**](data/README.md) |
| [`models/`](models/README.md) | **Thư mục quản lý các phiên bản mô hình (Local Only)**:<br>• Chứa các gói model hiện tại (`current/`) và lịch sử (`previous/`) gồm vector đặc trưng và metadata (nằm ngoài Git). | [**`models/README.md`**](models/README.md) |
| `runtime/` | **Thư mục trạng thái runtime của ứng dụng (Local Only)**:<br>• Chứa thông tin đăng nhập admin khởi tạo ban đầu (`admin-credentials.json`), service token nội bộ, cơ sở dữ liệu SQLite của tài khoản và log tác vụ nền. | *(Tự động sinh khi chạy)* |

---

## Các Bước Triển Khai Để Chạy Code

Hệ thống hỗ trợ chạy trên **môi trường 1 máy** (phục vụ kiểm thử, demo, phát triển) và **môi trường 2 máy** (phục vụ thực nghiệm phân tán với HDFS và Alluxio).

### Bước 1: Yêu cầu môi trường tiên quyết
- **Hệ điều hành**: Windows 10/11 (khuyến nghị có WSL2) hoặc Linux (Ubuntu 20.04/22.04).
- **Phần mềm bắt buộc**:
  - [Docker Desktop](https://www.docker.com/products/docker-desktop/) đã bật chế độ Linux Containers.
  - [Python 3.10+](https://www.python.org/) trên máy tính để chạy launcher và script điều phối (không bắt buộc cài đặt PHP hay Java trực tiếp trên máy chủ vì tất cả đã được container hóa).
  - Đảm bảo các cổng mạng sau đang trống: `8080` (Web), `8000` (API), `27018` (MongoDB Compass), `19999` (Alluxio UI).

---

### Bước 2: Chuẩn bị tệp cấu hình và gói Model
1. Mở PowerShell hoặc Terminal tại thư mục gốc của dự án:
   ```powershell
   # Sao chép tệp cấu hình môi trường mẫu nếu chưa có
   if (!(Test-Path .env)) { Copy-Item .env.example .env }
   ```
2. Đảm bảo thư mục `models/current/` có sẵn gói model hợp lệ (gồm `factors.npz`, `movies.json`, `candidate_movie_ids.json`, `manifest.json`). *(Nếu cài đặt mới hoàn toàn từ đầu, xem hướng dẫn chuẩn bị dữ liệu trong [notebooks/README.md](notebooks/README.md))*.

---

### Bước 3: Khởi chạy hệ thống trên 1 máy (Single-host Run)

#### Cách 1: Khởi chạy bằng 1 cú nhấp chuột (Khuyên dùng trên Windows)
1. Bật Docker Desktop và đợi Docker Engine chuyển sang trạng thái sẵn sàng (Running).
2. Nhấp đúp vào tệp **[`MovieLab.pyw`](MovieLab.pyw)** ở thư mục gốc của dự án.
3. Cửa sổ launcher sẽ tự động khởi động các container (Laravel Web, FastAPI, MongoDB), bật tiến trình Worker nền và tự động mở trình duyệt web.

#### Cách 2: Khởi chạy thủ công bằng dòng lệnh (Dành cho lần đầu hoặc khi build lại)
```powershell
# 1. Cài đặt các thư viện Python bổ trợ cho việc chạy script
pip install -r requirements.txt

# 2. Khởi động toàn bộ dịch vụ và tự động kích hoạt worker nền
python scripts/start_app.py --build
```
*(Các lần chạy sau có thể bỏ qua cờ `--build`: `python scripts/start_app.py`)*.

---

### Bước 4: Khởi chạy hệ thống phân tán 2 máy (Two-host Setup - Tùy chọn)
Nếu bạn muốn tái lập thực nghiệm phân tán với Máy A (HDFS) và Máy B (Spark/Alluxio):
1. **Kết nối mạng**: Cài đặt [Tailscale](https://tailscale.com/) trên cả 2 máy và kết nối vào cùng mạng riêng ảo để lấy IP Tailscale của Máy A.
2. **Trên Máy A (Storage)**:
   ```powershell
   Copy-Item twohost/.env.storage.example twohost/.env.storage
   # Điền STORAGE_IP là IP Tailscale của Máy A
   docker compose --env-file twohost/.env.storage -f twohost/compose.storage.yaml up -d
   ```
3. **Trên Máy B (Compute & App)**:
   ```powershell
   Copy-Item twohost/.env.compute.example twohost/.env.compute
   # Điền STORAGE_IP trùng với IP của Máy A
   docker compose --env-file twohost/.env.compute -f twohost/compose.compute.yaml up -d alluxio-master alluxio-worker
   ```
*Chi tiết các bước kiểm tra cổng mạng TCP và tải dataset lên HDFS xem tại [twohost/README.md](twohost/README.md).*

---

### Bước 5: Kiểm tra và Truy cập các cổng giao diện

Sau khi khởi chạy thành công, truy cập các địa chỉ sau trên trình duyệt:

| Địa chỉ truy cập | Dịch vụ và Chức năng | Thông tin đăng nhập |
|---|---|---|
| **http://localhost:8080** | **Website xem phim và nhận gợi ý** | Đăng ký tài khoản mới tại `/register` |
| **http://localhost:8080/admin/jobs** | **Trung tâm điều hành và quản trị tự động** | Tài khoản Admin ban đầu lưu tại `runtime/admin-credentials.json` |
| **http://localhost:8080/admin/benchmark** | **Xem kết quả benchmark lưu trữ H0/H1/H2** | Truy cập qua tài khoản Admin |
| **http://localhost:8080/admin/eda** | **Thống kê dữ liệu MovieLens 32M** | Truy cập qua tài khoản Admin |
| **http://localhost:8000/health** | **Kiểm tra trạng thái sức khỏe của API gợi ý** | Công khai (Public) |
| **`mongodb://127.0.0.1:27018`** | **Kết nối trực tiếp bằng MongoDB Compass** | Database: `movies` |

---

### Bước 6: Chạy kiểm thử tự động và Tái lập thực nghiệm

#### Chạy kiểm thử tự động hệ thống:
```powershell
# Chạy bộ kiểm thử đơn vị Python
python -m unittest discover -s tests

# Kiểm tra an toàn mã nguồn trước khi đẩy lên Git
python scripts/check_source.py
```

#### Tự động chạy lại thực nghiệm đánh giá gợi ý và đo tải API:
```powershell
# Chạy toàn bộ pipeline đánh giá offline và benchmark HTTP API độc lập
.\scripts\run_report_experiments.ps1
```

---

### Bước 7: Dừng hệ thống an toàn và Bảo toàn dữ liệu
Để dừng toàn bộ dịch vụ mà không làm mất dữ liệu tài khoản và lịch sử đánh giá:
```powershell
# Dừng các dịch vụ Docker và giữ nguyên volume dữ liệu
docker compose stop
```
*(Tuyệt đối không dùng lệnh `docker compose down -v` vì sẽ xóa sạch volume chứa tài khoản người dùng và đánh giá MongoDB).*

---

## Liên Kết Tài Liệu Hữu Ích
- [Hướng dẫn chi tiết vận hành và xử lý sự cố (HUONG_DAN_CHAY.md)](HUONG_DAN_CHAY.md)
- [Giao thức và kế hoạch thực nghiệm (docs/THUC_NGHIEM.md)](docs/THUC_NGHIEM.md)
- [Báo cáo kết quả thực nghiệm chi tiết (docs/reports/2026-10-20/bao-cao-thuc-nghiem.md)](docs/reports/2026-10-20/bao-cao-thuc-nghiem.md)
- [Kiểm thử và checklist nghiệm thu đồ án (docs/KIEM_THU.md)](docs/KIEM_THU.md)
