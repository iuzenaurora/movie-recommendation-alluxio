# Công cụ vận hành

Sử dụng thường ngày: mở [MovieLab.pyw](../MovieLab.pyw), sau đó thao tác trên web. Không cần gọi từng script pipeline bằng tay.

| Script | Chức năng |
|---|---|
| `start_app.py` | Khởi động app, tạo cấu hình lần đầu và bật một worker nền |
| `job_worker.py` | Scheduler, snapshot, retrain, benchmark, activate/rollback, timeline |
| `spark.sh` | spark-submit với Alluxio client và cấu hình Hadoop |
| `upload_dataset.py` | Upload staging lên HDFS, kiểm tra và từ chối ghi đè |
| `verify_dataset.py` | Đối chiếu Parquet local với manifest |
| `validate_package.py` | Checksum, shape và tính hợp lệ của gói model |
| `check_release.py` | Kiểm tra source, notebook, Compose và unittest |
| `check_source.py` | Kiểm tra file chuẩn bị công bố và tùy chọn tạo ZIP source sạch |
| `download.py` | Tải dataset theo lựa chọn của CLI |
| `summarize_als.py` | Tổng hợp timing benchmark ALS |
| `summarize_alluxio_metrics.py` | Kiểm tra/tổng hợp cache và bộ đếm Alluxio |
| `summarize.py` | Tổng hợp workload benchmark scan/group |

Các `.ps1` activate/benchmark được giữ cho chạy kỹ thuật thủ công và đối chiếu thực nghiệm cũ. Worker hiện tự thực hiện các bước của mình, không yêu cầu người dùng chạy các file này.

Job và log ở `runtime/jobs/`; lịch ở `runtime/automation.json`; trạng thái scheduler ở `runtime/automation-state.json`. Worker chỉ nhận loại job cố định. Job bị ngắt được đánh dấu lỗi để kiểm tra thay vì chạy lại âm thầm.

Hướng dẫn đầy đủ: [vận hành](../HUONG_DAN_CHAY.md), [hai máy](../twohost/README.md).
