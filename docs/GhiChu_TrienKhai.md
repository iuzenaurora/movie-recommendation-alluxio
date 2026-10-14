# Môi trường triển khai

Cập nhật tài liệu ngày 14/10/2026. Đây là mô tả môi trường đã dùng, không phải yêu cầu phần cứng bảo đảm cho mọi máy.

## Kiến trúc hiện tại

| Máy | Vai trò |
|---|---|
| A | HDFS NameNode/DataNode |
| B | Docker Desktop, Alluxio, Spark local[2], MongoDB, FastAPI, Laravel và worker Python host |

Hai máy kết nối LAN/Tailscale. Ví dụ: A dùng địa chỉ 192.168.1.10 và B dùng 192.168.1.20; đây không phải IP máy thật. Khi thay máy, sửa STORAGE_IP trong cấu hình thật; hướng dẫn không phụ thuộc cố định vào hai IP này.

## Phiên bản thể hiện trong cấu hình

- Laravel chạy trong image PHP 8.3; Composer lock xác định phiên bản package.
- API dùng Python 3.11 theo Dockerfile.
- Spark 3.5.6, Java 11 và Alluxio client 2.9.5 theo Dockerfile Spark.
- MongoDB image 7.0.
- Alluxio image 2.9.5.
- Worker/launcher dùng Python trên Windows.

## Tài nguyên

Compute hiện giới hạn heap Spark 2g, container Spark 3584m, Alluxio cache 512MB. Theo dõi RAM thực tế, swap và dung lượng khi chạy tác vụ toàn bộ.

Ghi chú phần cứng cũ ngày 09/10/2026: máy phát triển Intel Core i5-1035G1, RAM 16GB. Các số RAM trống/dung lượng đĩa tại thời điểm cũ không còn dùng làm trạng thái hiện tại.

## Trạng thái

Web/API/MongoDB hoạt động; đã kiểm tra đọc HDFS/Alluxio, snapshot và upload thử nghiệm. Worker điều phối trên B; launcher không cài worker thành Windows Service.

Lustre chưa triển khai/nghiệm thu. Full retrain và benchmark 15 lượt qua worker trên máy A mới vẫn cần chạy để nghiệm thu. Xem [KIEM_THU.md](KIEM_THU.md) và [triển khai hai máy](../twohost/README.md).
