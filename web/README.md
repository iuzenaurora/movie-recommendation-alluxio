# Web Laravel

Giao diện khám phá phim và trung tâm điều hành MovieLab. Chạy bằng Docker theo [hướng dẫn chung](../HUONG_DAN_CHAY.md); không cần cài PHP trên Windows.

## Cấu trúc

| Đường dẫn | Chức năng |
|---|---|
| `app/Http/Controllers/` | Auth, phim, đánh giá, gợi ý, admin và pipeline |
| `app/Http/Middleware/AccountContext.php` | Đồng bộ thông tin tài khoản đăng nhập |
| `app/Models/User.php` | Tài khoản web và quyền |
| `app/Services/ApiService.php` | Gọi FastAPI bằng service token |
| `app/Services/JobQueue.php` | Ghi các job hợp lệ vào runtime |
| `resources/views/` | Blade: phim, tài khoản, admin, bảo trì |
| `public/css/cinema.css` | Giao diện và hiệu ứng 3D |
| `public/js/` | Hiệu ứng thẻ phim và polling điều hành |
| `routes/web.php` | Route và middleware xác thực |
| `routes/console.php` | Bootstrap/promote admin |
| `config/recommender.php`, `config/trailers.php` | Đường dẫn runtime/model và trailer mẫu |
| `database/migrations/` | Schema tài khoản SQLite |
| `tests/Feature/` | Luồng đăng nhập, đánh giá, quyền và pipeline |
| `Dockerfile`, `start.sh` | Build và khởi động web |

## Tài khoản và phân quyền

Tài khoản lưu trong SQLite, mật khẩu băm bằng Laravel. UUID `app_user_id` ổn định liên kết sang hồ sơ gợi ý MongoDB. Người dùng không tự gán role admin khi đăng ký.

Catalog và trailer công khai. Đánh giá, lịch sử và gợi ý cần đăng nhập. Các endpoint admin kiểm tra role phía server, kể cả endpoint JSON cập nhật tiến độ.

SQLite và application key nằm trong volume `web-storage`. `start.sh` chạy migration, tái sử dụng key và tạo admin ban đầu khi cần. Thông tin admin nằm ở `runtime/admin-credentials.json`, không hardcode trong source.

## Tự động hóa

Trang `/admin/jobs` đọc heartbeat, lịch scheduler, manifest model và job. JavaScript lấy trạng thái mỗi 5 giây từ `/admin/pipeline/status`; trang chi tiết job đọc thêm phần cuối log.

Web ghi yêu cầu job; worker trên máy B thực thi Docker. Web không được mount Docker socket và không chấp nhận lệnh shell tùy ý. Cài đặt tự động gồm lịch benchmark, ngưỡng retrain và tự kích hoạt model.

## Giao diện

Blade tải Tailwind và Lucide qua CDN. CSS/JS tùy chỉnh nằm trực tiếp trong `public/`; các thay đổi này không cần Vite build. Máy demo cần Internet cho CDN, poster và trailer. Hiệu ứng chuyển động tôn trọng `prefers-reduced-motion`.

Sau khi sửa source, từ gốc dự án chạy starter với `--build` rồi tải lại trang. `package.json` và Vite vẫn được giữ cho phát triển frontend; chúng không tham gia đường chạy giao diện hiện tại.

## Kiểm thử

Cài dependency phát triển bằng Composer trong môi trường PHP 8.3 có SQLite. Chuẩn bị thư mục `storage/framework/views`, `sessions`, `cache/data` và `storage/logs`. Sau đó chạy tại `web/`:

```sh
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: SESSION_DRIVER=array CACHE_STORE=array vendor/bin/phpunit
```

Lệnh trên dùng shell Linux/container. Các biến môi trường testing cần được đặt khi chạy trong image Docker vốn mặc định APP_ENV=local. Không dùng database đang chạy để chạy kiểm thử.
