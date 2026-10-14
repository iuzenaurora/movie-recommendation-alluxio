# Thiết kế dữ liệu và hợp đồng ứng dụng

## Nguồn MovieLens

Ratings dùng userId INT, movieId INT, rating DOUBLE (0,5–5, bước 0,5), timestamp LONG (Unix giây). Movies dùng movieId, title và genres, phân tách bằng dấu |. Giữ nguyên movieId; không thay ID bằng vị trí dòng.

ETL loại dòng sai kiểu, ID/điểm/thời gian không hợp lệ và rating thiếu metadata. Cặp user–movie lấy timestamp mới nhất, hòa timestamp lấy điểm cao hơn. Chia theo thời gian từng người dùng; validation chọn cấu hình, test phục vụ đánh giá cuối.

## Tài khoản Laravel và hồ sơ MongoDB

| Dữ liệu | Nơi lưu |
|---|---|
| Email, password hash, role, app_user_id | SQLite trong volume web-storage |
| UUID hồ sơ, seedHistory, sourceProfileId | MongoDB users |
| Điểm và tombstone xóa | MongoDB ratings |
| Danh mục theo datasetVersion | MongoDB movies |
| Revision đánh giá | MongoDB operations |

app_user_id được lưu bền trên tài khoản, không cấp lại mỗi phiên đăng nhập. API tạo hồ sơ theo UUID bằng upsert idempotent. Collection users là hồ sơ gợi ý, không phải nơi lưu mật khẩu web.

Lịch sử chuẩn = seedHistory (nếu là hồ sơ nguồn) + các thay đổi web. Điểm web thay thế điểm cũ; tombstone ẩn điểm đã xóa. Tài khoản mới không tự nhận lịch sử MovieLens.

## Các định danh

- **appUserId:** UUID của hồ sơ gợi ý gắn với tài khoản.
- **sourceProfileId:** ID MovieLens tùy chọn cho nghiên cứu/demo qua API nội bộ.
- **trainingUserId:** số nguyên cho Spark; tài khoản web dùng ID trên maxSourceUserId. Mapping nằm ở runtime/user-mapping.json và cần sao lưu.
- **datasetVersion:** phiên bản dữ liệu trong manifest.
- **modelVersion:** phiên bản gói model.
- **historyVersion:** hash lịch sử hiện tại, đổi khi thêm/sửa/xóa điểm.

Giữ unique index cho ratings(appUserId,movieId), và danh mục tra cứu theo movieId/datasetVersion. Snapshot giữ mapping ID, không xóa đánh giá khi chuyển model.

## Hợp đồng Laravel–API

Laravel xác thực tài khoản và gửi yêu cầu server-to-server bằng X-Service-Token. Trình duyệt không nhận token nội bộ. Ngoài healthcheck, API cần token khi triển khai.

Endpoint và phương thức xem ở [serving/README.md](../serving/README.md). Gợi ý trả items, fallback, method, modelVersion, datasetVersion, historyVersion, milliseconds và số đánh giá dùng được.

Web mặc định hybrid: ALS fold-in + xếp hạng thể loại có tín hiệu điểm thích/không thích. ALS thuần được giữ riêng để đối chiếu. Theo thể loại là một bộ lọc; popularity là baseline. Điểm xếp hạng không phải xác suất hoặc số sao thật.

## Dữ liệu batch và vận hành

Parquet, manifest và checksum làm đầu vào Spark. Snapshot MongoDB ghép với dataset gốc, upload HDFS, đọc qua Alluxio, xuất model mới và kiểm tra trước khi kích hoạt.

SQLite/MongoDB dùng volumes; dataset/model/runtime/kết quả thực nghiệm không đi cùng gói source GitHub. Báo cáo thực nghiệm cần giữ nguồn gốc, không coi metrics kế thừa trong retrain là đánh giá mới.

Ứng dụng dùng cho lab/local. Chưa có HTTPS hoặc email khôi phục mật khẩu; không dùng cấu hình hiện tại như một triển khai public production.
