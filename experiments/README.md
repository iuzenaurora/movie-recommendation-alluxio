# Thực nghiệm và đánh giá

Thư mục lưu protocol, CSV số đo nhỏ, biểu đồ và mô tả điều kiện thực nghiệm. Chưa có kết quả đo thật.

## Sáu cấu hình lưu trữ

| Mã | Đường đọc | Cache Alluxio đầu lượt |
|---|---|---|
| H0 | Spark → HDFS | Không sử dụng |
| H1 | Spark → Alluxio → HDFS | Rỗng |
| H2 | Spark → Alluxio → HDFS | Đã nạp; ghi tỷ lệ nằm trong cache |
| L0 | Spark → Lustre | Không sử dụng |
| L1 | Spark → Alluxio → Lustre | Rỗng |
| L2 | Spark → Alluxio → Lustre | Đã nạp; ghi tỷ lệ nằm trong cache |

## Tác vụ

- S1: đọc giá trị rating/timestamp và tính tổng; không dùng count đơn thuần để tuyên bố đọc toàn bộ dữ liệu.
- S2: nhóm theo movieId, tính số rating và điểm trung bình.
- S3: chuỗi ba hoặc năm Spark application độc lập, chốt một số lượng trước khi đo.
- S4 tùy chọn: ALS fit với cùng dữ liệu và tham số.

## Điều kiện đối chứng

Dùng cùng snapshot, file layout và cấu hình Spark. Công bố CPU/RAM, backend, cache quota, protocol OS cache và lịch lượt chạy. Đo thời gian action thực thi, tách startup và copy Drive. S1/S2 không dùng user cache/persist Spark.

Chạy ít nhất năm lượt cho mỗi điểm S1/S2, luân phiên thứ tự và kiểm cache trước/sau. S3 reset trước cả chuỗi, không reset giữa các application. So sánh trên cùng máy hoặc điều kiện tương đương; khác phần cứng chỉ phân tích hiệu quả cache trong từng backend.

## Chỉ số

- Lưu trữ: trung vị thời gian, IQR, speedup so với direct cùng backend, byte cache/UFS, cache fraction, RAM, CPU, swap/lỗi và tính đúng.
- Mô hình: RMSE, MAE, Precision@10, Recall@10, NDCG@10, coverage.
- Ứng dụng: thời gian lưu, fold-in, tính điểm và tổng phản hồi; tách khỏi hiệu quả Alluxio khi dùng factors trong RAM.

Mỗi lượt cần run_id, environment_id, datasetVersion, workload, cấu hình và kết quả logic. Nếu counter không đo được, ghi rõ thiếu; không suy ra hit rate chỉ từ thời gian.

## Sản phẩm

CSV số đo gốc, bảng tổng hợp, biểu đồ và giải thích cả lợi ích lẫn overhead. Log lớn lưu Drive `05_Experiments`. Không đưa số giả vào dashboard hoặc README.
