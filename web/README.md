# Website Laravel

Thư mục dành cho website PHP/Laravel, tích hợp MongoDB và dịch vụ Python. Chưa có ứng dụng hoặc cấu hình cài đặt đã kiểm thử.

## Chức năng dự kiến

- Chọn hồ sơ demo hoặc tài khoản.
- Tìm phim, xem chi tiết, lọc và phân trang.
- Xem lịch sử, chấm và sửa rating.
- Gọi gợi ý Top 10 theo yêu cầu.
- Hiển thị trạng thái lưu, lỗi/fallback và phiên bản mô hình/lịch sử.
- Dashboard dữ liệu, mô hình và hiệu năng lưu trữ.
- Theo dõi snapshot, retrain và phiên bản hiện hành.

## Trách nhiệm backend

Xác thực hồ sơ, kiểm đầu vào, lưu rating vào MongoDB và lấy lịch sử chuẩn trước khi gọi Python. Không tin lịch sử tùy ý do trình duyệt gửi.

Một cặp tài khoản–phim có một rating hiện hành; sửa điểm là cập nhật. historyVersion tăng nhất quán khi lịch sử đổi. Tách ID tài khoản với trainingUserId qua mapping.

## Luồng rating

Chấm điểm → Laravel kiểm tra/lưu MongoDB → gọi fold-in → tính lại gợi ý. Nếu đã lưu nhưng API lỗi, thông báo đúng trạng thái và cho thử lại; không báo mô hình đã cập nhật chỉ vì DB ghi thành công.

## Dashboard

Dùng CSV/log thật, gắn điều kiện đo và run_id. Chỉ gọi dashboard là live khi lấy trạng thái thật. Trang benchmark lưu trữ tách khỏi nút gợi ý đang dùng factors trong RAM.

## Triển khai

Dự kiến chạy trên Windows hoặc Docker. Phiên bản Laravel/PHP, connector MongoDB, cấu hình kết nối, cổng và thao tác chạy cần chốt khi triển khai. Không commit `.env` thật, vendor hoặc node_modules; cấu hình mẫu không chứa bí mật.
