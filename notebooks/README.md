# Notebook dữ liệu và huấn luyện ban đầu

Các notebook phục vụ chuẩn bị dữ liệu/model trên Colab. Website đang chạy không cần mở lại notebook mỗi lần đánh giá hoặc retrain; các việc đó do worker thực hiện.

## Các notebook hiện có

| File | Mục đích |
|---|---|
| `00_ChuanBi_Colab.ipynb` | Chuẩn bị môi trường và nguồn mã trên Colab |
| `01_KhaoSat_MovieLens.ipynb` | Khảo sát dữ liệu MovieLens |
| `02_TienXuLy.ipynb` | Làm sạch, xuất Parquet và manifest |
| `03_HuanLuyen_ALS.ipynb` | Huấn luyện, chọn cấu hình, đánh giá và xuất model |

Các notebook clone source từ GitHub; SOURCE_REF mặc định origin/main, có thể thay bằng commit SHA để tái lập. Push source mới trước khi chạy Colab. Không còn cần ZIP source hoặc ZIP bản vá. Đọc cấu hình Drive/DATASET/VERSION ở đầu notebook; bước 00/02 mặc định dữ liệu nhỏ, đổi sang ml-32m và ml32m-full-v1 khi chuẩn bị dữ liệu chính cho bước 03.

Notebook 02 lưu cả thư mục Parquet và ZIP cùng tên VERSION ở 02_Dataset_Processed. Notebook 03 đọc ZIP dataset sạch và xuất model với tên als-colab-<thời điểm>. Notebook 01 khảo sát số dòng, ID, rating lỗi, cặp trùng và thể loại. Source kiểm tra cú pháp các cell; các notebook mới vẫn cần chạy xác nhận trong phiên Colab thực tế.

## Đầu vào và đầu ra

- CSV gốc: `data/raw/ml-32m/`.
- Dataset sạch: `data/processed/ml32m-full-v1/`, gồm `movies/`, `ratings/`, `split/` và `manifest.json`.
- Model: thư mục phiên bản mới trong `models/`; không ghi đè `current` khi train.
- Nhật ký và metrics: lưu theo từng run ở `experiments/`.

## Chính sách huấn luyện

Mã chính nằm ở [src/train.py](../src/train.py). Lưới mặc định, seed và chính sách ứng viên được kiểm tra trong parser/source trước khi chạy. Chọn cấu hình theo validation; không dùng test để chọn tham số.

ALS và baseline popularity cần dùng cùng chính sách ứng viên. Khi tính Recall, không tự bỏ các phim relevant bị chính sách ứng viên loại khỏi mẫu số. Candidate policy và checksum được xuất cùng model.

[src/retrain.py](../src/retrain.py) dùng cấu hình đã chọn và giữ chính sách ứng viên của model cha. Metrics cũ được ghi nhãn kế thừa; retrain serving không thay thế một vòng đánh giá held-out mới.

## Đưa kết quả về chương trình

1. Kiểm tra manifest, checksum và các file model.
2. Đưa dataset/model về máy B theo cấu trúc ở README gốc.
3. Thiết lập HDFS theo [hướng dẫn hai máy](../twohost/README.md).
4. Nếu app đã chạy, đặt model vào thư mục `als-...` và kích hoạt qua admin.
5. Nếu cài mới chưa có model, chuẩn bị gói hợp lệ trong `models/current/` trước khi bật app.

Colab không phải nơi đo benchmark HDFS/Alluxio của hai máy. Lưu kết quả Colab và kết quả hạ tầng lab riêng, kèm phiên bản dữ liệu/model.
