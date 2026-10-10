# Website Laravel

Thư mục dành cho website PHP/Laravel, tích hợp MongoDB và dịch vụ Python. Chưa có ứng dụng hoặc cấu hình cài đặt đã kiểm thử.

## Chức năng dự kiến

- Chọn hồ sơ demo hoặc tài khoản.
- Tìm phim, xem chi tiết, lọc và phân trang.
- Xem lịch sử, chấm và sửa rating.
- Gọi gợi ý Top 10 theo yêu cầu.
- Hiển thị trạng thái lưu, lỗi/fallback và phiên bản mô hình/lịch sử.
- Dashboard dữ liệu, mô hình và hiệu năng lưu trữ.
- Theo dõi snapshot, retrain và phiên bản hiện hành.

## Trách nhiệm backend

Xác thực hồ sơ, kiểm đầu vào, lưu rating vào MongoDB và lấy lịch sử chuẩn trước khi gọi Python. Không tin lịch sử tùy ý do trình duyệt gửi.

Một cặp tài khoản–phim có một rating hiện hành; sửa điểm là cập nhật. historyVersion tăng nhất quán khi lịch sử đổi. Tách ID tài khoản với trainingUserId qua mapping.

## Luồng rating

Chấm điểm → Laravel kiểm tra/lưu MongoDB → gọi fold-in → tính lại gợi ý. Nếu đã lưu nhưng API lỗi, thông báo đúng trạng thái và cho thử lại; không báo mô hình đã cập nhật chỉ vì DB ghi thành công.

## Dashboard

Dùng CSV/log thật, gắn điều kiện đo và run_id. Chỉ gọi dashboard là live khi lấy trạng thái thật. Trang benchmark lưu trữ tách khỏi nút gợi ý đang dùng factors trong RAM.

## Triển khai

Dự kiến chạy trên Windows hoặc Docker. Phiên bản Laravel/PHP, connector MongoDB, cấu hình kết nối, cổng và thao tác chạy cần chốt khi triển khai. Không commit `.env` thật, vendor hoặc node_modules; cấu hình mẫu không chứa bí mật.
<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
