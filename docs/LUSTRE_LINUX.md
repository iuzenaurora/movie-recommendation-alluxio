# Lustre thật — điều kiện hoàn thành nhánh L0/L1/L2

Đây là runbook tích hợp sau khi có filesystem Lustre thật, không phải bộ cài tự động cho Windows.

## 1. Chuẩn bị và kiểm chứng
Chọn bản Linux, kernel và Lustre server/client hỗ trợ nhau theo tài liệu phát hành. Dùng lab Linux hoặc VM phù hợp có tài nguyên riêng. Không mặc định kernel WSL2 có module Lustre.
Triển khai MGS/MDS + MDT, OSS + OST và client; có thể gộp vai trò trong lab nhưng phải ghi đúng. Bắt đầu một OST và stripe count 1, ghi cấu hình mạng/đĩa/RAM.
Sau khi mount thành công tại /mnt/lustre, lưu các bằng chứng:

```bash
uname -a
lsmod | grep lustre
findmnt -T /mnt/lustre
lfs df -h /mnt/lustre
lfs getstripe /mnt/lustre
```

findmnt phải cho thấy FSTYPE lustre. Lưu version server/client, layout, log và ảnh bằng chứng. Nếu những điều kiện này chưa đạt, chưa được ghi L0/L1/L2 vào báo cáo.

## 2. Cùng dữ liệu, cùng tài nguyên
Chép nguyên thư mục processed version từ HDFS/local sang /mnt/lustre/datasets/demo-v1; không chạy ETL lại để tạo bộ dữ liệu khác. So SHA256 từng tệp Parquet với manifest checksum do bạn ghi trước khi copy.
Đặt Spark và Alluxio ở Linux client có thể nhìn thấy Lustre. Nếu dùng container Linux, bind mount /mnt/lustre vào **cùng đường dẫn** trong Spark, Alluxio master và worker; kiểm tra mount đã tồn tại trên host trước khi tạo container. Hệ thống journal tách khỏi thư mục dữ liệu.

Thêm namespace Alluxio:

```bash
alluxio fs mount /lustre /mnt/lustre
alluxio fs ls /lustre/datasets/demo-v1/ratings
```

Phải xác minh phiên bản Alluxio đọc mount POSIX Lustre được ở cả master và worker. Đây là truy cập mount POSIX; không tự gọi là native Lustre connector.

## 3. Chạy từng lượt bằng cùng benchmark.py

L0 dùng input file:///mnt/lustre/datasets/demo-v1/ratings tại Spark client. Dù URI file://, backing filesystem của đường dẫn phải là Lustre thật; lưu bằng chứng findmnt.

```bash
bash scripts/spark.sh src/benchmark.py --input file:///mnt/lustre/datasets/demo-v1/ratings --label L0 --repeat 1 --task scan
alluxio fs free -f /lustre/datasets/demo-v1/ratings
alluxio fs du /lustre/datasets/demo-v1/ratings
alluxio fsadmin report metrics
bash scripts/spark.sh src/benchmark.py --input alluxio://alluxio-master:19998/lustre/datasets/demo-v1/ratings --label L1 --repeat 1 --task scan
alluxio fsadmin report metrics
alluxio fs load /lustre/datasets/demo-v1/ratings
alluxio fs du /lustre/datasets/demo-v1/ratings
bash scripts/spark.sh src/benchmark.py --input alluxio://alluxio-master:19998/lustre/datasets/demo-v1/ratings --label L2 --repeat 1 --task scan
```

Thêm --dataset-version <datasetVersion trong manifest.json> vào cả ba lệnh benchmark để tổng hợp chung với nhánh HDFS cùng dữ liệu. Lưu metrics trước/sau, chạy >=5 lượt, lặp cho group. Không dùng mặc định script benchmark.ps1 vì script đó đo HDFS. scripts/spark.sh cần đúng jar path /opt/alluxio/client.jar; nếu ngoài image, thay đường dẫn tương ứng và đặt PYTHONPATH src. Trong cluster nhiều Spark workers, mỗi worker cần cùng Lustre mount.

## 4. Diễn giải
So L1/L2 với L0 tại cùng máy/cấu hình; H1/H2 với H0 tại cùng máy/cấu hình. Nếu HDFS trên laptop còn Lustre trên lab khác phần cứng, không dùng số tuyệt đối để kết luận Lustre/HDFS nào nhanh hơn do filesystem.
Đây là phép so hiệu quả cache Alluxio trên hai backend; Alluxio không có vai trò lưu bền vững ngang HDFS/Lustre.
