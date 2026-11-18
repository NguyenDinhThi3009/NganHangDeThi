<?php

namespace App\Http\Controllers;

use App\Models\NganHangDeThi\DeThi;
use App\Models\NganHangDeThi\NhatKyThaoTac;
use App\Services\TaoDeThiService;
use App\Support\NhatKy;
use App\Support\QuyenDeThi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Phản biện đề thi (UC-15, FR-16): Chờ phản biện chuyển thành Chờ duyệt (Đạt) hoặc về Nháp (Cần chỉnh sửa). */
class PhanBienDeThiController extends Controller
{
    public function __construct(private readonly TaoDeThiService $dich) {}

    public function index(Request $request): View
    {
        $nguoi = $request->user();
        $maHocPhans = QuyenDeThi::maHocPhanPhanCong($nguoi, 'Phản biện');

        $cho = DeThi::with(['maTranDeThi.hocPhan', 'nguoiTao'])->withCount('deThiCauHois')
            ->where('trangThai', 'Chờ phản biện')
            ->where('maNguoiTao', '!=', $nguoi->getKey())
            ->whereHas('maTranDeThi', fn ($q) => $q->whereIn('maHocPhan', $maHocPhans))
            ->orderBy('maDeThi')->get();

        $daLam = DeThi::with(['maTranDeThi.hocPhan', 'nguoiTao'])
            ->where('maNguoiPhanBien', $nguoi->getKey())
            ->orderByDesc('ngayPhanBien')->limit(50)->get();

        return view('phan-bien.index', ['cho' => $cho, 'daLam' => $daLam, 'coPhanCong' => $maHocPhans !== []]);
    }

    public function show(Request $request, DeThi $deThi): View
    {
        $deThi->load(['maTranDeThi.hocPhan', 'nguoiTao', 'nguoiPhanBien']);
        $duocPhanBien = $this->duocPhanBien($request, $deThi);
        abort_unless($duocPhanBien || (int) $deThi->maNguoiPhanBien === (int) $request->user()->getKey(), 403, 'Bạn không thuộc phân công phản biện của học phần này.');

        $muc = $this->dich->mucTuDeThi($deThi);
        $ketQua = $this->dich->kiemTraCauTruc(
            $deThi->hinhThucThi,
            ['lam_bai' => $deThi->thoiGianLamBai, 'chuan_bi' => $deThi->thoiGianChuanBi, 'tra_loi' => $deThi->thoiGianTraLoi],
            $muc,
            $deThi->maTranDeThi?->hocPhan
        );

        return view('phan-bien.show', [
            'deThi' => $deThi,
            'muc' => $this->dich->dungHienThi($muc),
            'tongDiem' => round(array_sum(array_map(fn ($m) => $m['diemSo'], $muc)), 2),
            'doiChieu' => $this->dich->doiChieuMaTran($deThi, $muc),
            'loiCauTruc' => $ketQua['loi'],
            'canhBao' => $ketQua['canh_bao'],
            'duocPhanBien' => $duocPhanBien,
        ]);
    }

    public function ghiKetQua(Request $request, DeThi $deThi): RedirectResponse
    {
        abort_unless($this->duocPhanBien($request, $deThi), 403, 'Bạn không thuộc phân công phản biện của học phần này.');

        $d = $request->validate([
            'ket_qua' => ['required', 'in:Đạt,Cần chỉnh sửa'],
            'nhan_xet' => ['nullable', 'string', 'max:2000', 'required_if:ket_qua,Cần chỉnh sửa'],
        ], [
            'ket_qua.required' => 'Vui lòng chọn kết quả phản biện.',
            'ket_qua.in' => 'Kết quả phản biện chỉ là Đạt hoặc Cần chỉnh sửa.',
            'nhan_xet.required_if' => 'Vui lòng ghi nhận xét để giảng viên biên soạn biết cần chỉnh gì.',
        ]);

        if ($d['ket_qua'] === 'Đạt') {
            $loi = $this->dich->kiemTraCauTruc(
                $deThi->hinhThucThi,
                ['lam_bai' => $deThi->thoiGianLamBai, 'chuan_bi' => $deThi->thoiGianChuanBi, 'tra_loi' => $deThi->thoiGianTraLoi],
                $this->dich->mucTuDeThi($deThi),
                $deThi->maTranDeThi?->hocPhan
            )['loi'];
            if ($loi) {
                return back()->withInput()->with('loi', 'Đề chưa thỏa cấu trúc nên không thể đánh giá Đạt: '.implode(' ', $loi));
            }
        }

        $ketQuaCuoi = DB::transaction(function () use ($request, $deThi, $d) {
            $moi = DeThi::whereKey($deThi->getKey())->lockForUpdate()->first();
            if ($moi->trangThai !== 'Chờ phản biện') {
                return null;
            }
            $moi->update([
                'maNguoiPhanBien' => $request->user()->getKey(),
                'ngayPhanBien' => now(),
                'ketQuaPhanBien' => $d['ket_qua'],
                'trangThai' => $d['ket_qua'] === 'Đạt' ? 'Chờ duyệt' : 'Nháp',
            ]);

            return $d['ket_qua'];
        });

        if ($ketQuaCuoi === null) {
            return redirect()->route('phan-bien.index')->with('loi', 'Đề này không còn ở trạng thái Chờ phản biện.');
        }

        $nhanXet = trim((string) ($d['nhan_xet'] ?? ''));
        NhatKy::ghi($request->user()->getKey(), 'Phản biện đề thi', 'Đề thi', $deThi->getKey(), "Kết quả: {$d['ket_qua']}".($nhanXet !== '' ? "; Nhận xét: {$nhanXet}" : ''));

        return redirect()->route('phan-bien.index')->with('thanh_cong', $d['ket_qua'] === 'Đạt'
            ? 'Đã ghi kết quả Đạt. Đề chuyển sang Chờ duyệt của Trưởng bộ môn.'
            : 'Đã ghi kết quả Cần chỉnh sửa. Đề được trả về giảng viên biên soạn ở trạng thái Nháp.');
    }

    /** Được phân công phản biện học phần, đề đang Chờ phản biện, và không phải người biên soạn đề. */
    private function duocPhanBien(Request $request, DeThi $deThi): bool
    {
        $nguoi = $request->user();

        return $deThi->trangThai === 'Chờ phản biện'
            && (int) $deThi->maNguoiTao !== (int) $nguoi->getKey()
            && QuyenDeThi::duocPhanCong($nguoi, QuyenDeThi::maHocPhan($deThi), 'Phản biện');
    }
}
