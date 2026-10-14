# API gợi ý và snapshot MongoDB

FastAPI + NumPy nạp gói `models/current`, đồng bộ danh mục phim và phục vụ gợi ý. Khởi động từ gốc dự án bằng launcher hoặc starter; xem [hướng dẫn chạy](../HUONG_DAN_CHAY.md).

## File chính

- `api.py`: vòng đời ứng dụng, xác thực nội bộ và các endpoint.
- `snapshot.py`: xuất lịch sử đánh giá để ghép dataset và retrain.
- `requirements.txt`: dependency Python của API.
- `Dockerfile`: image API Python 3.11.
- Thuật toán fold-in/ranking dùng chung ở [src/recommend_core.py](../src/recommend_core.py).

## Endpoint

| Phương thức | Đường dẫn | Chức năng |
|---|---|---|
| GET | `/health` | Sức khỏe và phiên bản |
| GET | `/profiles` | Hồ sơ nguồn trong model |
| POST | `/users` | Tạo/lấy hồ sơ gắn UUID tài khoản web |
| GET | `/movies` | Catalog, tìm kiếm, phân trang |
| GET | `/movies/{movie_id}` | Chi tiết phim |
| PUT | `/users/{uid}/ratings` | Thêm/sửa điểm |
| DELETE | `/users/{uid}/ratings/{movie_id}` | Xóa đánh giá |
| GET | `/users/{uid}/history` | Lịch sử hiện tại |
| GET | `/users/{uid}/recommendations` | Gợi ý |
| GET | `/dashboard` | Thống kê |
| GET | `/internal/activity` | Bộ đếm thay đổi cho scheduler |

Ngoài `/health`, API triển khai yêu cầu header `X-Service-Token`. Token được lưu trong `runtime/service-token`; Laravel và worker đọc cùng file. Không gọi các endpoint người dùng trực tiếp từ trình duyệt bằng cách đưa token vào JavaScript.

## Dữ liệu và tính gợi ý

MongoDB ở service `mongo:27017`, database `movies`. Compass trên máy host dùng `mongodb://127.0.0.1:27018`.

| Collection | Chức năng |
|---|---|
| `movies` | Metadata, thông tin factor và phiên bản dataset |
| `users` | UUID hồ sơ và ánh xạ người dùng |
| `ratings` | Điểm hiện tại và thông tin xóa phục vụ snapshot |
| `operations` | Revision của tương tác |

Tài khoản/mật khẩu web nằm ở SQLite, không nằm trong collection users. MovieLens Parquet phục vụ Spark nằm trên HDFS và bản local; MongoDB không chứa toàn bộ 32M ratings gốc.

Fold-in dùng lịch sử hiện tại với item factors cố định. Danh sách ứng viên theo chính sách của gói model, loại phim đã đánh giá. Người chưa đủ dữ liệu dùng fallback được ghi rõ trong phản hồi. Điểm ALS là điểm xếp hạng, không phải xác suất hay phần trăm phù hợp.

Tham số method hỗ trợ als (thuần, mặc định API), hybrid và popularity. Web mặc định chọn hybrid: điểm ALS + 0,75 × cosine thể loại. Hồ sơ thể loại dùng rating−3, phân bổ theo căn số thể loại của phim và chuẩn hóa vector. Điểm dưới 3 tạo tín hiệu âm; 3 trung tính. Phản hồi ghi rõ phương pháp, historySize và supportedRatings. Không blacklist Documentary; raw ALS/train/benchmark vẫn giữ nguyên. Hệ số 0,75 là cấu hình heuristic serving, chưa được chọn bằng validation mới.

## Snapshot và model

Worker tạm dừng API trước khi xuất snapshot nhất quán, rồi tự khởi động lại. Snapshot có tên UUID và không ghi đè file cũ. Không cần gọi `snapshot.py` thủ công trong sử dụng thường ngày.

API kiểm tra checksum gói model trước khi nạp. Kích hoạt/rollback thực hiện qua worker và kiểm tra phiên bản bằng healthcheck. Retrain dùng Alluxio cho đầu vào Spark; scoring trong API thực hiện trong RAM.

Kiểm thử: [tests/test_api.py](../tests/test_api.py), [tests/test_recommendation.py](../tests/test_recommendation.py) và [tài liệu kiểm thử](../docs/KIEM_THU.md).
