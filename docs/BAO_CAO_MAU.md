# Báo cáo đồ án — khung để hoàn thiện bằng kết quả thật

Tên đề tài: Xây dựng hệ thống gợi ý phim sử dụng Spark ALS và đánh giá hiệu quả Alluxio trên HDFS, Lustre.
Sinh viên/MSSV/lớp/GV: [điền]. Đây là khung báo cáo, không phải báo cáo kết quả hoàn thành.

## 1. Bài toán, bối cảnh, mục tiêu và phạm vi
Gợi ý Top10 phim chưa đánh giá từ lịch sử; phản hồi đánh giá mới bằng fold-in; đánh giá Alluxio khi truy cập lặp lại dataset. Nêu người dùng dự kiến, đầu vào/đầu ra, giá trị; phạm vi demo và môi trường một máy.

## 2. Dữ liệu
MovieLens 32M, nguồn/điều khoản, dung lượng/định dạng/schema; bản small và sample1M. Điền bảng số dòng trước/sau ETL, độ thưa, phân bố, thiếu/trùng, quy tắc quyết định và lý do. Liên kết notebook/biểu đồ.

## 3. Kiến trúc
Phân biệt Drive lưu artifact, HDFS/Lustre lưu dữ liệu thực nghiệm, Alluxio namespace/cache, Spark xử lý, Python serving, Laravel UI, MongoDB dữ liệu app. Sơ đồ môi trường và luồng batch/on-demand; versions, hardware, tài nguyên. Ghi đúng Lustre đã có hay chưa.

## 4. Phương pháp
Spark DataFrame ETL, Parquet, chia thời gian, ALS explicit, grid, baseline, TopK, fold-in ALS-WR với reg*n; trường hợp mới/fallback, lịch sử chuẩn và mapping IDs. Tái huấn luyện snapshot/activate/rollback.

## 5. Thực nghiệm
Mô tả H0/H1/H2, L0/L1/L2, cold/warm Alluxio, OS cache không kiểm soát, cùng dữ liệu/tác vụ/tài nguyên, >=5 lần. Dán số liệu từ logs thật.

| Backend | Cấu hình | Tác vụ | Số lượt | Median (s) | IQR | Speedup | Cached fraction | Cache/UFS bytes |
|---|---|---|---|---|---|---|---|---|
| [điền sau chạy] | | | | | | | | |

## 6. Đánh giá và thảo luận
RMSE/MAE/coverage, P/R/NDCG@10, so baseline; before/after fold-in nếu thực hiện. Giải thích tác vụ nào Alluxio có lợi, overhead, OS cache, cache capacity, CPU/shuffle; giới hạn cross-hardware và single-node. Không khẳng định tăng độ chính xác vì thay storage.

## 7. Ứng dụng minh họa
Ảnh tìm phim, đánh giá, lịch sử, gợi ý, dashboard; kịch bản mới/đã có hồ sơ, thêm/sửa điểm, restart. Chỉ ra điểm xếp hạng không phải xác suất.

## 8. Kết luận và hướng phát triển
Những mục đã đạt, phần chưa hoàn thành có bằng chứng; cluster nhiều máy/HA, đăng nhập thật, evaluation mở rộng.

## Phụ lục
Repo commit, Drive liên kết được cấp quyền phù hợp, lệnh chạy, manifest/checksum, logs, ảnh thật, phân công và mức đóng góp. Không công khai raw app snapshots có thông tin cá nhân.
