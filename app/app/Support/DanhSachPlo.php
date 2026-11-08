<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Đọc và kiểm tra các dòng PLO của form (dùng chung cho màn hình CTĐT và nhập từ Bản mô tả). */
class DanhSachPlo
{
    /**
     * Dòng để trống hoàn toàn bị bỏ qua (với PLO đã có thì nghĩa là xóa PLO đó).
     *
     * @return list<array{ma: int|null, ky_hieu: string, mo_ta: string}>
     */
    public static function doc(Request $request): array
    {
        $kyHieus = (array) $request->input('plo_ky_hieu', []);
        $moTas = (array) $request->input('plo_mo_ta', []);
        $maPlos = (array) $request->input('plo_ma', []);

        $hang = [];
        $loi = [];
        $daCo = [];

        foreach ($kyHieus as $i => $kyHieu) {
            $kyHieu = strtoupper(preg_replace('/\s+/', '', (string) $kyHieu));
            $moTa = trim((string) ($moTas[$i] ?? ''));
            $ma = isset($maPlos[$i]) && $maPlos[$i] !== '' ? (int) $maPlos[$i] : null;

            if ($kyHieu === '' && $moTa === '') {
                continue;
            }
            if (! preg_match('/^PLO\d{1,2}$/', $kyHieu)) {
                $loi[] = 'Dòng '.((int) $i + 1).': ký hiệu PLO phải có dạng PLO1, PLO2, ...';

                continue;
            }
            if ($moTa === '') {
                $loi[] = "{$kyHieu}: nội dung PLO không được để trống.";

                continue;
            }
            if (isset($daCo[$kyHieu])) {
                $loi[] = "Ký hiệu {$kyHieu} bị trùng.";

                continue;
            }

            $daCo[$kyHieu] = true;
            $hang[] = ['ma' => $ma, 'ky_hieu' => $kyHieu, 'mo_ta' => $moTa];
        }

        if ($loi) {
            throw ValidationException::withMessages(['plo' => $loi]);
        }

        return $hang;
    }
}
