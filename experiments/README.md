# Kết quả thực nghiệm

Giữ các thư mục đã có để bảo toàn log và bằng chứng; không xóa hoặc gắn kết quả của máy cũ cho máy mới.

| Vị trí | Nội dung |
|---|---|
| `als-benchmark/<run>/` | Các lượt benchmark ALS, cache, metrics và cấu hình |
| `als-benchmark/auto-<UUID>/` | Run do worker tạo |
| `metrics-summary-v1/` bên trong run | Báo cáo tổng hợp của run |
| Các thư mục results hiện có | Kết quả các giai đoạn thực nghiệm trước |

Worker benchmark đo H0/H1/H2, mỗi nhãn 5 lượt, kiểm tra trạng thái cache và chỉ công bố pointer `runtime/active-benchmark.json` khi tổng hợp thành công.

Báo cáo VPN `ml32m-vpn-als-20261012-205245` thuộc hạ tầng cũ. Run trên máy A mới cần số đo riêng. Benchmark scan/group và benchmark ALS là hai workload khác nhau, không gộp kết quả.

Xem [protocol](../docs/THUC_NGHIEM.md) và [phạm vi kiểm chứng](../docs/KIEM_THU.md).
