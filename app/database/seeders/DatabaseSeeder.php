<?php

namespace Database\Seeders;

use App\Models\NganHangDeThi\NguoiDung;
use App\Models\NganHangDeThi\VaiTro;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(VaiTroSeeder::class);
        $this->call(DanhMucMauSeeder::class);

        // Tài khoản quản trị mẫu để đăng nhập lần đầu. ĐỔI MẬT KHẨU khi triển khai thật.
        NguoiDung::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'hoTen' => 'Quản trị viên',
                'matKhauMaHoa' => 'Admin@123456',
                'maVaiTro' => VaiTro::where('tenVaiTro', 'Quản trị viên')->value('maVaiTro'),
                'trangThai' => 'Hoạt động',
                'ngayTao' => now(),
            ]
        );
    }
}
