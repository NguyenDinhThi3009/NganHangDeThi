<?php

namespace App\Support;

/** Kiểm tra trình độ học vị của người được phân công (BR-10: thạc sĩ trở lên). */
class HocVi
{
    public static function tuThacSiTroLen(?string $trinhDo): bool
    {
        $chuoi = mb_strtolower(trim((string) $trinhDo));
        if ($chuoi === '') {
            return false;
        }
        foreach (['thạc sĩ', 'thac si', 'tiến sĩ', 'tien si', 'phó giáo sư', 'giáo sư', 'master', 'phd', 'doctor'] as $tu) {
            if (str_contains($chuoi, $tu)) {
                return true;
            }
        }

        // Viết tắt: ThS, TS, TSKH, PGS, GS, PGS.TS...
        return (bool) preg_match('/(^|[^\p{L}])(ths|ts|tskh|pgs|gs)([^\p{L}]|$)/u', $chuoi);
    }
}
