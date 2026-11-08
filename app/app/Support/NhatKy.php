<?php

namespace App\Support;

use App\Models\NganHangDeThi\NhatKyThaoTac;

/** Ghi nhật ký thao tác người dùng vào bảng nhatkythaotac (UC-13). */
class NhatKy
{
    public static function ghi(int $maNguoiDung, string $hanhDong, string $doiTuong, ?int $maDoiTuong, ?string $chiTiet = null): void
    {
        NhatKyThaoTac::create([
            'maNguoiDung' => $maNguoiDung,
            'hanhDong' => $hanhDong,
            'doiTuongTacDong' => $doiTuong,
            'thoiGian' => now(),
            'chiTiet' => $chiTiet,
            'maDoiTuong' => $maDoiTuong,
        ]);
    }
}
