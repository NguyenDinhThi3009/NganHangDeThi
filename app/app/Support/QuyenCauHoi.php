<?php

namespace App\Support;

use App\Models\NganHangDeThi\CauHoi;
use App\Models\NganHangDeThi\DeThiCauHoi;
use App\Models\NganHangDeThi\NguoiDung;

/** Quy tắc quyền thao tác và toàn vẹn dữ liệu của câu hỏi (UC-08, UC-09, BR-02, BR-15). */
class QuyenCauHoi
{
    /** Quản trị viên thao tác mọi câu hỏi, giảng viên chỉ thao tác câu hỏi do mình tạo. */
    public static function duocThaoTac(NguoiDung $nguoiDung, CauHoi $cauHoi): bool
    {
        return $nguoiDung->vaiTro->tenVaiTro === 'Quản trị viên'
            || $cauHoi->maNguoiTao === $nguoiDung->getKey();
    }

    /** Câu hỏi đang nằm trong một đề thi bất kỳ -> không được xóa. */
    public static function trongDeThi(CauHoi $cauHoi): bool
    {
        return DeThiCauHoi::where('maCauHoi', $cauHoi->getKey())->exists();
    }

    /** BR-15: nằm trong đề thi đã rời trạng thái "Nháp" -> khóa sửa. */
    public static function biKhoaSua(CauHoi $cauHoi): bool
    {
        return DeThiCauHoi::where('maCauHoi', $cauHoi->getKey())
            ->whereHas('deThi', fn ($q) => $q->where('trangThai', '!=', 'Nháp'))
            ->exists();
    }

    /**
     * Kiểm tra toàn vẹn đáp án của câu hỏi (đã nạp sẵn quan hệ đáp án).
     *
     * @return list<string>
     */
    public static function loiToanVen(CauHoi $cauHoi): array
    {
        $loi = [];

        if ($cauHoi->loaiCauHoi === 'Trắc nghiệm') {
            $phuongAn = $cauHoi->dapAnTracNghiems;
            if ($phuongAn->count() < 3) {
                $loi[] = 'Câu trắc nghiệm cần ít nhất 3 phương án.';
            }
            if ($phuongAn->where('laDapAnDung', true)->count() !== 1) {
                $loi[] = 'Câu trắc nghiệm phải có đúng 1 đáp án đúng.';
            }
        } elseif ($cauHoi->loaiCauHoi === 'Tự luận') {
            $cacY = $cauHoi->dapAnTuLuanYs;
            if ($cacY->isEmpty()) {
                $loi[] = 'Câu tự luận cần có đáp án theo ý.';
            }
            foreach ($cacY as $y) {
                $diem = (float) $y->diemY;
                if ($diem <= 0 || $diem > 0.5) {
                    $loi[] = "Ý {$y->thuTu} có điểm không hợp lệ: mỗi ý phải lớn hơn 0 và tối đa 0,5 điểm (BR-02).";
                }
            }
        }

        return $loi;
    }
}
