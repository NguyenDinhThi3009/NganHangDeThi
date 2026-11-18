<?php

namespace App\Support;

use App\Models\NganHangDeThi\DeThi;
use App\Models\NganHangDeThi\NguoiDung;
use App\Models\NganHangDeThi\PhanCongHocPhan;

/** Quy tắc quyền theo vai trò trong học phần và bộ môn (UC-15, UC-16, UC-20). */
class QuyenDeThi
{
    public static function maHocPhan(DeThi $deThi): ?string
    {
        return $deThi->maTranDeThi?->maHocPhan;
    }

    /** Học phần mà người dùng đang được phân công (Biên soạn hoặc Phản biện) còn hiệu lực. */
    public static function maHocPhanPhanCong(NguoiDung $nguoiDung, string $vaiTro): array
    {
        return PhanCongHocPhan::where('maNguoiDung', $nguoiDung->getKey())
            ->where('vaiTroPhanCong', $vaiTro)->where('trangThai', 'Đang hiệu lực')
            ->pluck('maHocPhan')->unique()->values()->all();
    }

    public static function duocPhanCong(NguoiDung $nguoiDung, ?string $maHocPhan, string $vaiTro): bool
    {
        return $maHocPhan !== null && in_array($maHocPhan, self::maHocPhanPhanCong($nguoiDung, $vaiTro), true);
    }

    /** Trưởng bộ môn của bộ môn phụ trách học phần chứa đề. */
    public static function laTruongBoMonCuaDe(NguoiDung $nguoiDung, DeThi $deThi): bool
    {
        if ($nguoiDung->vaiTro?->tenVaiTro !== 'Trưởng bộ môn' || $nguoiDung->maBoMon === null) {
            return false;
        }
        $maBoMon = $deThi->maTranDeThi?->hocPhan?->maBoMon;

        return $maBoMon !== null && (int) $maBoMon === (int) $nguoiDung->maBoMon;
    }
}
