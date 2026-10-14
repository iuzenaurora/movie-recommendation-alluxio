# Tài liệu dự án

Bắt đầu từ [README gốc](../README.md) và [hướng dẫn chạy](../HUONG_DAN_CHAY.md). Tài liệu trong thư mục này tập trung vào thiết kế, bằng chứng và báo cáo; không cần đọc hết để mở web.

| Tài liệu | Nội dung |
|---|---|
| [ThietKe_DuLieu.md](ThietKe_DuLieu.md) | Thiết kế dữ liệu và định danh; đối chiếu schema thực thi trong source |
| [THUC_NGHIEM.md](THUC_NGHIEM.md) | Protocol đo, điều kiện cache và cách diễn giải |
| [KIEM_THU.md](KIEM_THU.md) | Kiểm tra đã chạy, lệnh kiểm thử và phần chưa nghiệm thu |
| [GhiChu_TrienKhai.md](GhiChu_TrienKhai.md) | Môi trường và kiến trúc triển khai hiện tại |
| [GITHUB_DRIVE.md](GITHUB_DRIVE.md) | Phân chia source/dữ liệu và lưu trữ ngoài Git |
| [LUSTRE_LINUX.md](LUSTRE_LINUX.md) | Điều kiện triển khai Lustre thật |
| [NGUON_THAM_KHAO.md](NGUON_THAM_KHAO.md) | Nguồn tham khảo |
| [BAO_CAO_MAU.md](BAO_CAO_MAU.md) | Khung báo cáo để điền bằng kết quả thực tế |
| [CHECKLIST_NOP.md](CHECKLIST_NOP.md) | Checklist nghiệm thu/nộp bài |

Các tài liệu thiết kế, protocol và mẫu báo cáo có thể chứa phương án chưa triển khai. Trạng thái kiểm chứng xem ở KIEM_THU; cấu hình chạy thực tế nằm trong Compose và source.

## Quy tắc bằng chứng

- Gắn số đo với run, datasetVersion, modelVersion và hạ tầng thực tế.
- Tách benchmark của máy A cũ khỏi máy A mới.
- Không dùng test tổng hợp, fixture hoặc metrics kế thừa làm kết quả đánh giá mới.
- Giữ model, snapshot và báo cáo cũ để đối chiếu.
- Chỉ kết luận Lustre hoàn thành khi có triển khai và số đo Lustre thật.

## Dọn cấu trúc ngày 14/10/2026

Đã bỏ giao diện overlay cũ, thư mục mẫu Drive chỉ chứa ghi chú, file checksum/validation của gói phát hành cũ và hướng dẫn patch đã tích hợp. Hướng dẫn sử dụng được gom về README gốc, HUONG_DAN_CHAY và README hai máy. Dataset, model, runtime, kết quả thực nghiệm và tài liệu chuyên môn được giữ.
