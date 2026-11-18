<?php

namespace App\Services;

use App\Models\NganHangDeThi\CauHoi;
use App\Models\NganHangDeThi\ChiTietMaTran;
use App\Models\NganHangDeThi\DeThi;
use App\Models\NganHangDeThi\DeThiCauHoi;
use App\Models\NganHangDeThi\HocPhan;
use App\Models\NganHangDeThi\KiemTraTrungLap;
use App\Models\NganHangDeThi\MaTranDeThi;
use App\Support\DiemDeThi;
use App\Support\MucDoBloom;
use App\Support\QuyenCauHoi;
use Illuminate\Support\Collection;

/**
 * Tạo bộ đề thi từ ma trận đã chốt và các câu hỏi đã duyệt (UC-10, FR-11):
 * chọn câu khớp ma trận, xếp thứ tự, chia điểm (tổng 10), kiểm tra cấu trúc theo BR-01 đến BR-06.
 *
 * "Mục" của đề là mảng ['cauHoi' => CauHoi, 'thuTu' => int, 'phan' => ?int, 'diemSo' => float];
 * cauHoi đã nạp sẵn phương án (trắc nghiệm) hoặc các ý đáp án (tự luận, vấn đáp).
 */
class TaoDeThiService
{
    public const HINH_THUC = ['Tự luận', 'Trắc nghiệm', 'Vấn đáp'];

    private const DO_KHO = ['Dễ', 'Trung bình', 'Khó'];

    /**
     * Chọn câu hỏi và lập đề nháp.
     *
     * @param  array{lam_bai?: ?int, chuan_bi?: ?int, tra_loi?: ?int, so_bat_buoc?: int, diem_bat_buoc?: float}  $thongSo
     * @return array{
     *     thieu: list<array<string, mixed>>,
     *     muc: list<array<string, mixed>>,
     *     loi: list<string>,
     *     canh_bao: list<string>
     * }
     */
    public function lapDe(MaTranDeThi $maTran, string $hinhThuc, array $thongSo): array
    {
        $maTran->loadMissing(['hocPhan', 'chiTietMaTrans.clo']);
        $cacDong = $this->sapXepDong($maTran->chiTietMaTrans);

        $chon = $this->chonCau($maTran, $cacDong, $hinhThuc);
        if ($chon['thieu']) {
            return ['thieu' => $chon['thieu'], 'muc' => [], 'loi' => [], 'canh_bao' => []];
        }

        $muc = $this->xepThuTuVaChiaDiem($chon['cau'], $hinhThuc, $thongSo, $loi);
        $canhBao = $chon['canh_bao'];

        if (! $loi) {
            $ketQua = $this->kiemTraCauTruc($hinhThuc, $thongSo, $muc, $maTran->hocPhan);
            $loi = $ketQua['loi'];
            $canhBao = array_merge($canhBao, $ketQua['canh_bao']);
        }

        return ['thieu' => [], 'muc' => $muc, 'loi' => $loi, 'canh_bao' => $canhBao];
    }

    /**
     * Chọn câu hỏi đã duyệt cho từng dòng ma trận. Ưu tiên câu ít được dùng trong các đề khác,
     * tránh chọn hai câu có độ tương đồng từ ngưỡng của ma trận trở lên.
     *
     * @return array{cau: list<CauHoi>, thieu: list<array<string, mixed>>, canh_bao: list<string>}
     */
    private function chonCau(MaTranDeThi $maTran, Collection $cacDong, string $hinhThuc): array
    {
        $cloIds = $cacDong->pluck('maClo')->unique()->all();

        // BR-16: chỉ câu "Đã duyệt" cùng học phần với ma trận
        $tho = CauHoi::where('maHocPhan', $maTran->maHocPhan)
            ->where('trangThaiDuyet', 'Đã duyệt')
            ->where('loaiCauHoi', $hinhThuc)
            ->whereIn('maClo', $cloIds)
            ->with([
                'dapAnTracNghiems' => fn ($q) => $q->orderBy('thuTu'),
                'dapAnTuLuanYs' => fn ($q) => $q->orderBy('thuTu'),
            ])
            ->get();

        $hopLe = $tho->filter(fn (CauHoi $c) => QuyenCauHoi::loiToanVen($c) === []);
        $nhomHopLe = $hopLe->groupBy(fn (CauHoi $c) => $this->khoa($c->maClo, $c->mucDoBloom, $c->doKho));
        $nhomTho = $tho->groupBy(fn (CauHoi $c) => $this->khoa($c->maClo, $c->mucDoBloom, $c->doKho));

        $ids = $hopLe->pluck('maCauHoi')->map(fn ($v) => (int) $v)->all();
        $suDung = $ids
            ? DeThiCauHoi::whereIn('maCauHoi', $ids)->selectRaw('maCauHoi, COUNT(*) as so')->groupBy('maCauHoi')->pluck('so', 'maCauHoi')
            : collect();
        $trung = $this->cacCapTrung($ids, (float) $maTran->nguongTrungLap);

        $daChon = [];
        $cau = [];
        $thieu = [];

        foreach ($cacDong as $dong) {
            $khoa = $this->khoa($dong->maClo, $dong->mucDoBloom, $dong->doKho);
            $can = (int) $dong->soLuongCau;

            $thuTu = $this->xaoTheoMucDungIt($nhomHopLe->get($khoa, collect()), $suDung);
            $dongChon = [];
            $hoan = [];

            foreach ($thuTu as $c) {
                if (count($dongChon) >= $can) {
                    break;
                }
                if ($this->trungVoiCauDaChon((int) $c->maCauHoi, $daChon, $trung)) {
                    $hoan[] = $c;

                    continue;
                }
                $dongChon[] = $c;
                $daChon[(int) $c->maCauHoi] = true;
            }
            // Thiếu câu không trùng thì mới lấy đến câu có nguy cơ trùng (sẽ cảnh báo)
            foreach ($hoan as $c) {
                if (count($dongChon) >= $can) {
                    break;
                }
                $dongChon[] = $c;
                $daChon[(int) $c->maCauHoi] = true;
            }

            if (count($dongChon) < $can) {
                $thieu[] = $this->moTaThieu($maTran, $dong, $hinhThuc, $can, count($dongChon), $nhomTho->get($khoa, collect())->count() - $nhomHopLe->get($khoa, collect())->count());
            }
            array_push($cau, ...$dongChon);
        }

        $canhBao = [];
        $maDaChon = array_map(fn (CauHoi $c) => (int) $c->maCauHoi, $cau);
        foreach ($maDaChon as $i => $a) {
            foreach (array_slice($maDaChon, $i + 1) as $b) {
                if (isset($trung[$a][$b])) {
                    $canhBao[] = "Câu #{$a} và câu #{$b} có nội dung tương đồng từ ".number_format((float) $maTran->nguongTrungLap * 100, 0).'% trở lên nhưng ngân hàng không còn câu thay thế. Hãy xem lại hai câu này.';
                }
            }
        }

        return ['cau' => $cau, 'thieu' => $thieu, 'canh_bao' => $canhBao];
    }

    /** @return array<string, mixed> */
    private function moTaThieu(MaTranDeThi $maTran, ChiTietMaTran $dong, string $hinhThuc, int $can, int $co, int $khongHopLe): array
    {
        $coKhac = CauHoi::where('maHocPhan', $maTran->maHocPhan)
            ->where('maClo', $dong->maClo)->where('mucDoBloom', $dong->mucDoBloom)->where('doKho', $dong->doKho)
            ->where('trangThaiDuyet', 'Đã duyệt')->where('loaiCauHoi', '!=', $hinhThuc)
            ->count();
        $nhap = CauHoi::where('maHocPhan', $maTran->maHocPhan)
            ->where('maClo', $dong->maClo)->where('mucDoBloom', $dong->mucDoBloom)->where('doKho', $dong->doKho)
            ->where('trangThaiDuyet', 'Nháp')
            ->count();

        return [
            'clo' => (string) $dong->clo?->kyHieu,
            'bloom' => MucDoBloom::sangNhan($dong->mucDoBloom),
            'do_kho' => $dong->doKho,
            'can' => $can,
            'co' => $co,
            'thieu' => $can - $co,
            'loai_khac' => $coKhac,
            'nhap' => $nhap,
            'khong_hop_le' => max(0, $khongHopLe),
        ];
    }

    /**
     * Xếp thứ tự câu (Bloom thấp đến cao, dễ đến khó) và chia điểm.
     *
     * @param  list<CauHoi>  $cau
     * @param  list<string>  $loi
     * @return list<array<string, mixed>>
     */
    private function xepThuTuVaChiaDiem(array $cau, string $hinhThuc, array $thongSo, ?array &$loi): array
    {
        $loi = [];
        $thuTuBloom = array_values(MucDoBloom::BANG);

        usort($cau, function (CauHoi $a, CauHoi $b) use ($thuTuBloom) {
            return [
                (int) array_search($a->mucDoBloom, $thuTuBloom, true),
                (int) array_search($a->doKho, self::DO_KHO, true),
                (int) $a->maClo,
                (int) $a->maCauHoi,
            ] <=> [
                (int) array_search($b->mucDoBloom, $thuTuBloom, true),
                (int) array_search($b->doKho, self::DO_KHO, true),
                (int) $b->maClo,
                (int) $b->maCauHoi,
            ];
        });

        $n = count($cau);
        $muc = [];

        if ($hinhThuc === 'Trắc nghiệm') {
            $diem = DiemDeThi::chiaDeuTracNghiem($n);
            foreach ($cau as $i => $c) {
                $muc[] = ['cauHoi' => $c, 'thuTu' => $i + 1, 'phan' => null, 'diemSo' => $diem[$i]];
            }

            return $muc;
        }

        // Tự luận: một nhóm 10 điểm. Vấn đáp: nhóm câu bắt buộc (phan = 1) và nhóm câu hỏi thêm (phan = 2)
        $soBatBuoc = $hinhThuc === 'Vấn đáp' ? max(0, min((int) ($thongSo['so_bat_buoc'] ?? 2), $n)) : 0;
        $nhom = $hinhThuc === 'Vấn đáp'
            ? [1 => array_slice($cau, 0, $soBatBuoc), 2 => array_slice($cau, $soBatBuoc)]
            : [0 => $cau];

        $diemBatBuoc = (float) ($thongSo['diem_bat_buoc'] ?? 7);
        $tongTheoNhom = $hinhThuc === 'Vấn đáp'
            ? ($nhom[2] === [] ? [1 => DiemDeThi::TONG_DIEM] : [1 => $diemBatBuoc, 2 => DiemDeThi::TONG_DIEM - $diemBatBuoc])
            : [0 => DiemDeThi::TONG_DIEM];

        $stt = 0;
        foreach ($nhom as $maNhom => $cacCau) {
            if ($cacCau === []) {
                continue;
            }
            $trongSo = array_map(function (CauHoi $c) {
                $goc = $this->diemGocCacY($c);

                return ['w' => array_sum($goc), 'tran' => DiemDeThi::tranDiemCau($goc)];
            }, $cacCau);

            $diem = DiemDeThi::chiaTheoTrongSo($trongSo, (float) $tongTheoNhom[$maNhom]);
            if ($diem === null) {
                $ten = $hinhThuc === 'Vấn đáp' ? ($maNhom === 1 ? 'nhóm câu hỏi bắt buộc' : 'nhóm câu hỏi thêm') : 'đề thi';
                $loi[] = 'Không chia được '.rtrim(rtrim(number_format((float) $tongTheoNhom[$maNhom], 2, ',', ''), '0'), ',')
                    ." điểm cho {$ten} mà vẫn giữ mỗi ý tối đa 0,5 điểm (BR-02): các câu được chọn không đủ số ý đáp án. "
                    .($hinhThuc === 'Vấn đáp'
                        ? 'Hãy giảm điểm của nhóm này (đổi điểm phần bắt buộc), tăng số câu trong ma trận hoặc bổ sung ý đáp án cho câu hỏi.'
                        : 'Hãy tăng số câu trong ma trận hoặc bổ sung ý đáp án cho câu hỏi.');

                return [];
            }

            foreach ($cacCau as $i => $c) {
                $muc[] = ['cauHoi' => $c, 'thuTu' => ++$stt, 'phan' => $maNhom === 0 ? null : $maNhom, 'diemSo' => $diem[$i]];
            }
        }

        return $muc;
    }

    /**
     * Kiểm tra cấu trúc đề theo BR-01 đến BR-06 (NFR-09). Dùng cả khi lập đề nháp, khi lưu và khi gửi phản biện.
     *
     * @param  list<array<string, mixed>>  $muc
     * @return array{loi: list<string>, canh_bao: list<string>}
     */
    public function kiemTraCauTruc(string $hinhThuc, array $thongSo, array $muc, ?HocPhan $hocPhan): array
    {
        $loi = [];
        $canhBao = [];
        $n = count($muc);

        $lamBai = $thongSo['lam_bai'] ?? null;
        $chuanBi = $thongSo['chuan_bi'] ?? null;
        $traLoi = $thongSo['tra_loi'] ?? null;

        // BR-01
        $tong = round(array_sum(array_map(fn ($m) => (float) $m['diemSo'], $muc)), 2);
        if (abs($tong - DiemDeThi::TONG_DIEM) > 0.001) {
            $loi[] = 'Tổng điểm các câu là '.number_format($tong, 2, ',', '').', phải bằng 10 (BR-01).';
        }

        if ($hinhThuc === 'Trắc nghiệm') {
            if ($n < 30 || $n > 60) {
                $loi[] = "Đề trắc nghiệm phải có từ 30 đến 60 câu (BR-05), ma trận hiện cho {$n} câu. Hãy chỉnh lại tổng số câu của ma trận.";
            }
            if ($lamBai === null || $lamBai < 30 || $lamBai > 60) {
                $loi[] = 'Thời gian làm bài của đề trắc nghiệm phải từ 30 đến 60 phút (BR-05).';
            }
            foreach ($muc as $m) {
                foreach (QuyenCauHoi::loiToanVen($m['cauHoi']) as $l) {
                    $loi[] = "Câu #{$m['cauHoi']->maCauHoi}: {$l} (BR-05).";
                }
            }
            if ($hocPhan && $hocPhan->soTinChi > 0) {
                $thap = 20 * (int) $hocPhan->soTinChi;
                $cao = 30 * (int) $hocPhan->soTinChi;
                if ($n < $thap || $n > $cao) {
                    $canhBao[] = "Quy định gợi ý 20 đến 30 câu trắc nghiệm cho mỗi tín chỉ: học phần {$hocPhan->soTinChi} tín chỉ nên có {$thap} đến {$cao} câu (đề có {$n} câu).";
                }
            }

            return ['loi' => $loi, 'canh_bao' => $canhBao];
        }

        if ($hinhThuc === 'Tự luận') {
            if ($n < 2) {
                $loi[] = 'Đề tự luận phải có ít nhất 2 câu hỏi (BR-04).';
            }
            if ($lamBai === null || $lamBai < 30 || $lamBai > 75) {
                $loi[] = 'Thời gian làm bài của đề tự luận phải từ 30 đến 75 phút (BR-04).';
            }
        }

        if ($hinhThuc === 'Vấn đáp') {
            $soBatBuoc = count(array_filter($muc, fn ($m) => (int) $m['phan'] === 1));
            if ($soBatBuoc < 1 || $soBatBuoc > 2) {
                $loi[] = "Đề vấn đáp phải có 1 đến 2 câu hỏi bắt buộc (BR-06), hiện có {$soBatBuoc} câu.";
            }
            if ($chuanBi === null || $chuanBi < 1 || $chuanBi > 15) {
                $loi[] = 'Thời gian chuẩn bị của đề vấn đáp phải từ 1 đến 15 phút (BR-06).';
            }
            if ($traLoi === null || $traLoi < 1 || $traLoi > 7) {
                $loi[] = 'Thời gian trình bày của đề vấn đáp phải từ 1 đến 7 phút (BR-06).';
            }
        }

        // BR-02 và BR-03: mỗi ý tối đa 0,5 điểm, tổng điểm các ý bằng điểm của câu, câu nào cũng có đáp án
        foreach ($muc as $m) {
            $goc = $this->diemGocCacY($m['cauHoi']);
            if ($goc === []) {
                $loi[] = "Câu #{$m['cauHoi']->maCauHoi} chưa có đáp án chi tiết theo ý (BR-03).";

                continue;
            }
            $cacY = DiemDeThi::quyDoiCacY($goc, (float) $m['diemSo']);
            if ($cacY === [] || max($cacY) > DiemDeThi::DIEM_Y_TOI_DA + 0.0001 || abs(array_sum($cacY) - (float) $m['diemSo']) > 0.005) {
                $loi[] = "Câu #{$m['cauHoi']->maCauHoi} ({$m['diemSo']} điểm) có ý vượt 0,5 điểm sau khi quy đổi (BR-02).";
            }
        }

        return ['loi' => $loi, 'canh_bao' => $canhBao];
    }

    /**
     * Dữ liệu hiển thị: với tự luận/vấn đáp, điểm từng ý đã quy đổi theo điểm của câu trong đề.
     *
     * @param  list<array<string, mixed>>  $muc
     * @return list<array<string, mixed>>
     */
    public function dungHienThi(array $muc): array
    {
        return array_map(function ($m) {
            /** @var CauHoi $c */
            $c = $m['cauHoi'];
            $cacY = [];
            if ($c->loaiCauHoi !== 'Trắc nghiệm') {
                $goc = $this->diemGocCacY($c);
                $diem = DiemDeThi::quyDoiCacY($goc, (float) $m['diemSo']);
                foreach ($c->dapAnTuLuanYs->sortBy('thuTu')->values() as $i => $y) {
                    $cacY[] = ['noi_dung' => $y->noiDungY, 'diem' => $diem[$i] ?? 0.0];
                }
            }

            return $m + [
                'phuong_an' => $c->loaiCauHoi === 'Trắc nghiệm' ? $c->dapAnTracNghiems->sortBy('thuTu')->values() : collect(),
                'cac_y' => $cacY,
            ];
        }, $muc);
    }

    /**
     * Dựng mục của đề từ bản nháp (chỉ có mã câu, thứ tự, phần, điểm).
     * Trả về null nếu có câu không còn hợp lệ (đã bị xóa, không còn "Đã duyệt" hoặc khác học phần).
     *
     * @param  list<array{ma: int, thu_tu: int, phan: ?int, diem: float}>  $banNhap
     * @return list<array<string, mixed>>|null
     */
    public function mucTuBanNhap(array $banNhap, string $maHocPhan, string $hinhThuc): ?array
    {
        $cauHois = $this->napCauHoi(array_column($banNhap, 'ma'))->keyBy('maCauHoi');
        $muc = [];
        foreach ($banNhap as $d) {
            $c = $cauHois->get($d['ma']);
            if (! $c || $c->maHocPhan !== $maHocPhan || $c->trangThaiDuyet !== 'Đã duyệt' || $c->loaiCauHoi !== $hinhThuc) {
                return null;
            }
            $muc[] = ['cauHoi' => $c, 'thuTu' => $d['thu_tu'], 'phan' => $d['phan'], 'diemSo' => (float) $d['diem']];
        }

        return $muc;
    }

    /** @return list<array<string, mixed>> */
    public function mucTuDeThi(DeThi $deThi): array
    {
        $dong = $deThi->deThiCauHois()->orderBy('thuTuCau')->get();
        $cauHois = $this->napCauHoi($dong->pluck('maCauHoi')->all())->keyBy('maCauHoi');

        return $dong->filter(fn ($d) => $cauHois->has($d->maCauHoi))->map(fn ($d) => [
            'cauHoi' => $cauHois->get($d->maCauHoi),
            'thuTu' => (int) $d->thuTuCau,
            'phan' => $d->phan === null ? null : (int) $d->phan,
            'diemSo' => (float) $d->diemSo,
        ])->values()->all();
    }

    /** @param  list<int|string>  $ids */
    private function napCauHoi(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return CauHoi::whereIn('maCauHoi', $ids)->with([
            'clo',
            'dapAnTracNghiems' => fn ($q) => $q->orderBy('thuTu'),
            'dapAnTuLuanYs' => fn ($q) => $q->orderBy('thuTu'),
        ])->get();
    }

    /** @return list<float> */
    private function diemGocCacY(CauHoi $c): array
    {
        return $c->dapAnTuLuanYs->sortBy('thuTu')->map(fn ($y) => (float) $y->diemY)->values()->all();
    }

    /** Dòng ma trận theo CLO, rồi mức Bloom, rồi độ khó. */
    private function sapXepDong(Collection $dong): Collection
    {
        $thuTuBloom = array_values(MucDoBloom::BANG);

        return $dong->sortBy(fn ($c) => sprintf(
            '%03d-%d-%d',
            (int) substr((string) $c->clo?->kyHieu, 3),
            (int) array_search($c->mucDoBloom, $thuTuBloom, true),
            (int) array_search($c->doKho, self::DO_KHO, true)
        ))->values();
    }

    private function khoa(int|string $clo, string $bloom, string $doKho): string
    {
        return (int) $clo.'|'.$bloom.'|'.$doKho;
    }

    /** Xáo ngẫu nhiên trong từng nhóm cùng số lần đã dùng; câu ít được dùng đứng trước. */
    private function xaoTheoMucDungIt(Collection $cacCau, Collection $suDung): array
    {
        $theoNhom = $cacCau->groupBy(fn (CauHoi $c) => (int) ($suDung[$c->maCauHoi] ?? 0))->sortKeys();
        $ketQua = [];
        foreach ($theoNhom as $nhom) {
            $mang = $nhom->all();
            shuffle($mang);
            array_push($ketQua, ...$mang);
        }

        return $ketQua;
    }

    /**
     * Các cặp câu có độ tương đồng từ ngưỡng của ma trận trở lên (chưa được đánh dấu "Đã xử lý").
     *
     * @param  list<int>  $ids
     * @return array<int, array<int, true>>
     */
    private function cacCapTrung(array $ids, float $nguong): array
    {
        $trung = [];
        if (count($ids) < 2) {
            return $trung;
        }

        $cacCap = KiemTraTrungLap::whereIn('maCauHoi', $ids)->whereIn('maCauHoiTrung', $ids)
            ->where('doTuongDong', '>=', $nguong)
            ->where('trangThaiCanhBao', '!=', 'Đã xử lý')
            ->get(['maCauHoi', 'maCauHoiTrung']);

        foreach ($cacCap as $cap) {
            $trung[(int) $cap->maCauHoi][(int) $cap->maCauHoiTrung] = true;
            $trung[(int) $cap->maCauHoiTrung][(int) $cap->maCauHoi] = true;
        }

        return $trung;
    }

    /** @param  array<int, true>  $daChon */
    private function trungVoiCauDaChon(int $ma, array $daChon, array $trung): bool
    {
        foreach ($trung[$ma] ?? [] as $khac => $_) {
            if (isset($daChon[$khac])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Đối chiếu đề với ma trận (UC-15 bước 2): số câu cần theo ma trận và số câu đề đang có ở từng dòng.
     *
     * @param  list<array<string, mixed>>  $muc
     * @return list<array{clo: string, bloom: string, do_kho: string, can: int, co: int, khop: bool}>
     */
    public function doiChieuMaTran(DeThi $deThi, array $muc): array
    {
        $deThi->loadMissing('maTranDeThi.chiTietMaTrans.clo');

        $co = [];
        foreach ($muc as $m) {
            $k = $this->khoa($m['cauHoi']->maClo, $m['cauHoi']->mucDoBloom, $m['cauHoi']->doKho);
            $co[$k] = ($co[$k] ?? 0) + 1;
        }

        $hang = [];
        foreach ($this->sapXepDong($deThi->maTranDeThi->chiTietMaTrans) as $dong) {
            $k = $this->khoa($dong->maClo, $dong->mucDoBloom, $dong->doKho);
            $soCo = $co[$k] ?? 0;
            $hang[] = [
                'clo' => (string) $dong->clo?->kyHieu,
                'bloom' => MucDoBloom::sangNhan($dong->mucDoBloom),
                'do_kho' => $dong->doKho,
                'can' => (int) $dong->soLuongCau,
                'co' => $soCo,
                'khop' => $soCo === (int) $dong->soLuongCau,
            ];
        }

        return $hang;
    }

    /**
     * BR-09: các đề đã duyệt cùng học phần và hình thức có tỷ lệ câu trùng với đề này quá 30%
     * (sự khác biệt phải đạt tối thiểu 70%).
     *
     * @param  list<array<string, mixed>>  $muc
     * @return list<array{ma: int, ten: string, ty_le: float}>
     */
    public function deTrungNhieu(DeThi $deThi, array $muc): array
    {
        $n = count($muc);
        $maHocPhan = $deThi->maTranDeThi?->maHocPhan;
        if ($n === 0 || $maHocPhan === null) {
            return [];
        }
        $cauCuaDe = array_map(fn ($m) => (int) $m['cauHoi']->maCauHoi, $muc);

        $ketQua = [];
        $cacDe = DeThi::where('trangThai', 'Đã duyệt')->where('hinhThucThi', $deThi->hinhThucThi)
            ->where('maDeThi', '!=', $deThi->getKey())
            ->whereHas('maTranDeThi', fn ($q) => $q->where('maHocPhan', $maHocPhan))
            ->with('deThiCauHois')->get();

        foreach ($cacDe as $khac) {
            $chung = count(array_intersect($cauCuaDe, $khac->deThiCauHois->pluck('maCauHoi')->map(fn ($v) => (int) $v)->all()));
            $tyLe = round($chung * 100 / $n, 1);
            if ($tyLe > 30) {
                $ketQua[] = ['ma' => (int) $khac->getKey(), 'ten' => $khac->tenDeThi, 'ty_le' => $tyLe];
            }
        }
        usort($ketQua, fn ($a, $b) => $b['ty_le'] <=> $a['ty_le']);

        return $ketQua;
    }
}
