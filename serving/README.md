# Dịch vụ Python phục vụ gợi ý

Thư mục dành cho Python API dùng factors đã huấn luyện để tính gợi ý theo yêu cầu. Dự kiến FastAPI và NumPy; chưa có API hoạt động hoặc endpoint cố định.

## Đầu vào

Gói mô hình đã kiểm tra, hồ sơ người dùng, lịch sử hiện hành do backend xác thực và bộ lọc phim.

## Xử lý gợi ý

1. Chọn gói modelVersion nhất quán.
2. Lấy vector đã học hoặc vector fold-in đúng historyVersion.
3. Chọn phim có factor, áp dụng bộ lọc và loại phim đã đánh giá.
4. Tính điểm, chọn Top 10 với quy tắc hòa cố định.
5. Trả movieId, score, phiên bản, fallback và thời gian xử lý.

Điểm ALS là điểm xếp hạng, không mặc định là xác suất hoặc điểm trong thang 0.5–5.

## Fold-in

Sau khi rating được lưu, dùng toàn bộ lịch sử hiện hành với rating sửa đã thay thế giá trị cũ. Giữ itemFactors cố định để tính vector cá nhân. Không chỉ dùng rating mới hoặc lấy trung bình vector phim.

User chưa có lịch sử dùng fallback; phim chưa có factor chưa được chấm ALS. Cache vector cá nhân theo appUserId/modelVersion/historyVersion; phản hồi cũ không được ghi đè trạng thái mới.

## Kiểm thử và vận hành

Đối chiếu điểm với Spark, kiểm lọc phim, lỗi, request cũ, restart và chuyển model. Nạp gói mới nhất quán; không ghép factors khác phiên bản. Tài liệu endpoint, schema, dependencies và thao tác chạy sẽ bổ sung sau triển khai.

## Vai trò Alluxio

Alluxio tham gia đầu vào Spark và retrain. Tính điểm/fold-in từ factors trong RAM không tự được tăng tốc bởi Alluxio; cần đo riêng.
