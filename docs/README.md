# Danh Mục Tài Liệu Kỹ Thuật (MovieLab)

Tài liệu trong thư mục này cung cấp thiết kế chi tiết, giao thức thực nghiệm, kiểm thử và báo cáo kết quả của hệ thống MovieLab.

---

## 1. Danh sách tài liệu chính

| Tài liệu | Nội dung và Mục đích |
|---|---|
| [THUC_NGHIEM.md](THUC_NGHIEM.md) | **Giao thức và kế hoạch thực nghiệm**: Chi tiết về thiết kế đo tầng lưu trữ (H0/H1/H2), đánh giá chất lượng gợi ý offline (NDCG, Recall, Precision, RMSE, Bootstrap CI) và đo tải HTTP API. |
| [reports/2026-10-20/bao-cao-thuc-nghiem.md](reports/2026-10-20/bao-cao-thuc-nghiem.md) | **Báo cáo kết quả thực nghiệm chính thức**: Số liệu đo đạc thực tế ngày 20/10/2026, bảng đối chiếu 1 máy vs 2 máy, chất lượng gợi ý và biểu đồ. |
| [ThietKe_DuLieu.md](ThietKe_DuLieu.md) | **Thiết kế dữ liệu và định danh**: Phân tích schema MovieLens, quy tắc khử trùng lặp, chia tập theo thời gian và lưu trữ Parquet kèm manifest. |
| [KIEM_THU.md](KIEM_THU.md) | **Kiểm thử và checklist nghiệm thu**: Kết quả các bộ test tự động (Laravel, Python unittest, API, Worker, Spark) và danh sách tiêu chí nghiệm thu đồ án. |
| [GITHUB_DRIVE.md](GITHUB_DRIVE.md) | **Hướng dẫn quản lý mã nguồn**: Phân định giữa mã nguồn đẩy lên GitHub và dữ liệu lớn (dataset/model) lưu trữ ngoài Git. |
| [LUSTRE_LINUX.md](LUSTRE_LINUX.md) | **Tài liệu nghiên cứu mở rộng**: Hướng dẫn và điều kiện thiết lập hệ thống tệp hiệu năng cao Lustre trên Linux. |
| [NGUON_THAM_KHAO.md](NGUON_THAM_KHAO.md) | **Tài liệu tham khảo**: Các liên kết chính thức đến Apache Spark, Alluxio, Hadoop HDFS, GroupLens MovieLens và Laravel. |

---

## 2. Quy tắc quản lý dữ liệu và bằng chứng thực nghiệm

- Mọi số đo thực nghiệm đều được gắn kèm mã định danh phiên bản (`modelVersion`, `datasetVersion`), mã băm SHA-256 và log thực thi đầy đủ trong thư mục `experiments/`.
- Không sử dụng dữ liệu giả lập hoặc kết quả kiểm thử nhỏ (fixtures) để thay thế cho số đo trên tập dữ liệu MovieLens 32M thật.
- Các kết quả thực nghiệm được theo dõi và hiển thị trực tiếp trên giao diện Admin Dashboard tại địa chỉ `http://localhost:8080/admin/benchmark`.
