<?php

namespace Database\Seeders;

use App\Models\NganHangDeThi\VaiTro;
use Illuminate\Database\Seeder;

class VaiTroSeeder extends Seeder
{
    public function run(): void
    {
        $danhSach = [
            'Giảng viên' => 'Upload tài liệu, cấu hình ma trận, sinh và quản lý câu hỏi, tạo đề thi',
            'Quản trị viên' => 'Quản lý tài khoản, phân quyền, CTĐT, học phần, CLO/PLO, mẫu prompt, nhật ký',
            'Trưởng bộ môn' => 'Phân công biên soạn/phản biện, phê duyệt đề thi',
            'Phòng ĐT&ĐBCLGD' => 'Tải và quản lý tài liệu chung, theo dõi chất lượng đề thi',
        ];

        foreach ($danhSach as $ten => $moTa) {
            VaiTro::updateOrCreate(['tenVaiTro' => $ten], ['moTa' => $moTa]);
        }
    }
}
