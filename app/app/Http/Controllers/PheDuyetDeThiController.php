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

/** Phê duyệt đề thi vào ngân hàng (UC-16, FR-17): Chờ duyệt chuyển thành Đã duyệt hoặc Bị từ chối. */
class PheDuyetDeThiController extends Controller
{
    public function __construct(private readonly TaoDeThiService $dich) {}

    public function index(Request $request): View
    {
        $nguoi = $request->user();
        $theoBoMon = fn ($q) => $q->whereHas('hocPhan', fn ($h) => $nguoi->maBoMon === null ? $h->whereRaw('1 = 0') : $h->where('maBoMon', $nguoi->maBoMon));

        $cho = DeThi::with(['maTranDeThi.hocPhan', 'nguoiTao', 'nguoiPhanBien'])->withCount('deThiCauHois')
            ->where('trangThai', 'Chờ duyệt')
            ->whereHas('maTranDeThi', $theoBoMon)
            ->orderBy('maDeThi')->get();

        $daXuLy = DeThi::with(['maTranDeThi.hocPhan', 'nguoiTao'])
            ->whereIn('trangThai', ['Đã duyệt', 'Bị từ chối'])
            ->whereHas('maTranDeThi', $theoBoMon)
            ->orderByDesc('ngayDuyet')->limit(50)->get();

        return view('phe-duyet.index', ['cho' => $cho, 'daXuLy' => $daXuLy, 'coBoMon' => $nguoi->maBoMon !== null]);
    }

    public function show(Request $request, DeThi $deThi): View
    {
        $deThi->load(['maTranDeThi.hocPhan', 'nguoiTao', 'nguoiPhanBien', 'nguoiDuyet']);
        abort_unless(QuyenDeThi::laTruongBoMonCuaDe($request->user(), $deThi)
            && in_array($deThi->trangThai, ['Chờ duyệt', 'Đã duyệt', 'Bị từ chối'], true), 403, 'Bạn không có quyền xem đề này.');

        $muc = $this->dich->mucTuDeThi($deThi);
        $ketQua = $this->dich->kiemTraCauTruc(
            $deThi->hinhThucThi,
            ['lam_bai' => $deThi->thoiGianLamBai, 'chuan_bi' => $deThi->thoiGianChuanBi, 'tra_loi' => $deThi->thoiGianTraLoi],
            $muc,
            $deThi->maTranDeThi?->hocPhan
        );

        return view('phe-duyet.show', [
            'deThi' => $deThi,
            'muc' => $this->dich->dungHienThi($muc),
            'tongDiem' => round(array_sum(array_map(fn ($m) => $m['diemSo'], $muc)), 2),
            'doiChieu' => $this->dich->doiChieuMaTran($deThi, $muc),
            'loiCauTruc' => $ketQua['loi'],
            'canhBao' => $ketQua['canh_bao'],
            'deTrung' => $this->dich->deTrungNhieu($deThi, $muc),
            'nhanXet' => $this->nhatKyGanNhat($deThi, 'Phản biện đề thi'),
            'lyDoTuChoi' => $deThi->trangThai === 'Bị từ chối' ? $this->nhatKyGanNhat($deThi, 'Từ chối đề thi') : null,
        ]);
    }

    public function duyet(Request $request, DeThi $deThi): RedirectResponse
    {
        $this->chanNeuKhongDuocQuyet($request, $deThi);

        // BR-01, BR-03, BR-05: đề không thỏa cấu trúc hoặc thiếu đáp án thì chặn phê duyệt
        $loi = $this->dich->kiemTraCauTruc(
            $deThi->hinhThucThi,
            ['lam_bai' => $deThi->thoiGianLamBai, 'chuan_bi' => $deThi->thoiGianChuanBi, 'tra_loi' => $deThi->thoiGianTraLoi],
            $this->dich->mucTuDeThi($deThi),
            $deThi->maTranDeThi?->hocPhan
        )['loi'];
        if ($loi) {
            return back()->with('loi', 'Không thể phê duyệt: '.implode(' ', $loi));
        }

        if (! $this->doiTrangThai($request, $deThi, 'Đã duyệt')) {
            return redirect()->route('phe-duyet.index')->with('loi', 'Đề này không còn ở trạng thái Chờ duyệt.');
        }
        NhatKy::ghi($request->user()->getKey(), 'Phê duyệt đề thi', 'Đề thi', $deThi->getKey());

        return redirect()->route('phe-duyet.index')->with('thanh_cong', 'Đã phê duyệt, đề thi được đưa vào ngân hàng đề thi.');
    }

    public function tuChoi(Request $request, DeThi $deThi): RedirectResponse
    {
        $this->chanNeuKhongDuocQuyet($request, $deThi);
        $lyDo = trim((string) $request->validate(['ly_do' => ['nullable', 'string', 'max:1000']])['ly_do'] ?? '');

        if (! $this->doiTrangThai($request, $deThi, 'Bị từ chối')) {
            return redirect()->route('phe-duyet.index')->with('loi', 'Đề này không còn ở trạng thái Chờ duyệt.');
        }
        NhatKy::ghi($request->user()->getKey(), 'Từ chối đề thi', 'Đề thi', $deThi->getKey(), $lyDo !== '' ? "Lý do: {$lyDo}" : null);

        return redirect()->route('phe-duyet.index')->with('thanh_cong', 'Đã từ chối đề thi.');
    }

    /** Điều kiện của UC-16: đúng Trưởng bộ môn của bộ môn, đề Chờ duyệt và đã được phản biện Đạt (BR-11). */
    private function chanNeuKhongDuocQuyet(Request $request, DeThi $deThi): void
    {
        $deThi->loadMissing('maTranDeThi.hocPhan');
        abort_unless(QuyenDeThi::laTruongBoMonCuaDe($request->user(), $deThi), 403, 'Bạn không phải Trưởng bộ môn của học phần này.');
        abort_unless($deThi->trangThai === 'Chờ duyệt' && $deThi->ketQuaPhanBien === 'Đạt', 403, 'Chỉ phê duyệt đề đã qua phản biện với kết quả Đạt.');
    }

    private function doiTrangThai(Request $request, DeThi $deThi, string $trangThaiMoi): bool
    {
        return DB::transaction(function () use ($request, $deThi, $trangThaiMoi) {
            $moi = DeThi::whereKey($deThi->getKey())->lockForUpdate()->first();
            if ($moi->trangThai !== 'Chờ duyệt' || $moi->ketQuaPhanBien !== 'Đạt') {
                return false;
            }
            $moi->update([
                'trangThai' => $trangThaiMoi,
                'maNguoiDuyet' => $request->user()->getKey(),
                'ngayDuyet' => now(),
            ]);

            return true;
        });
    }

    private function nhatKyGanNhat(DeThi $deThi, string $hanhDong): ?string
    {
        return NhatKyThaoTac::where('doiTuongTacDong', 'Đề thi')->where('maDoiTuong', $deThi->getKey())
            ->where('hanhDong', $hanhDong)->orderByDesc('maNhatKyThaoTac')->value('chiTiet');
    }
}
