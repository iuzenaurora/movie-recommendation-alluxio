# Dữ liệu

Thư mục này lưu tài liệu nguồn, cấu trúc dữ liệu và mô tả các snapshot. Không dùng để commit toàn bộ MovieLens hoặc Parquet lớn.

## Đầu vào dự kiến

- `ratings.csv`: userId, movieId, rating, timestamp.
- `movies.csv`: movieId, title, genres.
- Các tệp tags/links chỉ sử dụng nếu có chức năng cần đến.

Nguồn chính thức: [GroupLens MovieLens](https://grouplens.org/datasets/movielens/). Ghi phiên bản, ngày tải và điều kiện sử dụng khi chọn dataset. Chưa có bản dữ liệu được xác minh trong repo này.

## Quy trình

1. Giữ nguyên dữ liệu gốc trên Drive.
2. Khảo sát thiếu, trùng, điểm không hợp lệ và quan hệ giữa ID phim.
3. Chốt quy tắc xử lý một người đánh giá cùng phim nhiều lần.
4. Xuất Parquet và manifest.
5. Sao chép cùng bộ tệp lên HDFS và Lustre, kiểm checksum.

## Đầu ra bàn giao

Schema, quy tắc làm sạch, số hàng trước/sau, số người/phim, datasetVersion, file layout, codec, checksum và vị trí lưu. Ghi seed và cách chọn mẫu.

## Nơi lưu

Drive: `01_Dataset_Raw` và `02_Dataset_Processed`. Link và hướng dẫn truy cập sẽ bổ sung; không lưu URL có token bí mật.

## Tình trạng

Chưa có dataset, manifest hoặc thống kê được xác nhận hoàn thành. Cần giữ riêng dữ liệu train/validation/test và snapshot đánh giá ứng dụng để tránh rò rỉ dữ liệu.
