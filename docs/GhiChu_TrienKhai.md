# Ghi chú môi trường triển khai

Ngày kiểm tra: 09/10/2026.

## Máy phát triển

- Hệ điều hành: Windows; chưa ghi nhận phiên bản cụ thể.
- CPU: Intel Core i5-1035G1.
- Số nhân / luồng: 4 nhân, 8 luồng.
- RAM tổng: 16 GB.
- RAM đang sử dụng: khoảng 11,2 GB tại thời điểm chụp.
- RAM khả dụng: khoảng 4,6 GB tại lần kiểm tra trước.
- GPU: Intel UHD Graphics.
- Virtualization: Enabled.
- Ổ C: tổng dung lượng 236 GB, còn khoảng 14,6 GB.

Các thông số RAM và dung lượng trống thay đổi theo thời điểm.

## Môi trường dự kiến

- Colab: khảo sát, tiền xử lý và thử huấn luyện ALS.
- Google Drive: lưu dữ liệu, notebook, mô hình và kết quả.
- Docker Desktop với WSL 2: dự kiến chạy HDFS, Alluxio,
  Spark và các dịch vụ ứng dụng.
- Lustre: chưa chốt môi trường Linux và kernel tương thích.

## Vị trí lưu trữ

- Repo: https://github.com/iuzenaurora/movie-recommendation-alluxio
- Drive: My Drive/BigData/DoAn_GoiYPhim_Alluxio.
- Đường dẫn repo trên Windows: C:\Users\phatl\bigdata\movie-recommendation-alluxio.

## Trạng thái chuẩn bị

- Đã tạo cấu trúc Drive.
- Đã tạo repository và các README.
- Đã tạo notebook khảo sát và đặt trong thư mục notebooks.
- Đã kiểm tra ảo hóa CPU: Enabled.
- Chưa xác nhận Docker Desktop và WSL 2 hoạt động.
- Chưa triển khai HDFS, Alluxio, Spark và Lustre.
- Cần chuẩn bị thêm dung lượng đĩa trước khi dựng hạ tầng.