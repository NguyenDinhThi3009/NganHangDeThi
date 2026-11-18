<?php

namespace Database\Seeders;

use App\Models\NganHangDeThi\BoMon;
use App\Models\NganHangDeThi\NguoiDung;
use App\Models\NganHangDeThi\VaiTro;
use Illuminate\Database\Seeder;

/**
 * Tài khoản mẫu để thử luồng biên soạn, phản biện, phê duyệt (khi chưa có màn hình quản lý người dùng, UC-12).
 * Chạy: php artisan db:seed --class=NguoiDungMauSeeder. ĐỔI MẬT KHẨU khi triển khai thật.
 */
class NguoiDungMauSeeder extends Seeder
{
    public function run(): void
    {
        $boMon = BoMon::first();

        $danhSach = [
            ['truongbm@example.com', 'Trưởng bộ môn Mẫu', 'Trưởng bộ môn', 'Tiến sĩ'],
            ['bienson@example.com', 'Giảng viên Biên soạn Mẫu', 'Giảng viên', 'Thạc sĩ'],
            ['phanbien@example.com', 'Giảng viên Phản biện Mẫu', 'Giảng viên', 'Thạc sĩ'],
            ['phongdt@example.com', 'Chuyên viên Phòng ĐT&ĐBCLGD Mẫu', 'Phòng ĐT&ĐBCLGD', 'Thạc sĩ'],
        ];

        foreach ($danhSach as [$email, $hoTen, $vaiTro, $hocVi]) {
            NguoiDung::updateOrCreate(['email' => $email], [
                'hoTen' => $hoTen,
                'matKhauMaHoa' => 'Mau@123456',
                'maVaiTro' => VaiTro::where('tenVaiTro', $vaiTro)->value('maVaiTro'),
                'maBoMon' => $boMon?->maBoMon,
                'trangThai' => 'Hoạt động',
                'trinhDoHocVi' => $hocVi,
                'ngayTao' => now(),
            ]);
        }
    }
}
