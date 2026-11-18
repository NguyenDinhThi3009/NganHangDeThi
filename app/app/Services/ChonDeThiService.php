<?php

namespace App\Services;

use App\Models\NganHangDeThi\DeThi;
use App\Models\NganHangDeThi\DeThiCauHoi;
use App\Models\NganHangDeThi\MaDeCauHoi;
use App\Models\NganHangDeThi\MaDeTracNghiem;
use App\Models\NganHangDeThi\SuDungDeThi;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Chọn ngẫu nhiên đề đã duyệt cho ca thi và tạo mã đề trắc nghiệm (UC-18, FR-19). */
class ChonDeThiService
{
    /** Số lần thử xáo trộn để các mã đề không trùng thứ tự câu hoặc thứ tự đáp án. */
    private const SO_LAN_THU = 50;

    /**
     * @return array{su_dung: SuDungDeThi, de_thi: DeThi, canh_bao: list<string>}
     *
     * @throws RuntimeException khi không còn đề phù hợp (thông báo hiển thị được cho người dùng)
     */
    public function chon(string $maHocPhan, string $hinhThuc, int $hocKy, string $namHoc, CarbonInterface $ngayThi, int $soMaDe): array
    {
        return DB::transaction(function () use ($maHocPhan, $hinhThuc, $hocKy, $namHoc, $ngayThi, $soMaDe) {
            // BR-08: loại các đề đã dùng trong học kỳ và năm học này
            $conLai = DeThi::where('trangThai', 'Đã duyệt')->where('hinhThucThi', $hinhThuc)
                ->whereHas('maTranDeThi', fn ($q) => $q->where('maHocPhan', $maHocPhan))
                ->whereDoesntHave('suDungDeThis', fn ($q) => $q->where('hocKy', $hocKy)->where('namHoc', $namHoc))
                ->withCount('deThiCauHois')
                ->get();

            if ($conLai->isEmpty()) {
                throw new RuntimeException('Không còn đề thi '.mb_strtolower($hinhThuc)." đã duyệt chưa dùng trong học kỳ {$hocKy} năm học {$namHoc} của học phần {$maHocPhan}.");
            }

            /** @var DeThi $deThi */
            $deThi = $conLai->random();

            try {
                $suDung = SuDungDeThi::create([
                    'maDeThi' => $deThi->getKey(),
                    'hocKy' => $hocKy,
                    'namHoc' => $namHoc,
                    'ngaySuDung' => $ngayThi->toDateString(),
                ]);
            } catch (QueryException $e) {
                throw new RuntimeException('Đề vừa được người khác chọn cho học kỳ này. Hãy chọn lại.');
            }

            if ($hinhThuc === 'Trắc nghiệm') {
                $this->taoMaDe($suDung, $deThi, $soMaDe);
            }

            $canhBao = [];
            // BR-13: đề phải được duyệt chậm nhất 04 tuần trước ngày thi
            if ($deThi->ngayDuyet && $deThi->ngayDuyet->greaterThan($ngayThi->copy()->subWeeks(4)->endOfDay())) {
                $canhBao[] = 'Đề được duyệt ngày '.$deThi->ngayDuyet->format('d/m/Y').', muộn hơn mốc 04 tuần trước ngày thi ('
                    .$ngayThi->copy()->subWeeks(4)->format('d/m/Y').') (BR-13). Bạn có thể hủy lần chọn này để chọn đề khác.';
            }

            return ['su_dung' => $suDung, 'de_thi' => $deThi, 'canh_bao' => $canhBao];
        });
    }

    /**
     * Tạo các mã đề: xáo thứ tự câu và thứ tự phương án, lưu thứ tự để suy ra đáp án đúng từng mã (UC-18 bước 4).
     * Các câu có phương án dạng "cả A và B", "tất cả đáp án trên" giữ nguyên thứ tự phương án để không sai nghĩa.
     */
    public function taoMaDe(SuDungDeThi $suDung, DeThi $deThi, int $soMaDe): void
    {
        $cacCau = DeThiCauHoi::where('maDeThi', $deThi->getKey())->orderBy('thuTuCau')
            ->with('cauHoi.dapAnTracNghiems')->get();
        if ($cacCau->isEmpty()) {
            throw new RuntimeException('Đề thi không có câu hỏi.');
        }

        $daDung = [];
        for ($i = 0; $i < $soMaDe; $i++) {
            $thuTuCau = $thuTuDapAn = $dauVan = null;

            for ($lan = 0; $lan < self::SO_LAN_THU; $lan++) {
                $ids = $cacCau->pluck('maDeThiCauHoi')->all();
                shuffle($ids);
                $dapAn = [];
                foreach ($cacCau as $c) {
                    $cacPa = $c->cauHoi->dapAnTracNghiems->sortBy('thuTu')->values();
                    $thu = $cacPa->pluck('maDapAnTracNghiem')->all();
                    if (! $this->phuongAnPhuThuocThuTu($cacPa->pluck('noiDungDapAn')->all())) {
                        shuffle($thu);
                    }
                    $dapAn[$c->maDeThiCauHoi] = $thu;
                }
                $dauVan = md5(json_encode($ids).json_encode($dapAn));
                // Mã đề phải khác các mã trước về thứ tự câu
                if (! in_array(md5(json_encode($ids)), array_column($daDung, 'cau'), true) && ! in_array($dauVan, array_column($daDung, 'tat_ca'), true)) {
                    $thuTuCau = $ids;
                    $thuTuDapAn = $dapAn;
                    break;
                }
            }
            if ($thuTuCau === null) {
                throw new RuntimeException('Không tạo được các mã đề khác nhau (đề quá ít câu).');
            }
            $daDung[] = ['cau' => md5(json_encode($thuTuCau)), 'tat_ca' => $dauVan];

            $ma = MaDeTracNghiem::create(['maSuDung' => $suDung->getKey(), 'kyHieuMa' => (string) (101 + $i)]);
            foreach ($thuTuCau as $viTri => $maDeThiCauHoi) {
                MaDeCauHoi::create([
                    'maMaDe' => $ma->getKey(),
                    'maDeThiCauHoi' => $maDeThiCauHoi,
                    'thuTuCauMoi' => $viTri + 1,
                    'thuTuDapAn' => $thuTuDapAn[$maDeThiCauHoi],
                ]);
            }
        }
    }

    /** Phương án nhắc tới phương án khác nên không được đảo vị trí. */
    private function phuongAnPhuThuocThuTu(array $cacPhuongAn): bool
    {
        foreach ($cacPhuongAn as $pa) {
            if (preg_match('/(tất cả|cả\s+[A-D]\b|\b[A-D]\s*(và|,|&)\s*[A-D]\b|đều đúng|đều sai|không có đáp án|không đáp án nào|các đáp án trên|các phương án trên)/iu', (string) $pa)) {
                return true;
            }
        }

        return false;
    }
}
