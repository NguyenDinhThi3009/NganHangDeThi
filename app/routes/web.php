<?php

use App\Http\Controllers\Auth\DangNhapController;
use App\Http\Controllers\CauHoiController;
use App\Http\Controllers\CauHoiQuanLyController;
use App\Http\Controllers\ChuongTrinhDaoTaoController;
use App\Http\Controllers\HocPhanController;
use App\Http\Controllers\MaTranDeThiController;
use App\Http\Controllers\NhapBanMoTaController;
use App\Http\Controllers\TaiLieuController;
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

    // Tài liệu học phần
    Route::middleware('vaitro:Giảng viên,Quản trị viên,Phòng ĐT&ĐBCLGD')
        ->prefix('tai-lieu')
        ->name('tai-lieu.')
        ->group(function () {
            Route::get('/', [TaiLieuController::class, 'index'])->name('index');
            Route::get('/tai-len', [TaiLieuController::class, 'create'])->name('create');
            Route::post('/', [TaiLieuController::class, 'store'])->name('store');

            // Xử lý lại tài liệu lỗi: chỉ Quản trị viên
            Route::post('/{taiLieu}/xu-ly-lai', [TaiLieuController::class, 'xuLyLai'])
                ->middleware('vaitro:Quản trị viên')
                ->name('xu-ly-lai');
        });

    // Câu hỏi: sinh bằng AI, danh sách, chi tiết
    Route::middleware('vaitro:Giảng viên,Quản trị viên')
        ->prefix('cau-hoi')
        ->name('cau-hoi.')
        ->group(function () {
            Route::get('/', [CauHoiController::class, 'index'])->name('index');
            Route::get('/sinh', [CauHoiController::class, 'formSinh'])->name('sinh');
            Route::post('/sinh', [CauHoiController::class, 'batDauSinh'])->name('bat-dau-sinh');
            Route::post('/xoa-nhieu', [CauHoiQuanLyController::class, 'xoaNhieu'])->name('xoa-nhieu');
            Route::get('/{cauHoi}', [CauHoiController::class, 'show'])->name('show');
            Route::get('/{cauHoi}/sua', [CauHoiQuanLyController::class, 'sua'])->name('sua');
            Route::put('/{cauHoi}', [CauHoiQuanLyController::class, 'capNhat'])->name('cap-nhat');
            Route::post('/{cauHoi}/duyet', [CauHoiQuanLyController::class, 'duyet'])->name('duyet');
            Route::post('/{cauHoi}/tu-choi', [CauHoiQuanLyController::class, 'tuChoi'])->name('tu-choi');
            Route::delete('/{cauHoi}', [CauHoiQuanLyController::class, 'xoa'])->name('xoa');
        });

    // Ma trận đề thi (UC-04): giảng viên và Quản trị viên
    Route::middleware('vaitro:Giảng viên,Quản trị viên')
        ->prefix('ma-tran')
        ->name('ma-tran.')
        ->group(function () {
            Route::get('/', [MaTranDeThiController::class, 'index'])->name('index');
            Route::get('/tao', [MaTranDeThiController::class, 'create'])->name('create');
            Route::post('/', [MaTranDeThiController::class, 'store'])->name('store');
            Route::get('/{maTran}', [MaTranDeThiController::class, 'show'])->name('show');
            Route::get('/{maTran}/sua', [MaTranDeThiController::class, 'edit'])->name('edit');
            Route::put('/{maTran}', [MaTranDeThiController::class, 'update'])->name('update');
            Route::post('/{maTran}/chot', [MaTranDeThiController::class, 'chot'])->name('chot');
            Route::post('/{maTran}/bo-chot', [MaTranDeThiController::class, 'boChot'])->name('bo-chot');
            Route::delete('/{maTran}', [MaTranDeThiController::class, 'destroy'])->name('destroy');
        });

    // Học phần: chỉ Quản trị viên (UC-14), nhập từ đề cương chi tiết
    Route::middleware('vaitro:Quản trị viên')
        ->prefix('hoc-phan')
        ->name('hoc-phan.')
        ->group(function () {
            Route::get('/', [HocPhanController::class, 'index'])->name('index');
            Route::get('/nhap-tu-de-cuong', [HocPhanController::class, 'formNhap'])->name('nhap');
            Route::post('/nhap-tu-de-cuong', [HocPhanController::class, 'docDeCuong'])->name('doc-de-cuong');
            Route::get('/nhap-tu-de-cuong/{token}', [HocPhanController::class, 'xemTruoc'])->name('xem-truoc');
            Route::post('/nhap-tu-de-cuong/{token}', [HocPhanController::class, 'xacNhan'])->name('xac-nhan');
        });

    // Chương trình đào tạo và PLO: chỉ Quản trị viên (UC-14)
    Route::middleware('vaitro:Quản trị viên')
        ->prefix('chuong-trinh')
        ->name('chuong-trinh.')
        ->group(function () {
            Route::get('/', [ChuongTrinhDaoTaoController::class, 'index'])->name('index');
            Route::get('/tao', [ChuongTrinhDaoTaoController::class, 'create'])->name('create');
            Route::post('/', [ChuongTrinhDaoTaoController::class, 'store'])->name('store');
            Route::get('/{chuongTrinh}/sua', [ChuongTrinhDaoTaoController::class, 'edit'])->name('edit');
            Route::put('/{chuongTrinh}', [ChuongTrinhDaoTaoController::class, 'update'])->name('update');
            Route::delete('/{chuongTrinh}', [ChuongTrinhDaoTaoController::class, 'destroy'])->name('destroy');
        });

    // Nhập chương trình đào tạo và PLO từ Bản mô tả CTĐT (PDF/bản scan), chỉ Quản trị viên
    Route::middleware('vaitro:Quản trị viên')
        ->prefix('ban-mo-ta')
        ->name('ban-mo-ta.')
        ->group(function () {
            Route::get('/', [NhapBanMoTaController::class, 'form'])->name('nhap');
            Route::post('/', [NhapBanMoTaController::class, 'tai'])->name('tai');
            Route::get('/{token}', [NhapBanMoTaController::class, 'xemTruoc'])->name('xem-truoc');
            Route::post('/{token}', [NhapBanMoTaController::class, 'xacNhan'])->name('xac-nhan');
        });

    // Ví dụ phân quyền: chỉ Quản trị viên vào được (thay bằng chức năng thật ở các bước sau)
    Route::middleware('vaitro:Quản trị viên')->prefix('quan-tri')->group(function () {
        Route::get('/', fn () => 'Khu vực dành cho Quản trị viên')->name('quan-tri');
    });
});
