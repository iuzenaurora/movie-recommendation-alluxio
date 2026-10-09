<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;

// Trang chủ mặc định chuyển về danh mục phim
Route::get('/', function () {
    return redirect()->route('movies.index');
});

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::get('/quick-login/{id}', [AuthController::class, 'quickLogin'])->name('auth.quick-login');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Phân hệ 1: Người dùng (User Side)
Route::get('/movies', [MovieController::class, 'index'])->name('movies.index');
Route::get('/movies/{id}', [MovieController::class, 'show'])->name('movies.show');
Route::post('/movies/{id}/rate', [MovieController::class, 'rate'])->name('movies.rate');

Route::get('/recommendations', [RecommendationController::class, 'index'])->name('recommendations.index');
Route::post('/recommendations/recompute', [RecommendationController::class, 'recompute'])->name('recommendations.recompute');

Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
Route::delete('/profile/ratings/{movieId}', [ProfileController::class, 'deleteRating'])->name('profile.delete-rating');

// Phân hệ 2: Quản trị & Big Data (Admin Side)
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/eda', [AdminController::class, 'eda'])->name('eda');
    Route::get('/benchmark', [AdminController::class, 'benchmark'])->name('benchmark');
    Route::get('/versions', [AdminController::class, 'versions'])->name('versions');
    Route::post('/create-snapshot', [AdminController::class, 'createSnapshot'])->name('create-snapshot');
    Route::post('/switch-version', [AdminController::class, 'switchVersion'])->name('switch-version');
});
