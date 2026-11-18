<?php

namespace App\Support;

/**
 * Tính điểm cho đề thi (BR-01, BR-02): tổng điểm các câu bằng 10, mỗi ý tối đa 0,5 điểm,
 * tổng điểm các ý bằng điểm của câu. Mọi phép tính dùng số nguyên (đơn vị phần trăm điểm)
 * để không bị lệch do số thực.
 */
class DiemDeThi
{
    public const TONG_DIEM = 10.0;

    /** Điểm tối đa của một ý (BR-02). */
    public const DIEM_Y_TOI_DA = 0.5;

    /** Điểm của một câu tự luận/vấn đáp là bội số của 0,25. */
    private const BUOC = 25;

    /**
     * Đề trắc nghiệm: các câu cùng điểm, tổng đúng 10. Nếu 10 không chia hết cho số câu,
     * vài câu đầu hơn 0,01 điểm.
     *
     * @return list<float>
     */
    public static function chiaDeuTracNghiem(int $soCau): array
    {
        if ($soCau < 1) {
            return [];
        }
        $tong = (int) round(self::TONG_DIEM * 100);
        $co = intdiv($tong, $soCau);
        $du = $tong - $co * $soCau;

        $ketQua = [];
        for ($i = 0; $i < $soCau; $i++) {
            $ketQua[] = ($co + ($i < $du ? 1 : 0)) / 100;
        }

        return $ketQua;
    }

    /**
     * Chia $tongDiem cho các câu tự luận/vấn đáp theo trọng số (tổng điểm gốc của các ý),
     * làm tròn theo bội số 0,25 và không để câu nào vượt mức trần (để mỗi ý sau quy đổi vẫn <= 0,5).
     *
     * @param  list<array{w: float, tran: float}>  $cacCau  w: trọng số, tran: điểm tối đa của câu
     * @return list<float>|null  null nếu không thể chia (trần quá thấp hoặc quá nhiều câu)
     */
    public static function chiaTheoTrongSo(array $cacCau, float $tongDiem): ?array
    {
        $n = count($cacCau);
        if ($n === 0) {
            return [];
        }

        $tongDon = (int) round($tongDiem * 100 / self::BUOC);
        if ($tongDon < $n) {
            return null; // mỗi câu cần ít nhất 0,25 điểm
        }

        $tran = [];
        $tongTran = 0;
        foreach ($cacCau as $i => $c) {
            $tran[$i] = (int) floor(round($c['tran'] * 100, 6) / self::BUOC);
            if ($tran[$i] < 1) {
                return null;
            }
            $tongTran += $tran[$i];
        }
        if ($tongTran < $tongDon) {
            return null;
        }

        $tongW = array_sum(array_column($cacCau, 'w'));
        $don = [];
        $mucTieu = [];
        foreach ($cacCau as $i => $c) {
            $mucTieu[$i] = $tongW > 0 ? $tongDon * $c['w'] / $tongW : $tongDon / $n;
            $don[$i] = max(1, min($tran[$i], (int) floor($mucTieu[$i])));
        }

        $con = $tongDon - array_sum($don);

        // Thêm từng đơn vị cho câu còn chỗ và thiếu nhiều nhất so với mục tiêu
        while ($con > 0) {
            $chon = null;
            $thieuNhat = -INF;
            foreach ($don as $i => $v) {
                if ($v < $tran[$i] && ($mucTieu[$i] - $v) > $thieuNhat) {
                    $thieuNhat = $mucTieu[$i] - $v;
                    $chon = $i;
                }
            }
            if ($chon === null) {
                return null;
            }
            $don[$chon]++;
            $con--;
        }

        // Bớt từng đơn vị ở câu đang vượt mục tiêu nhiều nhất (và còn > 1 đơn vị)
        while ($con < 0) {
            $chon = null;
            $vuotNhat = -INF;
            foreach ($don as $i => $v) {
                if ($v > 1 && ($v - $mucTieu[$i]) > $vuotNhat) {
                    $vuotNhat = $v - $mucTieu[$i];
                    $chon = $i;
                }
            }
            if ($chon === null) {
                return null;
            }
            $don[$chon]--;
            $con++;
        }

        $ketQua = [];
        foreach ($cacCau as $i => $_) {
            $ketQua[] = $don[$i] * self::BUOC / 100;
        }

        return $ketQua;
    }

    /**
     * Điểm tối đa một câu có thể nhận để mọi ý sau quy đổi vẫn <= 0,5.
     *
     * @param  list<float>  $diemGoc  điểm gốc của từng ý trong ngân hàng
     */
    public static function tranDiemCau(array $diemGoc): float
    {
        $tong = array_sum($diemGoc);
        $max = $diemGoc ? max($diemGoc) : 0;

        return $max > 0 ? self::DIEM_Y_TOI_DA * $tong / $max : 0.0;
    }

    /**
     * Quy đổi điểm các ý của một câu theo điểm của câu trong đề (tổng đúng bằng $diemCau).
     *
     * @param  list<float>  $diemGoc  điểm gốc của từng ý theo thứ tự
     * @return list<float>  điểm từng ý sau quy đổi (làm tròn 0,01)
     */
    public static function quyDoiCacY(array $diemGoc, float $diemCau): array
    {
        $tong = array_sum($diemGoc);
        if ($diemGoc === [] || $tong <= 0) {
            return [];
        }

        $dich = (int) round($diemCau * 100);
        $xu = [];
        $tongXu = 0;
        foreach ($diemGoc as $i => $d) {
            $xu[$i] = (int) round($d * $dich / $tong);
            $tongXu += $xu[$i];
        }

        // Phân phần lệch do làm tròn, mỗi lần 0,01 điểm: thêm vào ý lớn (còn <= 0,5), bớt ở ý nhỏ
        $lech = $dich - $tongXu;
        $thuTu = array_keys($xu);
        usort($thuTu, fn ($a, $b) => $xu[$a] <=> $xu[$b]);
        if ($lech > 0) {
            $thuTu = array_reverse($thuTu);
        }
        $toiDa = (int) round(self::DIEM_Y_TOI_DA * 100);
        $vong = 0;
        while ($lech !== 0 && $vong < 1000) {
            foreach ($thuTu as $i) {
                if ($lech === 0) {
                    break;
                }
                if ($lech > 0 && $xu[$i] < $toiDa) {
                    $xu[$i]++;
                    $lech--;
                } elseif ($lech < 0 && $xu[$i] > 1) {
                    $xu[$i]--;
                    $lech++;
                }
            }
            $vong++;
        }

        return array_map(fn ($v) => $v / 100, $xu);
    }
}
