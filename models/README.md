# Mô hình và phiên bản

Thư mục lưu tài liệu gói ALS, thông tin mapping, manifest và hướng dẫn lấy mô hình. Chưa có mô hình đã huấn luyện.

## Đầu vào

Dữ liệu sạch, split cố định và cấu hình ALS được chọn bằng validation.

## Gói mô hình cần có

- userFactors và itemFactors.
- Mapping ID nguồn/tài khoản/trainingUserId và movieId.
- modelVersion, datasetVersion, rank, regParam, seed và cutoff khi retrain.
- Tham số Spark quan trọng, schema và checksum.
- Metadata phim và lịch sử một số hồ sơ demo nếu cần.

Gói phải chứa factors và mapping nhất quán. Không ghép vector người dùng v1 với vector phim v2, kể cả cùng rank.

## Kiểm tra trước sử dụng

Đối chiếu điểm Python với Spark trên cặp cố định; kiểm Top 10 cùng candidate/filter/tie-break, loại phim đã đánh giá và không trả NaN. Báo phim/người thiếu factor và fallback.

## Phiên bản

Tách mô hình đánh giá với mô hình refit để phục vụ. Sau retrain, kiểm gói mới rồi kích hoạt nguyên tử; vô hiệu vector cá nhân cũ và giữ gói trước để rollback.

## Nơi lưu

Gói lớn lưu Drive `04_Models`; repo giữ manifest/tài liệu phù hợp. Link và dung lượng sẽ bổ sung khi có gói thật. Model package khác với Spark checkpoint: không chỉ dựa vào tệp trung gian huấn luyện để phục vụ API.
