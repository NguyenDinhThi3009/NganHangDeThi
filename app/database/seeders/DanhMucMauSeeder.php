<?php

namespace Database\Seeders;

use App\Models\NganHangDeThi\BoMon;
use App\Models\NganHangDeThi\ChuongTrinhDaoTao;
use App\Models\NganHangDeThi\Clo;
use App\Models\NganHangDeThi\CloPlo;
use App\Models\NganHangDeThi\HocPhan;
use App\Models\NganHangDeThi\HocPhanPlo;
use App\Models\NganHangDeThi\Plo;
use Illuminate\Database\Seeder;

/**
 * Dữ liệu danh mục lấy từ đề cương chi tiết KD5505003 – Cấu trúc máy tính.
 * Các giá trị đánh dấu "TODO" KHÔNG có trong đề cương, cần cập nhật theo
 * Bản mô tả CTĐT ngành CNTT (mã ngành 7480201, ban hành 2025).
 * Seeder dùng updateOrCreate nên chạy lại nhiều lần không bị trùng dữ liệu.
 */
class DanhMucMauSeeder extends Seeder
{
    public function run(): void
    {
        // --- Bộ môn ---
        $boMon = BoMon::updateOrCreate(['tenBoMon' => 'Công nghệ thông tin']);

        // --- Chương trình đào tạo ---
        $ctdt = ChuongTrinhDaoTao::updateOrCreate(
            ['tenChuongTrinhDaoTao' => 'Công nghệ thông tin (7480201)'],
            [
                'tenTiengAnh' => null,                 // TODO: không có trong đề cương
                'trinhDo' => 'Đại học',
                'tongSoTinChi' => 0,                   // TODO: tổng số tín chỉ theo Bản mô tả CTĐT
                'khoaQuanLy' => 'Khoa Công nghệ số',
                'khoaHoc' => 'Chưa cập nhật',          // TODO: khóa học áp dụng CTĐT
            ]
        );

        // --- PLO1..PLO9 (đề cương chỉ nêu ký hiệu, chưa có nội dung) ---
        $plo = [];
        for ($i = 1; $i <= 9; $i++) {
            $plo[$i] = Plo::updateOrCreate(
                ['maChuongTrinhDaoTao' => $ctdt->maChuongTrinhDaoTao, 'kyHieu' => "PLO{$i}"],
                ['moTa' => "TODO: nhập nội dung PLO{$i} theo Bản mô tả CTĐT ngành CNTT"]
            );
        }

        // --- Học phần KD5505003 ---
        $hocPhan = HocPhan::updateOrCreate(
            ['maHocPhan' => 'KD5505003'],
            [
                'tenHocPhan' => 'Cấu trúc máy tính',
                'soTinChi' => 2,
                'soTinChiLT' => 1,                     // giả định: 18 tiết LT ~ 1 TC (đề cương chỉ ghi số tiết)
                'soTinChiTH' => 1,                     // giả định: 12 tiết thực hành ~ 1 TC
                'soTinChiThucTap' => null,
                'loaiHocPhan' => 'Kiến thức Chuyên ngành',
                'hocKyDuKien' => 0,                    // TODO: học kỳ dự kiến theo CTĐT
                'maChuongTrinhDaoTao' => $ctdt->maChuongTrinhDaoTao,
                'maBoMon' => $boMon->maBoMon,
            ]
        );

        // --- Ma trận học phần – PLO (dòng "Tổng hợp" của đề cương: PLO1 = I, PLO2 = R) ---
        foreach ([1 => 'I', 2 => 'R'] as $soPlo => $mucDo) {
            HocPhanPlo::updateOrCreate(
                ['maHocPhan' => $hocPhan->maHocPhan, 'maPlo' => $plo[$soPlo]->maPlo],
                ['mucDo' => $mucDo]
            );
        }

        // --- CLO (nguyên văn đề cương, mục 10.1) và ánh xạ CLO – PLO / PI ---
        $dsClo = [
            ['CLO1', 'Trình bày được kiến trúc, nguyên tắc hoạt động của các thành phần máy tính và các quy trình bảo trì cơ bản.', 1, '1.1'],
            ['CLO2', 'Phân tích được các thông số kỹ thuật, đánh giá sự tương thích của linh kiện để lựa chọn, lắp ráp và cài đặt hệ thống.', 1, '1.2'],
            ['CLO3', 'Thực hiện được các thao tác bảo dưỡng, chẩn đoán và khắc phục sự cố phần cứng, phần mềm thông dụng trên máy tính.', 2, '2.1'],
        ];

        foreach ($dsClo as [$kyHieu, $moTa, $soPlo, $chiSoPI]) {
            $clo = Clo::updateOrCreate(
                ['maHocPhan' => $hocPhan->maHocPhan, 'kyHieu' => $kyHieu],
                ['moTa' => $moTa]
            );

            CloPlo::updateOrCreate(
                ['maClo' => $clo->maClo, 'maPlo' => $plo[$soPlo]->maPlo],
                ['chiSoPI' => $chiSoPI]
            );
        }
    }
}
