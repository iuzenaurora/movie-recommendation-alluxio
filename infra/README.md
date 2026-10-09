# Hạ tầng xử lý và lưu trữ

Thư mục dành cho cấu hình mẫu, Dockerfile/Compose nếu sử dụng, mô tả mạng, vùng lưu dữ liệu và hướng dẫn vận hành. Hiện chưa có cấu hình đã kiểm thử.

## Phương án dự kiến

- Windows + Docker Desktop dùng WSL 2 để thử HDFS, Alluxio và Spark.
- Lustre trên Linux có kernel/module phù hợp; môi trường chưa chốt.
- Colab dùng để phát triển notebook; benchmark lưu trữ cần đường đọc thực tế trong môi trường Linux.

Docker không loại bỏ yêu cầu ảo hóa trên Windows và không tự cung cấp kernel phù hợp cho Lustre. Các phiên bản phần mềm, image tag và tài nguyên cần xác minh bằng phép thử nhỏ trước khi chốt.

## Thành phần

- HDFS: NameNode, DataNode và dữ liệu bền.
- Alluxio: Master, Worker, journal và vùng cache.
- Spark: môi trường thực thi tác vụ dữ liệu.
- Lustre: server/targets và client mount thật.

## Kiểm tra tối thiểu

1. Ghi/đọc tệp nhỏ trên từng backend.
2. Mount nguồn vào Alluxio và đọc qua Spark.
3. Đối chiếu checksum và kết quả logic.
4. Xác nhận dữ liệu bền trước khi giải phóng cache.
5. Kiểm phục hồi dịch vụ sau restart.

Chỉ chuẩn bị/format target thử nghiệm mới dành riêng cho Lustre. Không format vùng chứa dữ liệu cá nhân; không format lại nguồn đã có để khởi động dịch vụ.

## Tài nguyên và lưu trữ

Máy 16 GB cần thử từng nhóm dịch vụ, đo tổng RAM và swap. Không cấp hết RAM cho Docker hoặc máy ảo. Dữ liệu volume, cache và đĩa máy ảo lưu ngoài Git; repo chứa cấu hình để dựng lại môi trường.

## Tài liệu phải bổ sung

Phiên bản đã kiểm tra, image nguồn, cổng, tên dịch vụ, đường mount, quota cache, giới hạn tài nguyên, thứ tự startup, thao tác kiểm tra/dừng/phục hồi và sao lưu. Không commit thông tin xác thực thật.
