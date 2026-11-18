<?php

namespace App\Http\Controllers;

use App\Models\NganHangDeThi\HocPhan;
use App\Models\NganHangDeThi\NguoiDung;
use App\Models\NganHangDeThi\PhanCongHocPhan;
use App\Support\HocVi;
use App\Support\NhatKy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Phân công giảng viên biên soạn/phản biện theo học phần, học kỳ, năm học (UC-20). */
class PhanCongHocPhanController extends Controller
{
    private const VAI_TRO = ['Biên soạn', 'Phản biện'];

    public function index(Request $request): View
    {
        $hocPhans = $this->hocPhanTrongPham($request)->orderBy('maHocPhan')->get(['maHocPhan', 'tenHocPhan']);
        $locTrangThai = in_array($request->query('trang_thai'), ['Đang hiệu lực', 'Kết thúc'], true) ? $request->query('trang_thai') : 'Đang hiệu lực';

        $phanCongs = PhanCongHocPhan::with(['hocPhan', 'nguoiDung.boMon'])
            ->whereIn('maHocPhan', $hocPhans->pluck('maHocPhan'))
            ->where('trangThai', $locTrangThai)
            ->orderBy('maHocPhan')->orderByDesc('namHoc')->orderByDesc('hocKy')->orderBy('vaiTroPhanCong')
            ->limit(300)->get();

        $nguoiDungs = NguoiDung::with('boMon')
            ->whereHas('vaiTro', fn ($q) => $q->whereIn('tenVaiTro', ['Giảng viên', 'Trưởng bộ môn']))
            ->where('trangThai', 'Hoạt động')->orderBy('hoTen')->get();

        $nam = (int) now()->format('Y');
        $namHocHienTai = (int) now()->format('n') >= 8 ? $nam.'-'.($nam + 1) : ($nam - 1).'-'.$nam;

        return view('phan-cong.index', [
            'phanCongs' => $phanCongs,
            'hocPhans' => $hocPhans,
            'nguoiDungs' => $nguoiDungs,
            'vaiTros' => self::VAI_TRO,
            'locTrangThai' => $locTrangThai,
            'namHocMacDinh' => $namHocHienTai,
            'khongCoHocPhan' => $hocPhans->isEmpty(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'ma_hoc_phan' => ['required', 'string'],
            'ma_nguoi_dung' => ['required', 'integer', Rule::exists('nguoidung', 'maNguoiDung')],
            'vai_tro' => ['required', Rule::in(self::VAI_TRO)],
            'hoc_ky' => ['required', Rule::in(['1', '2', '3'])],
            'nam_hoc' => ['required', 'regex:/^\d{4}-\d{4}$/'],
        ], [
            'ma_hoc_phan.required' => 'Vui lòng chọn học phần.',
            'ma_nguoi_dung.required' => 'Vui lòng chọn giảng viên.',
            'vai_tro.required' => 'Vui lòng chọn vai trò trong học phần.',
            'hoc_ky.in' => 'Học kỳ phải là 1, 2 hoặc 3 (học kỳ hè).',
            'nam_hoc.regex' => 'Năm học có dạng 2026-2027.',
        ]);

        [$dau, $cuoi] = array_map('intval', explode('-', $d['nam_hoc']));
        if ($cuoi !== $dau + 1) {
            throw ValidationException::withMessages(['nam_hoc' => 'Năm học phải gồm hai năm liên tiếp, ví dụ 2026-2027.']);
        }

        $hocPhan = $this->hocPhanTrongPham($request)->where('maHocPhan', $d['ma_hoc_phan'])->first();
        if (! $hocPhan) {
            throw ValidationException::withMessages(['ma_hoc_phan' => 'Học phần không thuộc bộ môn của bạn.']);
        }

        $nguoi = NguoiDung::with('vaiTro')->findOrFail((int) $d['ma_nguoi_dung']);
        if ($nguoi->trangThai !== 'Hoạt động' || ! in_array($nguoi->vaiTro?->tenVaiTro, ['Giảng viên', 'Trưởng bộ môn'], true)) {
            throw ValidationException::withMessages(['ma_nguoi_dung' => 'Chỉ phân công được giảng viên hoặc trưởng bộ môn đang hoạt động.']);
        }
        // BR-10: thạc sĩ trở lên
        if (! HocVi::tuThacSiTroLen($nguoi->trinhDoHocVi)) {
            $hienTai = $nguoi->trinhDoHocVi ? "hiện ghi: {$nguoi->trinhDoHocVi}" : 'chưa khai báo trình độ học vị';
            throw ValidationException::withMessages(['ma_nguoi_dung' => "{$nguoi->hoTen} chưa đủ điều kiện (BR-10: thạc sĩ trở lên; {$hienTai})."]);
        }

        $cungKy = PhanCongHocPhan::where('maHocPhan', $hocPhan->maHocPhan)->where('maNguoiDung', $nguoi->getKey())
            ->where('hocKy', (int) $d['hoc_ky'])->where('namHoc', $d['nam_hoc']);

        if ((clone $cungKy)->where('vaiTroPhanCong', $d['vai_tro'])->exists()) {
            throw ValidationException::withMessages(['ma_nguoi_dung' => 'Đã có phân công này (cùng học phần, vai trò, học kỳ, năm học).']);
        }
        if ((clone $cungKy)->where('vaiTroPhanCong', '!=', $d['vai_tro'])->where('trangThai', 'Đang hiệu lực')->exists()) {
            throw ValidationException::withMessages(['ma_nguoi_dung' => 'Một người không đồng thời biên soạn và phản biện cùng học phần trong cùng học kỳ.']);
        }

        $phanCong = PhanCongHocPhan::create([
            'maHocPhan' => $hocPhan->maHocPhan,
            'maNguoiDung' => $nguoi->getKey(),
            'vaiTroPhanCong' => $d['vai_tro'],
            'hocKy' => (int) $d['hoc_ky'],
            'namHoc' => $d['nam_hoc'],
            'trangThai' => 'Đang hiệu lực',
        ]);
        NhatKy::ghi($request->user()->getKey(), 'Phân công học phần', 'Phân công học phần', $phanCong->getKey(),
            "{$nguoi->hoTen} - {$d['vai_tro']} - {$hocPhan->maHocPhan} - học kỳ {$d['hoc_ky']} {$d['nam_hoc']}");

        return back()->with('thanh_cong', "Đã phân công {$nguoi->hoTen} ({$d['vai_tro']}) học phần {$hocPhan->maHocPhan}.");
    }

    public function ketThuc(Request $request, PhanCongHocPhan $phanCong): RedirectResponse
    {
        abort_unless($this->hocPhanTrongPham($request)->where('maHocPhan', $phanCong->maHocPhan)->exists(), 403);

        if ($phanCong->trangThai === 'Đang hiệu lực') {
            $phanCong->update(['trangThai' => 'Kết thúc']);
            NhatKy::ghi($request->user()->getKey(), 'Kết thúc phân công học phần', 'Phân công học phần', $phanCong->getKey());
        }

        return back()->with('thanh_cong', 'Đã kết thúc phân công.');
    }

    /** Trưởng bộ môn: học phần của bộ môn mình. Quản trị viên: mọi học phần. */
    private function hocPhanTrongPham(Request $request)
    {
        $nguoi = $request->user();
        $truyVan = HocPhan::query();
        if ($nguoi->vaiTro->tenVaiTro !== 'Quản trị viên') {
            $nguoi->maBoMon === null ? $truyVan->whereRaw('1 = 0') : $truyVan->where('maBoMon', $nguoi->maBoMon);
        }

        return $truyVan;
    }
}
