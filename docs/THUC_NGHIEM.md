# Kế hoạch đánh giá và dữ liệu phải thu

## Chất lượng mô hình
Giữ train/validation/test bất biến; validation chọn cấu hình, test một lần sau chọn.
RMSE/MAE chỉ trên cặp có dự đoán; báo scoredRows/totalRows và coverage. Baseline movie mean so trên cùng cặp.
Precision/Recall/NDCG@10: relevant rating>=4, candidate gồm toàn bộ item có factor từ train, loại lịch sử train+validation khi test; phim test chưa có factor vẫn nằm trong mẫu số recall. Người không có relevant test hoặc user factor bị loại nhưng phải báo số mẫu/được đánh giá.
Gói tự tính metrics cơ bản. Chưa có thực nghiệm tự động before/after fold-in trên holdout: nếu làm, tiết lộ 1/3/5 đánh giá đầu của holdout để fold-in, đánh giá trên phần chưa tiết lộ; dùng cùng tập ứng viên và cùng người dùng trước/sau. Không dùng điểm đã tiết lộ làm ground truth đánh giá.

## Lưu trữ
H0/H1/H2 và L0/L1/L2, hai tác vụ scan/group, >=5 lượt, median/IQR, speedup so cùng backend.
Lưu datasetVersion, SHA256 file, OS/hardware, image digest, Spark/Java/Alluxio/HDFS/Lustre versions, cores/RAM/cache/partition count, OS cache state, worker count, errors.
Không dùng DataFrame persist cho scan/group. Spark startup và data upload tách khỏi timing; load cache tách riêng. H1 cache Alluxio 0%, không khẳng định OS cache rỗng. H2 kiểm chứng cached fraction, không tự giả định 100%.
Byte hit ratio = delta cache bytes / (delta cache bytes + delta UFS bytes), nếu bộ đếm cùng phạm vi/đơn vị và tổng>0. Kiểm tra tên bộ đếm thực tế; không suy từ tốc độ hay du. Dùng docker stats/logs để ghi RAM/CPU, quan sát IO/swap; không gọi mạng nội bộ Docker là mạng nhiều máy vật lý.

## ALS như một workload bổ sung
Chạy cùng rank/reg/iterations/input tại direct vs Alluxio, ghi fit time riêng read time. ALS có cơ chế persist nội bộ và shuffle, nên fit nhanh/chậm không hoàn toàn do storage. Không chỉ đo một lần và không thay hyperparameter giữa hai nhánh.

## Các thử nghiệm mở rộng chưa tự động trong gói
- Chuỗi 3–5 Spark applications dùng chung dataset, reset cache một lần trước cả chuỗi; đo tổng thời gian để quan sát tái sử dụng cache giữa ứng dụng.
- Đo fold-in holdout theo quy tắc trên.
- API p50/p95/p99 với workload cố định; đây là đánh giá ứng dụng, không phải kết quả cache Alluxio khi vectors đã ở RAM.

## Kết luận
Điền kết luận sau số đo thật. Alluxio có thể ít lợi ích khi dữ liệu nhỏ/OS cache nóng, tăng chi phí khi cache miss, hoặc ít ảnh hưởng tới ALS bị CPU/shuffle giới hạn. Một worker không chứng minh khả năng scale nhiều máy hay HA.
