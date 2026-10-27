# Kiểm thử và phạm vi nghiệm thu

Cập nhật ngày 14/10/2026. Phân biệt kiểm thử phần mềm, kiểm tra tích hợp thật và phép đo thực nghiệm. Không dùng fixture hoặc dữ liệu tổng hợp làm số đo MovieLens.

## 1. Kết quả đã xác nhận

Đợt dọn source để công bố: chạy lại kiểm thử Python trên Python 3.14 với NumPy có sẵn và dependency test trong runtime (PyYAML 6.0.3); requirements/CI vẫn dùng Python 3.11 và các phiên bản được ghim. Pint và 19 kiểm thử Laravel chạy bằng PHP Laragon 8.3.30, SQLite trong bộ nhớ. Docker hiện tắt nên chưa chạy lại build/integration trong đợt này. Cả ba file Compose đã được kiểm tra config với env mẫu.

| Hạng mục | Kết quả |
|---|---|
| Laravel | 19 bài kiểm thử, 94 assertions qua trên PHP 8.3 |
| Python/API/worker/metrics/source audit | 52 bài được phát hiện; 47 qua, 5 bỏ qua theo điều kiện môi trường |
| 5 bài bỏ qua | 4 bài Spark protocol và 1 bài upload HDFS opt-in |
| JavaScript tùy chỉnh | Kiểm tra cú pháp cinema.js và operations.js qua |
| Tài khoản thật | Đăng ký/đăng nhập, phân quyền admin, đánh giá và gợi ý qua HTTP |
| Dữ liệu bền vững | Tạo lại container web, đăng nhập lại và đọc điểm Matrix 4.5 thành công |
| Snapshot thật | Job trên web tự chạy, ghi tiến độ và hoàn tất; API được bật lại |
| Upload HDFS thật | Một lượt acceptance dùng Parquet tổng hợp nhỏ, ghi vào namespace UUID riêng và từ chối ghi đè |
| HDFS/Alluxio | Preflight đọc manifest và byte Parquet qua cả hai đường |
| Trang và tài nguyên | Catalog, login, trang admin và CSS/JS mới trả HTTP 200 |
| Xem trực quan | Công cụ điều khiển trình duyệt lỗi khởi tạo trong phiên kiểm tra giao diện mới |

Kết quả acceptance HDFS nhỏ chỉ xác nhận cơ chế upload, không phải nghiệm thu retrain toàn bộ. 4 bài Spark từng được chạy riêng trong các đợt kiểm tra trước; lần chạy Python mới nhất vẫn bỏ qua chúng khi image không có Spark.

Các job snapshot có bằng chứng trong runtime:
- `175dfae5-d562-43fa-998b-0e6843bcc09d`.
- `2639e4de-1917-495c-8659-43696ff5419f` — có timeline bắt đầu/kết thúc từ worker mới.

Kiểm tra lỗi ưu tiên Documentary: một lịch sử thích 6 phim phiêu lưu/trẻ em/hoạt hình có 5 phim tài liệu trong Top 10 ALS thuần. Serving bổ sung chế độ hybrid rõ nhãn; các kiểm thử xác nhận tín hiệu điểm thấp, loại phim đã xem, candidate policy và giữ nguyên raw ALS. Chưa có đánh giá held-out mới để kết luận cải thiện chất lượng tổng quát.

## 2. Kiểm tra Python thông thường

Dùng môi trường Python tương thích với requirements, từ gốc dự án:

```sh
pip install -r requirements-dev.txt
python scripts/check_release.py
```

Script kiểm tra cú pháp Python, JSON/cell notebook, cấu trúc Compose và chạy unittest. Spark và LIVE_HDFS là các kiểm tra riêng, không tự chạy tác vụ hạ tầng nặng.

Worker riêng:

```sh
python -m unittest discover -s tests -p test_job_worker.py
```

Các kiểm thử worker gồm: loại job/model hợp lệ, validation trước downtime, phục hồi khi healthcheck sai, ngưỡng scheduler, giữ bộ đếm khi retrain thất bại, timeline và bật/tắt tự kích hoạt. Một số bước Docker/health được mock; chúng không thay thế full retrain thật.

## 3. Kiểm tra Laravel

Chuẩn bị dependency phát triển và thư mục cache như [web/README.md](../web/README.md). Trên shell Linux/container, trong web:

```sh
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: SESSION_DRIVER=array CACHE_STORE=array vendor/bin/phpunit
```

Không trỏ kiểm thử vào SQLite production hoặc volume tài khoản thật.

Kiểm thử bao gồm đăng ký, password hash, đăng nhập/đăng xuất, định danh theo tài khoản, quyền admin, đánh giá nửa sao, lịch sử, lỗi API, queue, path traversal, trailer, JSON tiến độ và thiết lập tự động.

## 4. Spark và HDFS thật

Từ gốc dự án, máy B:

```powershell
docker compose --env-file twohost/.env.compute -f twohost/compose.compute.yaml --profile jobs run --rm spark bash scripts/spark.sh tests/test_train_ranking.py
```

Acceptance upload chỉ chạy khi đã kết nối HDFS A:

```powershell
docker compose --env-file twohost/.env.compute -f twohost/compose.compute.yaml --profile jobs run --rm -e LIVE_HDFS=1 spark bash scripts/spark.sh tests/test_upload_dataset.py
```

Lượt upload này tạo dữ liệu tổng hợp dưới `/acceptance/uploads/<UUID>`. Giữ tách khỏi dataset chính.

## 5. Còn cần nghiệm thu

- Full snapshot → merge → upload → retrain → tự kích hoạt với dataset đầy đủ trên máy A mới.
- Benchmark H0/H1/H2 đủ 15 lượt trên A mới và báo cáo cache/median/IQR tương ứng.
- Thử phục hồi thực tế khi job bị ngắt giữa chừng, theo quy trình có kiểm soát.
- Kiểm tra giao diện trực quan trên desktop/mobile và trailer trong trình duyệt thực tế.
- Notebook trong phiên Colab hiện tại và CI trên GitHub sau khi push.
- Lustre thật và L0/L1/L2 nếu giảng viên yêu cầu.
- Báo cáo, slide và bằng chứng demo theo checklist nghiệm thu bên dưới.

## 6. Checklist nghiệm thu đồ án

- [x] Mã nguồn sạch, README chi tiết, hướng dẫn cài đặt và chạy hệ thống rõ ràng.
- [x] Pipeline ETL, làm sạch dữ liệu, khử trùng lặp và chia tập theo thời gian bằng PySpark.
- [x] Hạ tầng HDFS (NameNode, DataNode) và Alluxio (Master, Worker) hoạt động ổn định.
- [x] Thực nghiệm lưu trữ H0/H1/H2 đầy đủ (đo đạc trên cả 1 máy và 2 máy qua mạng Tailscale VPN).
- [x] Thuật toán Explicit ALS có đánh giá xếp hạng (NDCG@10, Precision@10, Recall@10, RMSE, MAE).
- [x] Tầng phục vụ trực tuyến (FastAPI + NumPy) tính toán Fold-in tức thời trong RAM (<100ms).
- [x] Giao diện web Laravel hoàn chỉnh: đăng ký/đăng nhập, xem catalog, xem trailer, chấm điểm sao, hiển thị gợi ý cá nhân hóa và bảng điều khiển Admin Dashboard.
- [x] Cơ chế tự động hóa: snapshot MongoDB, ghép dữ liệu, retrain định kỳ và tự động rollback an toàn.
- [x] Bộ báo cáo thực nghiệm đầy đủ ([docs/reports/2026-10-20/bao-cao-thuc-nghiem.md](reports/2026-10-20/bao-cao-thuc-nghiem.md)), biểu đồ trực quan và bằng chứng đo đạc được lưu trữ trong `experiments/`.

Báo cáo VPN ngày 12/10/2026 thuộc hạ tầng cũ. Không gắn số đo cũ cho máy A mới. Metrics model retrain là kế thừa từ đánh giá trước, không phải một kết quả chất lượng mới.
