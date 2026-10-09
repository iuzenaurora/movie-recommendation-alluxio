# Hệ thống gợi ý phim sử dụng Alluxio

Đồ án xây dựng hệ thống gợi ý phim từ lịch sử đánh giá MovieLens, sử dụng Spark ALS, Alluxio, HDFS, Lustre, Laravel và MongoDB.

## Trạng thái

Dự án đang ở bước chuẩn bị cấu trúc thư mục và tài liệu. Các cấu hình triển khai, mô hình, API và kết quả thực nghiệm chưa được xác nhận hoàn thành. README mô tả thiết kế dự kiến, không phải bằng chứng hệ thống đã chạy.

## Mục tiêu

- Xử lý và chuẩn hóa dữ liệu đánh giá phim.
- Huấn luyện ALS và tính Top 10 theo yêu cầu, loại phim đã được người dùng đánh giá.
- Lưu đánh giá mới bằng MongoDB, cập nhật vector cá nhân bằng fold-in.
- Thực hiện một đợt huấn luyện lại và chuyển phiên bản mô hình.
- Đánh giá tác động của Alluxio đối với truy cập dữ liệu bằng các phép đo có đối chứng.

## Thành phần và môi trường dự kiến

| Thành phần | Vai trò | Môi trường |
|---|---|---|
| MovieLens | Dữ liệu phim và đánh giá | Google Drive và nguồn lưu trữ thực nghiệm |
| PySpark / ALS | Tiền xử lý, huấn luyện và thực nghiệm | Colab và Linux |
| HDFS | Nguồn lưu trữ bền thứ nhất | Dự kiến container Linux |
| Lustre | Nguồn lưu trữ bền thứ hai | Linux có kernel/module phù hợp; chưa chốt |
| Alluxio | Namespace và cache dữ liệu cho Spark | Dự kiến container Linux |
| Python API | Tính điểm phim và fold-in | Windows hoặc Docker |
| MongoDB | Tài khoản, phim, đánh giá hiện hành | Windows hoặc Docker |
| Laravel | Website và điều phối yêu cầu | Windows hoặc Docker |

Docker Desktop với WSL 2 là phương án phát triển dự kiến trên Windows; cần kiểm tra điều kiện ảo hóa. Lustre cần kiểm tra riêng về kernel và module, không xem một thư mục thông thường hoặc container bất kỳ là Lustre.

Máy phát triển dự kiến: Windows, Intel Core i5-1035G1, RAM 16 GB, Intel UHD Graphics. Cấu hình tài nguyên thực nghiệm sẽ được ghi riêng sau khi kiểm tra.

## Luồng hoạt động

1. MovieLens → khảo sát/làm sạch → Parquet → HDFS/Lustre.
2. Spark đọc trực tiếp hoặc qua Alluxio để xử lý và huấn luyện.
3. Xuất gói mô hình gồm factors, mapping và manifest.
4. Laravel gọi Python API để tính gợi ý; MongoDB lưu lịch sử hiện hành.
5. Đánh giá mới → lưu → fold-in → tính lại Top 10.
6. Snapshot đánh giá → đầu vào qua Alluxio → retrain → chuyển modelVersion.

Fold-in và tính điểm bằng vector trong RAM được đo riêng. Không mặc định gán độ trễ gợi ý cho hiệu quả Alluxio.

## Cấu trúc repository

| Thư mục | Nội dung |
|---|---|
| [data](data/README.md) | Nguồn dữ liệu, cách tải, schema và mô tả snapshot |
| [docs](docs/README.md) | Kiến trúc, môi trường, hướng dẫn và báo cáo |
| [experiments](experiments/README.md) | Protocol, số đo, log nhỏ và biểu đồ |
| [infra](infra/README.md) | Cấu hình hạ tầng và hướng dẫn vận hành |
| [models](models/README.md) | Tài liệu gói mô hình và phiên bản |
| [notebooks](notebooks/README.md) | Notebook dữ liệu, huấn luyện và đánh giá |
| [serving](serving/README.md) | Dịch vụ Python phục vụ gợi ý |
| [web](web/README.md) | Website Laravel và tích hợp MongoDB/API |

## Dữ liệu và lưu trữ

Phát triển bằng MovieLens Latest Small trước, sau đó thử mẫu khoảng một triệu đánh giá từ MovieLens 32M. Tăng quy mô theo tài nguyên thực tế; quy mô huấn luyện và benchmark phải ghi riêng.

Thư mục Drive dự kiến: `DoAn_GoiYPhim_Alluxio`, gồm `01_Dataset_Raw`, `02_Dataset_Processed`, `03_Notebooks`, `04_Models`, `05_Experiments`, `06_Documents`.

Repo lưu mã nguồn, notebook, cấu hình mẫu và kết quả nhỏ. Dataset lớn, gói mô hình lớn, cache, dữ liệu volume và đĩa máy ảo lưu bên ngoài repo. Không commit mật khẩu, token hoặc `.env` chứa cấu hình thật. Quy tắc `.gitignore` cần được bổ sung theo từng thành phần.

## Thực nghiệm dự kiến

Sáu đường đọc: HDFS trực tiếp; Alluxio–HDFS cache rỗng/đã nạp; Lustre trực tiếp; Alluxio–Lustre cache rỗng/đã nạp. Đo tác vụ đọc giá trị, tổng hợp theo phim và đọc lặp giữa các Spark application.

Đánh giá mô hình: RMSE, MAE, Precision@10, Recall@10, NDCG@10 và coverage. Chất lượng gợi ý và hiệu năng lưu trữ là hai nhóm kết quả riêng.

## Hướng dẫn chạy

Chưa có quy trình đã kiểm thử. Khi triển khai, cập nhật phiên bản, điều kiện môi trường và thao tác chạy trong `infra`, `notebooks`, `serving`, `web` và `docs`. Không ghi lệnh hoặc kết quả chưa kiểm tra như thông tin đã xác nhận.

## Theo dõi tiến độ

- [ ] Chuẩn bị Drive, Colab và repository.
- [ ] Thống nhất schema và mapping ID.
- [ ] Kiểm tra hạ tầng bằng tệp nhỏ.
- [ ] Làm sạch dữ liệu và xuất Parquet.
- [ ] Huấn luyện, đánh giá và xuất ALS.
- [ ] Tích hợp website, API và MongoDB.
- [ ] Hoàn thành fold-in và retrain.
- [ ] Đo thực nghiệm, phân tích và hoàn thiện tài liệu.

Các ô chưa được đánh dấu vì chưa xác nhận hoàn thành.
