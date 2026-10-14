<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecommendationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('movies.index'));
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.post');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.post');
});
Route::get('/movies', [MovieController::class, 'index'])->name('movies.index');
Route::get('/movies/{id}', [MovieController::class, 'show'])->whereNumber('id')->name('movies.show');
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/movies/{id}/rate', [MovieController::class, 'rate'])->whereNumber('id')->name('movies.rate');
    Route::get('/recommendations', [RecommendationController::class, 'index'])->name('recommendations.index');
    Route::post('/recommendations/recompute', [RecommendationController::class, 'recompute'])->name('recommendations.recompute');
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::delete('/profile/ratings/{movieId}', [ProfileController::class, 'deleteRating'])->whereNumber('movieId')->name('profile.delete-rating');
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/eda', [AdminController::class, 'eda'])->name('eda');
        Route::get('/benchmark', [AdminController::class, 'benchmark'])->name('benchmark');
        Route::get('/versions', [AdminController::class, 'versions'])->name('versions');
        Route::get('/jobs', [PipelineController::class, 'index'])->name('jobs');
        Route::get('/pipeline/status', [PipelineController::class, 'status'])->name('pipeline.status');
        Route::post('/jobs', [PipelineController::class, 'store'])->name('jobs.store');
        Route::get('/jobs/{id}', [PipelineController::class, 'show'])->whereUuid('id')->name('jobs.show');
        Route::post('/automation', [PipelineController::class, 'automation'])->name('automation');
        Route::post('/trailers', [PipelineController::class, 'trailer'])->name('trailers');
    });
});
