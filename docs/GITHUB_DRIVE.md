# Chuẩn bị source và push GitHub

Git lưu source, cấu hình mẫu, notebook, tài liệu và kiểm thử. Dữ liệu/model/kết quả thực nghiệm/runtime được giữ trên máy và không đưa vào commit mặc định.

## 1. Kiểm tra trước khi commit

Tại gốc dự án:

```powershell
python scripts/check_source.py
git status --short
git diff --check
git diff --stat
```

Lệnh check_source đọc cả file đã track và file mới chưa bị ignore; từ chối một số dạng token/private key, env thật, dữ liệu/model, notebook output và file source trên 5 MiB. Đây là quét heuristic, vẫn cần review Changes. Không in giá trị secret khi phát hiện.

File đã được Git track trước đây không tự biến mất khi thêm .gitignore. Nếu có cảnh báo file nhạy cảm đã track, gỡ khỏi index và xử lý credential liên quan trước khi công bố; không chỉ xóa dòng khỏi bản hiện tại rồi bỏ qua lịch sử.

## 2. Dùng GitHub Desktop

1. Mở repository hiện có trong GitHub Desktop, không tạo repo lồng trong thư mục này.
2. Review Changes: chỉ source, cấu hình mẫu, tài liệu và kiểm thử. Các thư mục dữ liệu phát sinh không xuất hiện.
3. Ghi commit message phù hợp và chọn Commit to main hoặc nhánh làm việc của bạn.
4. Chọn Push origin.
5. Mở tab Actions trên GitHub để xem workflow Source checks.

Mã hiện tại dùng Laravel, FastAPI, MongoDB, SQLite, Spark và Alluxio. Cả benchmark scan/group và benchmark ALS được giữ lại. Không xóa một bộ benchmark chỉ vì đã có bộ kia.

Nếu dùng CLI, sau khi đã review:

```powershell
git add --all
git diff --cached --stat
git commit -m "Clean source and document MovieLab pipeline"
git push origin main
```

Thay main bằng nhánh thực tế nếu đang làm trên nhánh khác. Việc kiểm tra/đóng gói source không tự commit hoặc push.

## 3. ZIP source sạch

```powershell
python scripts/check_source.py --archive runtime/MovieLab-source.zip
```

ZIP chỉ chứa file ứng viên Git hiện tại; không chứa dữ liệu/model, .env thật, tài khoản, token, dependencies hay log. Lệnh từ chối ghi đè ZIP đã tồn tại. Đây là bản source để review/chuyển giao, không phải bản cài đã gồm dataset/model.

## 4. Lưu dữ liệu ngoài Git

| Nội dung | Nơi lưu |
|---|---|
| CSV MovieLens | data/raw và bản sao lưu riêng |
| Parquet + manifest | data/processed, HDFS và bản sao lưu riêng |
| Model packages | models và bản sao lưu riêng |
| Log, số đo, báo cáo gốc | experiments và bản sao lưu riêng |
| Credential/token/tài khoản | runtime và Docker volumes, không công bố |

Không đồng bộ trực tiếp Docker volumes hoặc đĩa VM đang chạy bằng Drive. Git không thay thế sao lưu volumes.

## 5. Colab và Drive

Notebook lấy source từ GitHub theo SOURCE_REF; push source mới trước khi chạy Colab. Dùng commit SHA để tái lập. Không còn cần BIGDATA_ALLUXIO_PROJECT.zip hoặc ALS_RANKING_FIX.zip.

Drive có thể giữ các thư mục:
01_Dataset_Raw, 02_Dataset_Processed, 03_Notebooks, 04_Models, 05_Experiments, 06_Documents.

Chỉ dataset/model được trao đổi bằng ZIP riêng. Ghi commit, datasetVersion và modelVersion cho mỗi run. Không tải credential từ runtime lên thư mục source công khai.

## 6. Giới hạn của việc chuẩn bị source

Source checks không đồng nghĩa toàn bộ đồ án đã nghiệm thu. Spark/HDFS thật, benchmark 15 lượt, full retrain, Colab và Lustre (nếu bắt buộc) cần được kiểm chứng riêng. Xem [KIEM_THU.md](KIEM_THU.md).
