# Gói model

| Thư mục | Vai trò |
|---|---|
| `current/` | Gói API đang nạp |
| `previous/` | Phiên bản trước để rollback, được tạo sau kích hoạt thành công |
| `als-.../` | Model phiên bản, có thể chọn ở admin |
| `before-...`, `previous-archive-...`, `failed-...` | Bản giữ lại từ quá trình chuyển/khôi phục |
| Các `backup-...` hiện có | Sao lưu phục hồi từ các lần vận hành trước |

Các gói hiện có được giữ nguyên khi dọn source. Không xóa current, previous hoặc backup chỉ dựa trên tên thư mục.

Gói xuất gồm manifest, factors.npz, movies.json, profiles.json, popularity.json và candidate_movie_ids.json khi có candidate policy. Manifest chỉ định checksum; không trộn file khác phiên bản.

Model lớn không nằm trong Git. Bản clone mới phải chuẩn bị model hợp lệ riêng trước khi bật API. Kích hoạt/rollback qua trang admin; worker kiểm tra trước khi chuyển và xác nhận health/modelVersion sau khi nạp.

Metrics retrain có thể kế thừa từ model ban đầu và được ghi rõ phạm vi. Không xem metrics kế thừa là đánh giá lại model mới.

Xem [hướng dẫn chạy](../HUONG_DAN_CHAY.md).
