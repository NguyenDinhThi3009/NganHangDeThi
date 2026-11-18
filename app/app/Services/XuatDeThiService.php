<?php

namespace App\Services;

use App\Models\NganHangDeThi\BoMon;
use App\Models\NganHangDeThi\DeThi;
use App\Support\DocxBuilder;

/**
 * Xuất đề thi và đáp án ra Word (.docx) và HTML để in/PDF (UC-11, FR-12).
 * Mẫu trình bày là mẫu chuẩn do hệ thống thiết kế, đổi tên trường/khoa trong config/dethi.php (.env).
 */
class XuatDeThiService
{
    public const MAU = ['de' => 'Đề thi', 'dap-an' => 'Đáp án và thang điểm', 'ca-hai' => 'Đề thi và đáp án'];

    public function __construct(private readonly TaoDeThiService $dich) {}

    /** Gom dữ liệu một đề thành mảng dùng chung cho Word và HTML. */
    public function duLieu(DeThi $deThi): array
    {
        $deThi->loadMissing(['maTranDeThi.hocPhan', 'nguoiTao']);
        $hocPhan = $deThi->maTranDeThi?->hocPhan;
        $boMon = $hocPhan?->maBoMon ? BoMon::find($hocPhan->maBoMon) : null;

        $muc = $this->dich->dungHienThi($this->dich->mucTuDeThi($deThi));
        $cacCau = [];
        foreach ($muc as $m) {
            $phuongAn = [];
            $dapAnDung = [];
            foreach ($m['phuong_an'] as $i => $pa) {
                $nhan = chr(65 + $i);
                $phuongAn[] = ['id' => (int) $pa->maDapAnTracNghiem, 'nhan' => $nhan, 'noi_dung' => $pa->noiDungDapAn, 'dung' => (bool) $pa->laDapAnDung];
                if ($pa->laDapAnDung) {
                    $dapAnDung[] = $nhan;
                }
            }
            $cacCau[] = [
                'ma_cau' => (int) $m['cauHoi']->maCauHoi,
                'stt' => $m['thuTu'],
                'phan' => $m['phan'],
                'diem' => $this->dinhDangDiem($m['diemSo']),
                'noi_dung' => $m['cauHoi']->noiDungCauHoi,
                'phuong_an' => $phuongAn,
                'dap_an_dung' => implode(', ', $dapAnDung),
                'cac_y' => array_map(fn ($y) => ['noi_dung' => $y['noi_dung'], 'diem' => $this->dinhDangDiem($y['diem'])], $m['cac_y']),
            ];
        }

        $thoiGian = $deThi->hinhThucThi === 'Vấn đáp'
            ? "Chuẩn bị {$deThi->thoiGianChuanBi} phút, trình bày {$deThi->thoiGianTraLoi} phút"
            : "{$deThi->thoiGianLamBai} phút";

        return [
            'truong' => (string) config('dethi.ten_truong'),
            'khoa' => (string) config('dethi.ten_khoa'),
            'bo_mon' => $boMon?->tenBoMon ?? '',
            'hoc_phan' => (string) $hocPhan?->tenHocPhan,
            'ma_hoc_phan' => (string) $hocPhan?->maHocPhan,
            'ten_de' => $deThi->tenDeThi,
            'ma_de' => $deThi->getKey(),
            'dong_de' => "Đề số {$deThi->getKey()}: {$deThi->tenDeThi}",
            'hinh_thuc' => $deThi->hinhThucThi,
            'thoi_gian' => $thoiGian,
            'trang_thai' => $deThi->trangThai,
            'la_ban_nhap' => $deThi->trangThai !== 'Đã duyệt',
            'nguoi_bien_soan' => (string) $deThi->nguoiTao?->hoTen,
            'cau' => $cacCau,
            'so_cau' => count($cacCau),
            'tong_diem' => $this->dinhDangDiem(array_sum(array_map(fn ($m) => $m['diemSo'], $muc))),
        ];
    }

    /**
     * Dữ liệu của một mã đề trắc nghiệm: câu và phương án theo thứ tự đã xáo, đáp án đúng suy ra từ thứ tự đó.
     */
    public function duLieuMaDe(DeThi $deThi, \App\Models\NganHangDeThi\MaDeTracNghiem $maDe): array
    {
        $de = $this->duLieu($deThi);
        $theoCau = [];
        foreach ($de['cau'] as $c) {
            $theoCau[$c['ma_cau']] = $c;
        }
        $maCauTheoDong = \App\Models\NganHangDeThi\DeThiCauHoi::where('maDeThi', $deThi->getKey())->pluck('maCauHoi', 'maDeThiCauHoi');

        $cau = [];
        foreach ($maDe->maDeCauHois()->orderBy('thuTuCauMoi')->get() as $dong) {
            $goc = $theoCau[$maCauTheoDong[$dong->maDeThiCauHoi] ?? 0] ?? null;
            if (! $goc) {
                continue;
            }
            $theoId = [];
            foreach ($goc['phuong_an'] as $pa) {
                $theoId[$pa['id']] = $pa;
            }
            $phuongAn = [];
            $dung = [];
            foreach (array_values((array) $dong->thuTuDapAn) as $i => $id) {
                if (! isset($theoId[$id])) {
                    continue;
                }
                $nhan = chr(65 + $i);
                $phuongAn[] = ['id' => $id, 'nhan' => $nhan, 'noi_dung' => $theoId[$id]['noi_dung'], 'dung' => $theoId[$id]['dung']];
                if ($theoId[$id]['dung']) {
                    $dung[] = $nhan;
                }
            }
            $cau[] = ['stt' => (int) $dong->thuTuCauMoi, 'phuong_an' => $phuongAn, 'dap_an_dung' => implode(', ', $dung)] + $goc;
            $cau[array_key_last($cau)]['stt'] = (int) $dong->thuTuCauMoi;
        }

        $de['cau'] = $cau;
        $de['ma_de'] = $maDe->kyHieuMa;
        $de['dong_de'] = "Mã đề {$maDe->kyHieuMa}: {$de['ten_de']}";

        return $de;
    }

    public function dinhDangDiem(float $diem): string
    {
        return rtrim(rtrim(number_format($diem, 2, ',', ''), '0'), ',');
    }

    /**
     * Tạo file Word chứa một hoặc nhiều đề. Trả về đường dẫn file tạm.
     *
     * @param  list<array<string, mixed>>  $cacDe  kết quả của duLieu()
     */
    public function taoWord(array $cacDe, string $mau): string
    {
        $docx = new DocxBuilder;
        $dau = true;

        foreach ($cacDe as $de) {
            foreach (($mau === 'ca-hai' ? ['de', 'dap-an'] : [$mau]) as $phan) {
                if (! $dau) {
                    $docx->ngatTrang();
                }
                $dau = false;
                $phan === 'de' ? $this->wordDe($docx, $de) : $this->wordDapAn($docx, $de);
            }
        }

        $tam = tempnam(sys_get_temp_dir(), 'dethi').'.docx';
        $docx->luu($tam);

        return $tam;
    }

    private function wordDauTrang(DocxBuilder $docx, array $de, string $tieuDe): void
    {
        $nho = ['canh' => 'giua', 'coChu' => 24];
        $trai = [[[[mb_strtoupper($de['truong']), 'b']], $nho]];
        if ($de['khoa'] !== '') {
            $trai[] = [[[mb_strtoupper($de['khoa']), 'b']], $nho];
        }
        if ($de['bo_mon'] !== '') {
            $trai[] = [[['BỘ MÔN '.mb_strtoupper($de['bo_mon']), 'b']], $nho];
        }

        $phai = [
            ['noiDung' => [[$tieuDe, 'b']], 'tuyChon' => ['canh' => 'giua']],
            ['noiDung' => [['Học phần: ', ''], [$de['hoc_phan'].' ('.$de['ma_hoc_phan'].')', 'b']], 'tuyChon' => ['canh' => 'giua', 'coChu' => 24]],
            ['noiDung' => 'Hình thức thi: '.$de['hinh_thuc'], 'tuyChon' => ['canh' => 'giua', 'coChu' => 24]],
            ['noiDung' => ($de['hinh_thuc'] === 'Vấn đáp' ? 'Thời gian: ' : 'Thời gian làm bài: ').$de['thoi_gian'], 'tuyChon' => ['canh' => 'giua', 'coChu' => 24]],
        ];

        $docx->bang([[
            ['cacDoan' => $trai],
            ['cacDoan' => array_map(fn ($d) => [$d['noiDung'], $d['tuyChon']], $phai)],
        ]], [3700, 5800], false);

        $docx->doan([[$de['dong_de'], 'i']], ['canh' => 'giua', 'truoc' => 120, 'coChu' => 22, 'vienDuoi' => true]);
        if ($de['la_ban_nhap']) {
            $docx->doan([['(Bản nháp, chưa được phê duyệt - trạng thái: '.$de['trang_thai'].')', 'i']], ['canh' => 'giua', 'coChu' => 22]);
        }
    }

    private function wordDe(DocxBuilder $docx, array $de): void
    {
        $this->wordDauTrang($docx, $de, 'ĐỀ THI KẾT THÚC HỌC PHẦN');

        $huongDan = match ($de['hinh_thuc']) {
            'Trắc nghiệm' => "Chọn một phương án đúng nhất cho mỗi câu. Đề gồm {$de['so_cau']} câu, thang điểm {$de['tong_diem']}.",
            default => "Đề gồm {$de['so_cau']} câu, thang điểm {$de['tong_diem']}.",
        };
        $docx->doan([[$huongDan, 'i']], ['truoc' => 120, 'sau' => 120]);

        $phanHienTai = null;
        foreach ($de['cau'] as $c) {
            if ($de['hinh_thuc'] === 'Vấn đáp' && $phanHienTai !== $c['phan']) {
                $phanHienTai = $c['phan'];
                $docx->doan([[$c['phan'] === 1 ? 'Phần 1 - Câu hỏi bắt buộc' : 'Phần 2 - Câu hỏi thêm', 'b']], ['truoc' => 160, 'giuVoiTiep' => true]);
            }
            $docx->doan([["Câu {$c['stt']}", 'b'], [" ({$c['diem']} điểm). ", 'i'], [$c['noi_dung'], '']], ['truoc' => 100, 'canh' => 'deu', 'giuVoiTiep' => $c['phuong_an'] !== []]);
            foreach ($c['phuong_an'] as $pa) {
                $docx->doan("{$pa['nhan']}. {$pa['noi_dung']}", ['thut' => 567, 'sau' => 20, 'canh' => 'deu']);
            }
        }

        $docx->doan([['--- HẾT ---', 'b']], ['canh' => 'giua', 'truoc' => 240]);
        $docx->bang([[
            ['cacDoan' => [[[['Người ra đề', 'b']], ['canh' => 'giua']], ['', ['truoc' => 700]], [$de['nguoi_bien_soan'], ['canh' => 'giua']]]],
            ['cacDoan' => [[[['Trưởng bộ môn', 'b']], ['canh' => 'giua']]]],
        ]], [4750, 4750], false);
    }

    private function wordDapAn(DocxBuilder $docx, array $de): void
    {
        $this->wordDauTrang($docx, $de, 'ĐÁP ÁN VÀ THANG ĐIỂM');
        $docx->doan([['Tài liệu lưu hành nội bộ, chỉ dùng cho chấm thi và phúc khảo.', 'i']], ['canh' => 'giua', 'coChu' => 22, 'sau' => 120]);

        if ($de['hinh_thuc'] === 'Trắc nghiệm') {
            $nua = (int) ceil(count($de['cau']) / 2);
            $hang = [[
                ['noiDung' => [['Câu', 'b']], 'tuyChon' => ['canh' => 'giua'], 'tieuDe' => true],
                ['noiDung' => [['Đáp án', 'b']], 'tuyChon' => ['canh' => 'giua'], 'tieuDe' => true],
                ['noiDung' => [['Điểm', 'b']], 'tuyChon' => ['canh' => 'giua'], 'tieuDe' => true],
                ['noiDung' => [['Câu', 'b']], 'tuyChon' => ['canh' => 'giua'], 'tieuDe' => true],
                ['noiDung' => [['Đáp án', 'b']], 'tuyChon' => ['canh' => 'giua'], 'tieuDe' => true],
                ['noiDung' => [['Điểm', 'b']], 'tuyChon' => ['canh' => 'giua'], 'tieuDe' => true],
            ]];
            for ($i = 0; $i < $nua; $i++) {
                $hang[] = [...$this->oDapAn($de['cau'][$i] ?? null), ...$this->oDapAn($de['cau'][$i + $nua] ?? null)];
            }
            $docx->bang($hang, [800, 1700, 1600, 800, 1700, 1600]);
        } else {
            $phanHienTai = null;
            foreach ($de['cau'] as $c) {
                if ($de['hinh_thuc'] === 'Vấn đáp' && $phanHienTai !== $c['phan']) {
                    $phanHienTai = $c['phan'];
                    $docx->doan([[$c['phan'] === 1 ? 'Phần 1 - Câu hỏi bắt buộc' : 'Phần 2 - Câu hỏi thêm', 'b']], ['truoc' => 160, 'giuVoiTiep' => true]);
                }
                $docx->doan([["Câu {$c['stt']}", 'b'], [" ({$c['diem']} điểm). ", 'i'], [$c['noi_dung'], '']], ['truoc' => 140, 'canh' => 'deu', 'giuVoiTiep' => true]);

                $hang = [[
                    ['noiDung' => [['Ý', 'b']], 'tuyChon' => ['canh' => 'giua'], 'tieuDe' => true],
                    ['noiDung' => [['Nội dung đáp án', 'b']], 'tieuDe' => true],
                    ['noiDung' => [['Điểm', 'b']], 'tuyChon' => ['canh' => 'giua'], 'tieuDe' => true],
                ]];
                foreach ($c['cac_y'] as $i => $y) {
                    $hang[] = [
                        ['noiDung' => (string) ($i + 1), 'tuyChon' => ['canh' => 'giua']],
                        ['noiDung' => $y['noi_dung']],
                        ['noiDung' => $y['diem'], 'tuyChon' => ['canh' => 'giua']],
                    ];
                }
                $hang[] = [
                    ['noiDung' => ''],
                    ['noiDung' => [['Cộng', 'b']], 'tuyChon' => ['canh' => 'phai']],
                    ['noiDung' => [[$c['diem'], 'b']], 'tuyChon' => ['canh' => 'giua']],
                ];
                $docx->bang($hang, [700, 7400, 1400]);
            }
        }

        $docx->doan([['Tổng điểm: '.$de['tong_diem'], 'b']], ['truoc' => 160]);
    }

    /** @return list<array<string, mixed>> */
    private function oDapAn(?array $c): array
    {
        if (! $c) {
            return [['noiDung' => ''], ['noiDung' => ''], ['noiDung' => '']];
        }

        return [
            ['noiDung' => (string) $c['stt'], 'tuyChon' => ['canh' => 'giua']],
            ['noiDung' => [[$c['dap_an_dung'], 'b']], 'tuyChon' => ['canh' => 'giua']],
            ['noiDung' => $c['diem'], 'tuyChon' => ['canh' => 'giua']],
        ];
    }
}
