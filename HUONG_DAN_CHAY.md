# Hướng dẫn chạy MovieLab

Tất cả lệnh dưới đây chạy ở thư mục chứa `compose.yaml` trên máy B, trừ phần ghi rõ máy A. Hoạt động thường ngày thực hiện trên web; lệnh dành cho cài đặt lần đầu và chẩn đoán.

## 1. Chuẩn bị

- Windows, Docker Desktop hoạt động ở chế độ Linux containers.
- Python trên máy B. Launcher sử dụng thư viện chuẩn và Tkinter; không cần cài PHP/Java trên Windows khi chạy bằng Docker.
- Dung lượng trống cho CSV, Parquet, model và Docker volumes. Các bản retrain tạo thêm phiên bản dữ liệu/model.
- Nếu retrain hoặc benchmark qua mạng: máy A chạy HDFS và kết nối LAN/Tailscale với máy B.
- Các cổng local 8080, 8000 và 27018 còn trống.

Mở PowerShell tại dự án nếu cần cài đặt:

```powershell
cd C:\BigData\movie-recommendation-alluxio
docker version
docker compose version
python --version
if (!(Test-Path .env)) { Copy-Item .env.example .env }
docker compose config --quiet
```

Không thay `COMPOSE_PROJECT_NAME` của bản đang sử dụng: tên project quyết định tên Docker volumes chứa tài khoản và MongoDB.

## 2. Phân biệt máy đã cài với bản clone mới

**Máy hiện tại đã có dữ liệu/model:** chuyển đến mục 3.

**Bản clone mới:** source không tự bao gồm dataset, model, credential hay volumes. Cần chuẩn bị:

| Đường dẫn | Nội dung |
|---|---|
| `models/current/` | Gói model đã xuất, có manifest, factors và checksum hợp lệ |
| `data/raw/ml-32m/links.csv` | Ánh xạ IMDb để lấy poster; nếu thiếu dùng poster SVG |
| `data/processed/ml32m-full-v1/` | Dataset gốc và manifest, bắt buộc cho worker benchmark/retrain |
| HDFS `/datasets/ml32m-full-v1` | Bản dữ liệu trùng manifest local, bắt buộc cho tác vụ hai máy |

Không đổi tên hoặc trộn file từ nhiều gói model. Hướng dẫn tạo model ban đầu: [notebooks/README.md](notebooks/README.md). Thiết lập/đưa dữ liệu lên máy A: [twohost/README.md](twohost/README.md).

## 3. Chạy chương trình

### Cách thường dùng, không nhập lệnh

1. Bật Docker Desktop và chờ engine sẵn sàng.
2. Nhấp đúp `MovieLab.pyw` ở thư mục dự án.
3. Cửa sổ khởi động bật web/API/MongoDB và worker, rồi mở trình duyệt.

Nếu Windows mở file bằng trình soạn thảo, chọn mở bằng Python; hoặc dùng lệnh ở dưới. Launcher không tự cài Docker/Python và không build source mới.

### Lần đầu hoặc sau khi sửa mã nguồn

```powershell
python scripts/start_app.py --build
```

Lần sau có thể dùng `python scripts/start_app.py` hoặc launcher. Script nhận biết worker đang chạy để tránh tạo bản trùng.

Nếu launcher lỗi, xem `runtime/launcher.log`. Nếu Docker chưa hoạt động, bật Docker Desktop rồi mở launcher lại.

## 4. Tài khoản và cách demo

- Web: **http://localhost:8080**.
- Người dùng mới chọn **Đăng ký**. Dùng email/mật khẩu vừa tạo để đăng nhập các lần sau.
- Admin ban đầu: thông tin trong `runtime/admin-credentials.json`. File được tạo một lần khi chưa có admin.
- Không có mật khẩu đăng nhập lấy từ MovieLens.
- Không chia sẻ thư mục `runtime/` khi gửi source công khai.

Kịch bản demo:

1. Tìm phim theo tên/thể loại.
2. Mở chi tiết và xem trailer nếu phim đã được cấu hình trailer.
3. Đánh giá vài phim, mở **Dành cho bạn** để xem gợi ý.
4. Sửa/xóa điểm tại **Đánh giá của tôi**.
5. Đăng nhập admin, vào **Điều hành** để xem bộ đếm đánh giá mới, lịch benchmark, model hiện tại và nhật ký.
6. Mở một job để xem các bước chạy và log tự cập nhật.

Poster và video dùng nguồn ngoài; trình duyệt cần Internet để tải chúng. Không phải mọi phim trong MovieLens đều có trailer đã được xác minh.

## 5. Cập nhật dữ liệu và retrain tự động

Tại **Admin → Điều hành → Thiết lập tự động**:

| Thiết lập | Mặc định khởi tạo | Ý nghĩa |
|---|---:|---|
| Benchmark mỗi | 24 giờ | Đo H0/H1/H2, mỗi kịch bản 5 lượt |
| Retrain sau số thay đổi đánh giá | 20 | Tính thêm/sửa/xóa điểm trên web |
| Tự cập nhật model sau retrain | Bật | Kiểm tra và kích hoạt model mới |

Giá trị 0 tắt lịch tương ứng. Thiết lập đã lưu được giữ qua lần khởi động lại.

Worker chỉ đánh dấu dữ liệu đã học sau khi retrain thành công. Nếu tác vụ lỗi, đánh giá chưa học vẫn còn trong bộ đếm; scheduler có thời gian chờ trước khi thử lại. Nếu tắt tự kích hoạt, admin chọn model ở **Chạy ngay và quản lý phiên bản**.

Muốn chạy thử mà chưa đủ ngưỡng: mở **Chạy ngay và quản lý phiên bản**, chọn snapshot/retrain/benchmark. Không cần chạy lệnh riêng trong CMD.

**Đây là cập nhật từ tương tác trên web**, không phải tự tải bộ MovieLens mới từ Internet. Model retrain giữ chính sách phim ứng viên của model cha. Huấn luyện/chọn chính sách mới dùng quy trình notebook/train riêng.

## 6. Xem MongoDB bằng giao diện

Mở MongoDB Compass, tạo kết nối:

```text
mongodb://127.0.0.1:27018
```

Chọn database **movies**:

| Collection | Nội dung |
|---|---|
| `movies` | Danh mục phim |
| `users` | Hồ sơ gợi ý và ánh xạ người dùng |
| `ratings` | Đánh giá từ ứng dụng |
| `operations` | Bộ đếm thay đổi phục vụ tự động hóa |

32 triệu đánh giá gốc dùng cho Spark nằm trong Parquet, không được nhập toàn bộ vào MongoDB. Tài khoản đăng nhập/mật khẩu nằm trong SQLite ở Docker volume `web-storage`.

Xem bằng shell khi cần:

```powershell
docker compose exec mongo mongosh movies
```

```javascript
show collections
db.movies.findOne()
db.ratings.find().limit(10)
```

## 7. Dữ liệu bền vững và sao lưu

| Vị trí | Cần giữ |
|---|---|
| Docker volume `web-storage` | SQLite tài khoản và application key |
| Docker volume `mongo` | Danh mục, hồ sơ, đánh giá |
| HDFS volumes máy A | Dữ liệu Parquet trên HDFS |
| `models/` | current, previous và các phiên bản |
| `runtime/` | Snapshot, job, cấu hình, token và thông tin admin |
| `experiments/` | Bằng chứng thực nghiệm và báo cáo |

Build/tạo lại container không xóa volume. Không dùng `docker compose down -v` để dừng thông thường. Sao lưu cả volumes và các thư mục cần thiết; chỉ sao chép source không mang theo tài khoản/đánh giá trong volumes.

## 8. Dừng chương trình

Chờ job hoàn tất; không tắt máy giữa lúc ghi dữ liệu hoặc chuyển model.

Yêu cầu worker dừng khi xong tác vụ:

```powershell
New-Item -ItemType File -Path runtime/jobs/stop -Force
```

Worker kiểm tra tín hiệu này ở cuối vòng lặp. Khi worker đã dừng, tắt ứng dụng:

```powershell
docker compose --profile app stop web api mongo
```

Dừng Alluxio máy B nếu không còn dùng:

```powershell
docker compose --env-file twohost/.env.compute -f twohost/compose.compute.yaml stop
```

Máy A dùng lệnh tương ứng trong [hướng dẫn hai máy](twohost/README.md). Lần sau mở lại launcher để khởi động app/worker. Không dừng worker trực tiếp khi job đang chạy.

## 9. Lỗi thường gặp

| Hiện tượng | Cách xử lý |
|---|---|
| `no configuration file provided` | Chuyển vào thư mục chứa `compose.yaml` |
| Docker engine chưa chạy | Bật Docker Desktop, chờ rồi mở lại launcher |
| Web vẫn giao diện cũ | Build bằng starter với `--build`, sau đó Ctrl+F5 |
| Worker chưa kết nối | Mở lại launcher; xem `runtime/worker.stderr.log` |
| Web báo đang cập nhật | Theo dõi job bằng admin; trang thường tự thử lại sau 30 giây |
| `No workers found` | Kiểm tra Alluxio Worker máy B và log theo hướng dẫn hai máy |
| Tailscale ping được nhưng HDFS không được | Kiểm tra cổng 9000/9866/9867, HDFS và firewall máy A |
| Compass không kết nối | Dùng cổng **27018**, kiểm tra MongoDB container đang chạy |
| Snapshot đã tồn tại | Tạo tác vụ mới trên web; worker tự dùng tên UUID |
| Job thất bại/bị ngắt | Mở nhật ký, kiểm tra container còn chạy trước khi tạo job khác |

Các lệnh chẩn đoán không sửa dữ liệu:

```powershell
docker compose --profile app ps -a
docker compose --profile app logs --tail 60 api web
docker compose port mongo 27017
```

## 10. Kiểm thử và nghiệm thu

Xem [docs/KIEM_THU.md](docs/KIEM_THU.md) cho kết quả đã chạy và các lệnh kiểm thử. Chạy đủ benchmark/retrain thực tế trên máy A mới trước khi kết luận hoàn thành thực nghiệm. Lustre cần xác nhận yêu cầu riêng của giảng viên.
