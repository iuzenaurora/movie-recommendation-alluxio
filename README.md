# MovieLab — Hệ thống gợi ý phim với Spark ALS, Alluxio và HDFS

MovieLab là đồ án Big Data kết hợp website khám phá phim với pipeline dữ liệu. Người dùng đăng ký, xem danh mục và trailer, đánh giá phim và nhận gợi ý cá nhân hóa. Admin theo dõi snapshot, huấn luyện lại, benchmark và phiên bản model ngay trên web.

## Chạy nhanh trên máy hiện tại

1. Bật Docker Desktop.
2. Nhấp đúp **[MovieLab.pyw](MovieLab.pyw)** ở thư mục gốc.
3. Mở **http://localhost:8080**. Worker tự chạy nền cùng ứng dụng.

Máy đã được thiết lập cần có model hợp lệ ở `models/current/`. Nếu cài mới, đọc [hướng dẫn chạy](HUONG_DAN_CHAY.md) và [thiết lập hai máy](twohost/README.md) trước. Launcher không tự tải dataset hoặc huấn luyện model ban đầu.

| Địa chỉ | Chức năng |
|---|---|
| http://localhost:8080 | Website |
| http://localhost:8080/register | Tạo tài khoản thường |
| http://localhost:8080/admin/jobs | Điều hành, lịch tự động, tiến độ và nhật ký |
| http://localhost:8080/admin/benchmark | Kết quả benchmark |
| http://localhost:8080/admin/eda | Thống kê dữ liệu |
| http://localhost:8080/admin/versions | Phiên bản model |
| http://localhost:8000/health | Sức khỏe API |
| `mongodb://127.0.0.1:27018` | Kết nối MongoDB Compass |

Admin được tạo một lần khi chưa có admin. Email và mật khẩu ban đầu nằm trong `runtime/admin-credentials.json`. Không đưa file này lên Git.

## Cấu trúc thư mục

```text
movie-recommendation-alluxio/
├── MovieLab.pyw             # Mở ứng dụng bằng nhấp đúp trên Windows
├── compose.yaml             # Web, API, MongoDB; công cụ Spark và hạ tầng một máy
├── .env.example             # Mẫu cấu hình Compose gốc
├── README.md                # Tổng quan và điểm bắt đầu
├── HUONG_DAN_CHAY.md         # Cài đặt, chạy, dừng, xử lý lỗi
├── web/                     # Laravel: tài khoản, giao diện và admin
├── serving/                 # FastAPI: danh mục, đánh giá, fold-in và snapshot
├── src/                     # ETL, train, retrain, benchmark và thuật toán gợi ý
├── scripts/                 # Starter, worker, upload, kiểm tra và tổng hợp
├── twohost/                 # HDFS máy A; Alluxio/Spark máy B
├── infra/                   # Dockerfile Hadoop/Spark và script Alluxio
├── notebooks/               # Khảo sát, tiền xử lý, huấn luyện trên Colab
├── tests/                   # Kiểm thử Python/API/worker/Spark
├── docs/                    # Thiết kế, thực nghiệm, kiểm thử và báo cáo
├── .github/workflows/       # Kiểm tra mã nguồn trên CI
├── data/raw/                # MovieLens CSV gốc, nằm ngoài Git
├── data/processed/          # Parquet và manifest, nằm ngoài Git
├── models/                  # current, previous và các phiên bản model
├── experiments/             # Nhật ký, số đo và báo cáo thực nghiệm
└── runtime/                 # Job, snapshot, lịch tự động và thông tin nội bộ
```

Các thư mục dữ liệu phát sinh có thể chưa có ở một bản clone mới. Cấu trúc chi tiết xem trong README của từng thành phần.

## Dữ liệu và kiến trúc

| Thành phần | Vai trò và nơi lưu |
|---|---|
| MovieLens 32M | Dữ liệu phim và 32.000.204 đánh giá gốc |
| HDFS máy A | Lưu Parquet bền vững để Spark đọc |
| Alluxio máy B | Lớp cache trước HDFS cho công việc Spark |
| Spark ALS máy B | Huấn luyện model; hiện chạy `local[2]` |
| FastAPI + NumPy | Nạp factors và tính gợi ý/fold-in trong RAM |
| MongoDB, database `movies` | Danh mục phim, hồ sơ gợi ý, đánh giá trên web, bộ đếm thay đổi |
| Laravel + SQLite | Tài khoản, mật khẩu đã băm và quyền admin |
| Worker Python trên máy B | Điều phối các job cố định bằng Docker |

Tài khoản web không phải tài khoản MovieLens: dataset chỉ có mã người dùng, không có email hoặc mật khẩu. UUID của tài khoản web liên kết với hồ sơ gợi ý MongoDB.

Alluxio phục vụ đường đọc dữ liệu Spark; phép tính gợi ý từ factors trong RAM không tự được tăng tốc bởi Alluxio. Website hỗ trợ trailer, không phát phim đầy đủ.

Web mặc định dùng **ALS + gu phim**: điểm ALS được xếp hạng bổ sung bằng độ tương đồng thể loại từ các điểm thích/không thích. Tab **ALS thuần** giữ nguyên để đối chiếu; không thay đổi model hoặc số đo benchmark. Đây là điều chỉnh serving nhằm giảm thiên lệch phim điểm cao, chưa phải kết luận cải thiện NDCG trên tập test.

## Tự động hóa trên web

Mặc định khi tạo cấu hình lần đầu:

- Benchmark mỗi **24 giờ**.
- Retrain sau **20 thay đổi đánh giá**: thêm, sửa hoặc xóa.
- **Tự kích hoạt model** sau khi kiểm tra gói và sức khỏe API.
- Nếu kích hoạt lỗi, khôi phục model cũ; giữ dữ liệu tài khoản và đánh giá.
- Sửa lịch hoặc tắt từng chế độ tại **Admin → Điều hành**. Giá trị 0 tắt lịch tương ứng.

Luồng retrain: MongoDB → snapshot → ghép với dataset gốc → upload HDFS → Spark đọc qua Alluxio → ALS → kiểm tra model → kích hoạt nếu bật tự động.

Gợi ý cá nhân thay đổi ngay sau khi đánh giá nhờ fold-in; không phải chờ retrain. Trang điều hành cập nhật mỗi 5 giây và hiển thị những bước worker đã chạy thật. Mỗi lần chỉ chạy một job. API có thể tạm bảo trì khi snapshot hoặc chạy tác vụ nặng; web điều hành vẫn mở.

Máy A, máy B, Docker và worker phải hoạt động để lịch được thực hiện. Sau khi khởi động lại Windows, mở lại `MovieLab.pyw`; worker chưa được cài thành Windows Service.

## Tài liệu

- [Cài đặt và vận hành](HUONG_DAN_CHAY.md)
- [Triển khai HDFS/Alluxio trên hai máy](twohost/README.md)
- [Danh mục tài liệu kỹ thuật](docs/README.md)
- [Laravel và giao diện](web/README.md)
- [API và MongoDB](serving/README.md)
- [Hạ tầng Docker](infra/README.md)
- [Notebook và huấn luyện ban đầu](notebooks/README.md)
- [Kiểm thử đã thực hiện và phần còn lại](docs/KIEM_THU.md)

## Chuẩn bị push GitHub

Chạy `python scripts/check_source.py` trước khi stage/commit. Lệnh kiểm tra danh sách file Git sẽ công bố, phát hiện một số dạng secret, file phát sinh và notebook output; không in giá trị secret. Dữ liệu/model/kết quả thực nghiệm/runtime và dependency được Git bỏ qua, vẫn giữ nguyên trên máy.

Có thể tạo ZIP source sạch bằng `python scripts/check_source.py --archive runtime/MovieLab-source.zip`. ZIP không chứa dataset/model và không phải bản cài chạy ngay. Các bước review bằng GitHub Desktop hoặc CLI nằm trong [hướng dẫn GitHub](docs/GITHUB_DRIVE.md).

## Trạng thái và giới hạn

Đã kiểm tra đăng ký/đăng nhập, phân quyền admin, đánh giá, gợi ý, lưu tài khoản qua lần tạo lại container, snapshot thật và upload HDFS thử nghiệm. Bản mới qua 19 kiểm thử Laravel và 47 kiểm thử Python/API/worker/metrics/source audit; 5 kiểm tra Spark/HDFS tách riêng. Xem chi tiết trong tài liệu kiểm thử.

Benchmark 15 lượt và retrain toàn bộ qua worker trên máy A mới chưa được nghiệm thu. Báo cáo VPN ngày 12/10/2026 thuộc hạ tầng cũ; không dùng làm số đo của máy A mới. Metrics trong model retrain được ghi nhãn kế thừa, không phải một lần đánh giá chất lượng mới.

Lustre chưa được triển khai/nghiệm thu; còn cần xác nhận yêu cầu của giảng viên. Ứng dụng hiện dành cho local/lab; chưa cấu hình HTTPS và email khôi phục mật khẩu.
