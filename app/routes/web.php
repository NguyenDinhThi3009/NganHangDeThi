<?php

use App\Http\Controllers\Auth\DangNhapController;
use Illuminate\Support\Facades\Route;

// Chưa đăng nhập
Route::middleware('guest')->group(function () {
    Route::get('/dang-nhap', [DangNhapController::class, 'hienThiForm'])->name('login');
    Route::post('/dang-nhap', [DangNhapController::class, 'dangNhap'])->name('dang-nhap');
});

// Đã đăng nhập
Route::middleware('auth')->group(function () {
    Route::post('/dang-xuat', [DangNhapController::class, 'dangXuat'])->name('dang-xuat');

    Route::get('/', fn () => redirect()->route('trang-chu'));
    Route::view('/trang-chu', 'trang-chu')->name('trang-chu');

    // Ví dụ phân quyền: chỉ Quản trị viên vào được (thay bằng chức năng thật ở các bước sau)
    Route::middleware('vaitro:Quản trị viên')->prefix('quan-tri')->group(function () {
        Route::get('/', fn () => 'Khu vực dành cho Quản trị viên')->name('quan-tri');
    });
});
