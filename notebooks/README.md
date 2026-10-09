# Notebook dữ liệu và mô hình

Thư mục lưu phiên bản notebook được quản lý bằng Git. Colab là nơi làm việc dự kiến, Drive là nơi lưu bản làm việc và các kết quả lớn.

## Danh sách dự kiến

1. `01_KhaoSat_MovieLens.ipynb`: khảo sát quy mô, phân bố và lỗi dữ liệu.
2. `02_TienXuLy_MovieLens.ipynb`: làm sạch, tạo mẫu, Parquet và manifest.
3. `03_HuanLuyen_ALS.ipynb`: split, baseline, chọn tham số và xuất mô hình.
4. `04_DanhGia_GoiY.ipynb`: đánh giá điểm, Top 10 và fold-in.

Các tên trên là kế hoạch; không khẳng định các notebook đã tồn tại.

## Mỗi notebook cần ghi

Mục tiêu, đầu vào/đầu ra, đường dẫn dữ liệu, phiên bản thư viện thực tế, seed, tài nguyên và điều kiện chạy. Giữ dữ liệu test ngoài việc chọn tham số; fold-in không được nhận trước các rating dùng để đánh giá.

## Thử nghiệm ban đầu

Dùng Latest Small để kiểm quy trình, sau đó mẫu khoảng một triệu rating. Lưới ALS ban đầu dự kiến rank 10/20, regParam 0.05/0.10, maxIter 5; đây là cấu hình thử, chưa phải kết quả tối ưu.

## Đồng bộ

Sau một mốc hoàn thành, tải `.ipynb` từ Colab, đặt vào thư mục này, kiểm nội dung rồi commit/push. Bản Drive và bản Git không tự đồng bộ theo cách này. Không commit token, dữ liệu nhạy cảm hoặc output quá lớn.

## Đầu ra

Bảng khảo sát, Parquet, metric và gói mô hình lưu bền bên ngoài repo; notebook ghi vị trí và cách tạo. Hiện chưa có kết quả được xác nhận.
