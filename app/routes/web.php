<?php

use App\Http\Controllers\Auth\DangNhapController;
use App\Http\Controllers\CauHoiController;
use App\Http\Controllers\CauHoiQuanLyController;
use App\Http\Controllers\ChuongTrinhDaoTaoController;
use App\Http\Controllers\DeThiController;
use App\Http\Controllers\ChonDeThiController;
use App\Http\Controllers\HocPhanController;
use App\Http\Controllers\NganHangDeThiController;
use App\Http\Controllers\XuatDeThiController;
use App\Http\Controllers\PhanBienDeThiController;
use App\Http\Controllers\PhanCongHocPhanController;
use App\Http\Controllers\PheDuyetDeThiController;
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

    // Đề thi (UC-10): tạo từ ma trận đã chốt, xem, gửi phản biện, xóa đề nháp
    Route::middleware('vaitro:Giảng viên,Quản trị viên')
        ->prefix('de-thi')
        ->name('de-thi.')
        ->group(function () {
            Route::get('/', [DeThiController::class, 'index'])->name('index');
            Route::get('/tao', [DeThiController::class, 'create'])->name('create');
            Route::post('/tao', [DeThiController::class, 'taoNhap'])->name('tao-nhap');
            Route::get('/thieu', [DeThiController::class, 'thieu'])->name('thieu');
            Route::get('/xuat-ngan-hang', [XuatDeThiController::class, 'xuatNganHang'])->name('xuat-ngan-hang');
            Route::get('/nhap/{token}', [DeThiController::class, 'xemTruoc'])->name('xem-truoc');
            Route::post('/nhap/{token}', [DeThiController::class, 'luu'])->name('luu');
            Route::post('/nhap/{token}/tao-lai', [DeThiController::class, 'taoLai'])->name('tao-lai');
            Route::post('/nhap/{token}/huy', [DeThiController::class, 'huyNhap'])->name('huy-nhap');
            Route::get('/{deThi}', [DeThiController::class, 'show'])->name('show');
            Route::get('/{deThi}/xuat', [XuatDeThiController::class, 'xuatDe'])->name('xuat');
            Route::post('/{deThi}/gui-phan-bien', [DeThiController::class, 'guiPhanBien'])->name('gui-phan-bien');
            Route::delete('/{deThi}', [DeThiController::class, 'destroy'])->name('destroy');
        });

    // Phân công học phần (UC-20): Trưởng bộ môn phân công biên soạn/phản biện; Quản trị viên xem và phân công mọi học phần
    Route::middleware('vaitro:Trưởng bộ môn,Quản trị viên')
        ->prefix('phan-cong')
        ->name('phan-cong.')
        ->group(function () {
            Route::get('/', [PhanCongHocPhanController::class, 'index'])->name('index');
            Route::post('/', [PhanCongHocPhanController::class, 'store'])->name('store');
            Route::post('/{phanCong}/ket-thuc', [PhanCongHocPhanController::class, 'ketThuc'])->name('ket-thuc');
        });

    // Phản biện đề thi (UC-15): quyền cụ thể theo phân công học phần được kiểm tra trong controller
    Route::middleware('vaitro:Giảng viên,Trưởng bộ môn')
        ->prefix('phan-bien')
        ->name('phan-bien.')
        ->group(function () {
            Route::get('/', [PhanBienDeThiController::class, 'index'])->name('index');
            Route::get('/{deThi}', [PhanBienDeThiController::class, 'show'])->name('show');
            Route::post('/{deThi}', [PhanBienDeThiController::class, 'ghiKetQua'])->name('ghi');
        });

    // Phê duyệt đề thi (UC-16): Trưởng bộ môn của bộ môn phụ trách học phần
    Route::middleware('vaitro:Trưởng bộ môn')
        ->prefix('phe-duyet')
        ->name('phe-duyet.')
        ->group(function () {
            Route::get('/', [PheDuyetDeThiController::class, 'index'])->name('index');
            Route::get('/{deThi}', [PheDuyetDeThiController::class, 'show'])->name('show');
            Route::post('/{deThi}/duyet', [PheDuyetDeThiController::class, 'duyet'])->name('duyet');
            Route::post('/{deThi}/tu-choi', [PheDuyetDeThiController::class, 'tuChoi'])->name('tu-choi');
        });

    // Cơ cấu ngân hàng đề thi (UC-17) và chọn đề cho ca thi (UC-18): Phòng ĐT&ĐBCLGD, Quản trị viên
    Route::middleware('vaitro:Phòng ĐT&ĐBCLGD,Quản trị viên')->group(function () {
        Route::get('/ngan-hang', [NganHangDeThiController::class, 'index'])->name('ngan-hang.index');
        Route::get('/ngan-hang/xuat', [XuatDeThiController::class, 'xuatNganHang'])->name('ngan-hang.xuat');
        Route::get('/ngan-hang/{maHocPhan}', [NganHangDeThiController::class, 'show'])->name('ngan-hang.show');

        Route::prefix('chon-de')->name('chon-de.')->group(function () {
            Route::get('/', [ChonDeThiController::class, 'index'])->name('index');
            Route::post('/', [ChonDeThiController::class, 'chon'])->name('chon');
            Route::get('/{suDung}', [ChonDeThiController::class, 'show'])->name('show');
            Route::get('/{suDung}/xuat', [XuatDeThiController::class, 'xuatSuDung'])->name('xuat');
            Route::post('/{suDung}/huy', [ChonDeThiController::class, 'huy'])->name('huy');
        });
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
