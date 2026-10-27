# Bộ Kiểm Thử Tự Động (MovieLab Test Suite)

Thư mục này chứa toàn bộ các bài kiểm thử đơn vị (Unit Tests) và tích hợp (Integration Tests) cho các thành phần Python, thuật toán gợi ý, API và Worker.

---

## 1. Danh sách các tệp kiểm thử

| Tệp kiểm thử | Thành phần kiểm thử |
|---|---|
| `test_recommendation.py` | Kiểm thử thuật toán Fold-in, xếp hạng Top K, lọc phim đã xem và chính sách ứng viên. |
| `test_evaluation_protocol.py` | Kiểm thử giao thức tính toán chỉ số chất lượng: Precision@10, Recall@10, NDCG@10, RMSE, MAE. |
| `test_api.py` | Kiểm thử các endpoint của FastAPI (sử dụng mongomock để giả lập database MongoDB). |
| `test_api_benchmark.py` | Kiểm thử công cụ đo tải HTTP API và xử lý dữ liệu độ trễ (latency percentiles). |
| `test_job_worker.py` | Kiểm thử logic điều phối tiến trình nền: snapshot, scheduler, rollback và healthcheck. |
| `test_metrics_summary.py` | Kiểm thử tổng hợp chỉ số I/O cache Alluxio (UFS/cache bytes) và tính toán median/IQR. |
| `test_summarize_als.py` | Kiểm thử bộ tổng hợp thời gian huấn luyện ALS cho H0/H1/H2. |
| `test_source_publication.py` | Kiểm thử bảo mật mã nguồn trước khi đẩy lên Git (chống rò rỉ secret, key, token). |
| `test_train_ranking.py` | Kiểm thử Spark ALS và đánh giá xếp hạng trên tập Validation/Test. |
| `test_upload_dataset.py` | Kiểm thử kiểm tra checksum và tải dataset lên HDFS. |

---

## 2. Hướng dẫn chạy kiểm thử

Từ thư mục gốc dự án:

```powershell
# Chạy toàn bộ kiểm thử đơn vị Python
python -m unittest discover -s tests

# Chạy một bài kiểm thử cụ thể
python -m unittest tests.test_recommendation
python -m unittest tests.test_evaluation_protocol
python -m unittest tests.test_metrics_summary
python -m unittest tests.test_source_publication
```

Kiểm thử Laravel (PHPUnit) được thực hiện trong thư mục `web/` theo hướng dẫn tại [web/README.md](../web/README.md).
