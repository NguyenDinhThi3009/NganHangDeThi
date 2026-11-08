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
 * Dữ liệu danh mục lấy từ:
 *  - Bản mô tả CTĐT ngành Công nghệ thông tin (mã ngành 7480201, ban hành 2025): thông tin CTĐT và nội dung PLO1-PLO9.
 *  - Đề cương chi tiết KD5505003 - Cấu trúc máy tính: học phần, CLO, ánh xạ CLO - PLO.
 * Các học phần khác nên nhập bằng chức năng "Nhập từ đề cương chi tiết" trên web.
 * Seeder dùng updateOrCreate nên chạy lại nhiều lần không bị trùng dữ liệu.
 */
class DanhMucMauSeeder extends Seeder
{
    /** Nội dung chuẩn đầu ra chương trình (mục 1.3 của Bản mô tả CTĐT). */
    private const PLO = [
        1 => 'Vận dụng các kiến thức liên ngành khoa học tự nhiên, khoa học xã hội, pháp luật, chính trị, kỹ thuật và công nghệ để xác định, phân tích và giải quyết các vấn đề kỹ thuật phức tạp trong lĩnh vực Công nghệ thông tin.',
        2 => 'Phân tích, đề xuất và triển khai các giải pháp kỹ thuật đáp ứng nhu cầu công việc chuyên môn trong lĩnh vực Công nghệ thông tin.',
        3 => 'Thiết kế phần mềm, hệ thống mạng trong lĩnh vực Công nghệ thông tin đáp ứng được các yêu cầu cụ thể có quan tâm đến con người, môi trường, cộng đồng và các vấn đề toàn cầu.',
        4 => 'Triển khai và bảo trì hệ thống trong lĩnh vực Công nghệ thông tin.',
        5 => 'Nhận thức về đạo đức, trách nhiệm nghề nghiệp, và quy trình kỹ thuật trong lĩnh vực Công nghệ thông tin.',
        6 => 'Có chiến lược học tập suốt đời và thường xuyên cập nhật kiến thức chuyên môn để thích ứng với sự phát triển của lĩnh vực Công nghệ thông tin.',
        7 => 'Có năng lực trình bày và thuyết trình tài liệu kỹ thuật; sử dụng được ngoại ngữ trong giao tiếp và công việc chuyên môn.',
        8 => 'Hợp tác hiệu quả trong nhóm với vai trò thành viên để hoàn thành các nhiệm vụ kỹ thuật phức tạp.',
        9 => 'Có tư duy phản biện, đổi mới sáng tạo, khởi nghiệp; kỹ năng quản trị, quản lý, đánh giá và cải tiến hiệu quả các hoạt động chuyên môn trong lĩnh vực Công nghệ thông tin.',
    ];

    public function run(): void
    {
        // --- Bộ môn ---
        $boMon = BoMon::updateOrCreate(['tenBoMon' => 'Công nghệ thông tin']);

        // --- Chương trình đào tạo (Bản mô tả CTĐT, mục 1.1) ---
        $ctdt = ChuongTrinhDaoTao::updateOrCreate(
            ['tenChuongTrinhDaoTao' => 'Công nghệ thông tin (7480201)'],
            [
                'tenTiengAnh' => 'Information Technology',
                'trinhDo' => 'Đại học',
                'tongSoTinChi' => 159,
                'khoaQuanLy' => 'Khoa Công nghệ số',
                'khoaHoc' => 'Chưa cập nhật',          // TODO: Bản mô tả chỉ ghi "ban hành 2025", chưa nêu khóa học áp dụng
            ]
        );

        // --- PLO1..PLO9 ---
        $plo = [];
        foreach (self::PLO as $so => $moTa) {
            $plo[$so] = Plo::updateOrCreate(
                ['maChuongTrinhDaoTao' => $ctdt->maChuongTrinhDaoTao, 'kyHieu' => "PLO{$so}"],
                ['moTa' => $moTa]
            );
        }

        // --- Học phần KD5505003 ---
        $hocPhan = HocPhan::updateOrCreate(
            ['maHocPhan' => 'KD5505003'],
            [
                'tenHocPhan' => 'Cấu trúc máy tính',
                'soTinChi' => 2,
                'soTinChiLT' => 1,                     // đề cương chỉ ghi số tiết (18 LT, 12 TH); chia 1 + 1 theo tỷ lệ
                'soTinChiTH' => 1,
                'soTinChiThucTap' => null,
                'loaiHocPhan' => 'Kiến thức Chuyên ngành',
                'hocKyDuKien' => 2,                    // Kế hoạch đào tạo (Bản mô tả CTĐT, mục 2.4): học kỳ 2
                'maChuongTrinhDaoTao' => $ctdt->maChuongTrinhDaoTao,
                'maBoMon' => $boMon->maBoMon,
            ]
        );

        // --- Ma trận học phần - PLO (dòng "Tổng hợp" của đề cương: PLO1 = I, PLO2 = R) ---
        foreach ([1 => 'I', 2 => 'R'] as $soPlo => $mucDo) {
            HocPhanPlo::updateOrCreate(
                ['maHocPhan' => $hocPhan->maHocPhan, 'maPlo' => $plo[$soPlo]->maPlo],
                ['mucDo' => $mucDo]
            );
        }

        // --- CLO (nguyên văn đề cương, mục 10.1) và ánh xạ CLO - PLO / PI ---
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
