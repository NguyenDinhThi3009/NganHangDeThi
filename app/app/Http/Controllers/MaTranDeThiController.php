<?php

namespace App\Http\Controllers;

use App\Models\NganHangDeThi\CauHoi;
use App\Models\NganHangDeThi\ChiTietMaTran;
use App\Models\NganHangDeThi\Clo;
use App\Models\NganHangDeThi\HocPhan;
use App\Models\NganHangDeThi\MaTranDeThi;
use App\Support\MucDoBloom;
use App\Support\NhatKy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Cấu hình ma trận đề thi: CLO x mức Bloom x độ khó x số câu (UC-04, FR-04). */
class MaTranDeThiController extends Controller
{
    private const DO_KHO = ['Dễ', 'Trung bình', 'Khó'];

    private const SO_DONG_TOI_THIEU = 10;

    private const SO_CAU_TOI_DA_MOI_DONG = 50;

    public function index(Request $request): View
    {
        $truyVan = MaTranDeThi::with(['hocPhan', 'nguoiTao'])
            ->withSum('chiTietMaTrans as tong_so_cau', 'soLuongCau')
            ->orderByDesc('maMaTranDeThi');

        $locTrangThai = in_array($request->query('trang_thai'), ['Nháp', 'Đã chốt'], true) ? $request->query('trang_thai') : null;
        if ($locTrangThai) {
            $truyVan->where('trangThai', $locTrangThai);
        }
        $locHocPhan = $request->query('hoc_phan') ?: null;
        if ($locHocPhan) {
            $truyVan->where('maHocPhan', $locHocPhan);
        }

        return view('ma-tran.index', [
            'maTrans' => $truyVan->limit(200)->get(),
            'hocPhans' => HocPhan::orderBy('maHocPhan')->get(['maHocPhan', 'tenHocPhan']),
            'locTrangThai' => $locTrangThai,
            'locHocPhan' => $locHocPhan,
        ]);
    }

    public function create(Request $request): View
    {
        $hocPhan = $request->query('hoc_phan') ? HocPhan::find($request->query('hoc_phan')) : null;

        return view('ma-tran.form', $this->duLieuForm(new MaTranDeThi, $hocPhan, [], 85));
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $this->kiemTraThongTin($request, true);
        $cacDong = $this->docCacDong($request, $d['ma_hoc_phan']);

        $maTran = DB::transaction(function () use ($request, $d, $cacDong) {
            $maTran = MaTranDeThi::create([
                'tenMaTran' => trim($d['ten_ma_tran']),
                'maHocPhan' => $d['ma_hoc_phan'],
                'maNguoiTao' => $request->user()->getKey(),
                'ngayTao' => now(),
                'trangThai' => 'Nháp',
                'nguongTrungLap' => round($d['nguong'] / 100, 4),
            ]);
            $this->luuCacDong($maTran, $cacDong);

            return $maTran;
        });

        NhatKy::ghi($request->user()->getKey(), 'Tạo ma trận đề thi', 'Ma trận đề thi', $maTran->getKey());

        return redirect()->route('ma-tran.show', $maTran->getKey())->with('thanh_cong', 'Đã tạo ma trận ở trạng thái Nháp.');
    }

    public function show(Request $request, MaTranDeThi $maTran): View
    {
        $maTran->load(['hocPhan', 'nguoiTao', 'chiTietMaTrans.clo']);

        $thuTuBloom = array_values(MucDoBloom::BANG);
        $cacDong = $maTran->chiTietMaTrans->sortBy(fn ($c) => sprintf(
            '%03d-%d-%d',
            (int) substr((string) $c->clo?->kyHieu, 3),
            (int) array_search($c->mucDoBloom, $thuTuBloom, true),
            (int) array_search($c->doKho, self::DO_KHO, true)
        ))->values();

        // Số câu hỏi hiện có của từng dòng: đã duyệt (theo loại) và còn nháp
        $thongKe = [];
        $cauHois = CauHoi::where('maHocPhan', $maTran->maHocPhan)
            ->whereIn('maClo', $cacDong->pluck('maClo')->unique()->all())
            ->whereIn('trangThaiDuyet', ['Đã duyệt', 'Nháp'])
            ->selectRaw('maClo, mucDoBloom, doKho, trangThaiDuyet, loaiCauHoi, COUNT(*) as so')
            ->groupBy('maClo', 'mucDoBloom', 'doKho', 'trangThaiDuyet', 'loaiCauHoi')
            ->get();

        foreach ($cauHois as $h) {
            $khoa = (int) $h->maClo.'|'.$h->mucDoBloom.'|'.$h->doKho;
            $thongKe[$khoa] ??= ['duyet' => 0, 'tn' => 0, 'tl' => 0, 'nhap' => 0];
            if ($h->trangThaiDuyet === 'Đã duyệt') {
                $thongKe[$khoa]['duyet'] += (int) $h->so;
                $thongKe[$khoa][$h->loaiCauHoi === 'Trắc nghiệm' ? 'tn' : 'tl'] += (int) $h->so;
            } else {
                $thongKe[$khoa]['nhap'] += (int) $h->so;
            }
        }

        $hang = $cacDong->map(function ($c) use ($thongKe) {
            $tk = $thongKe[(int) $c->maClo.'|'.$c->mucDoBloom.'|'.$c->doKho] ?? ['duyet' => 0, 'tn' => 0, 'tl' => 0, 'nhap' => 0];

            return [
                'dong' => $c,
                'nhan_bloom' => MucDoBloom::sangNhan($c->mucDoBloom),
                'duyet' => $tk['duyet'],
                'tn' => $tk['tn'],
                'tl' => $tk['tl'],
                'nhap' => $tk['nhap'],
                'thieu' => max(0, (int) $c->soLuongCau - $tk['duyet']),
            ];
        });

        $tongCau = (int) $cacDong->sum('soLuongCau');
        $cloTrongMaTran = $cacDong->pluck('maClo')->map(fn ($v) => (int) $v)->unique()->all();

        return view('ma-tran.show', [
            'maTran' => $maTran,
            'hang' => $hang,
            'tongCau' => $tongCau,
            'tongThieu' => (int) $hang->sum('thieu'),
            'phanBo' => [
                'CLO' => $this->phanBo($cacDong, fn ($c) => (string) $c->clo?->kyHieu, $tongCau),
                'Mức Bloom' => $this->phanBo($cacDong, fn ($c) => MucDoBloom::sangNhan($c->mucDoBloom), $tongCau),
                'Độ khó' => $this->phanBo($cacDong, fn ($c) => $c->doKho, $tongCau),
            ],
            'cloChuaCo' => Clo::where('maHocPhan', $maTran->maHocPhan)->get()
                ->reject(fn ($clo) => in_array((int) $clo->getKey(), $cloTrongMaTran, true))
                ->pluck('kyHieu')->all(),
            'duocThaoTac' => $this->duocThaoTac($request, $maTran),
            'dangDuocDung' => $maTran->deThis()->exists(),
        ]);
    }

    public function edit(Request $request, MaTranDeThi $maTran): View
    {
        $this->chanNeuKhongDuocSua($request, $maTran);

        $hang = $maTran->chiTietMaTrans()->with('clo')->get()
            ->sortBy(fn ($c) => (int) substr((string) $c->clo?->kyHieu, 3))
            ->map(fn ($c) => [
                'clo' => (int) $c->maClo,
                'bloom' => MucDoBloom::sangNhan($c->mucDoBloom),
                'do_kho' => $c->doKho,
                'so_cau' => (int) $c->soLuongCau,
            ])->values()->all();

        return view('ma-tran.form', $this->duLieuForm($maTran, $maTran->hocPhan, $hang, (int) round(((float) $maTran->nguongTrungLap) * 100)));
    }

    public function update(Request $request, MaTranDeThi $maTran): RedirectResponse
    {
        $this->chanNeuKhongDuocSua($request, $maTran);

        $d = $this->kiemTraThongTin($request, false);
        $cacDong = $this->docCacDong($request, $maTran->maHocPhan);

        DB::transaction(function () use ($maTran, $d, $cacDong) {
            $maTran->update([
                'tenMaTran' => trim($d['ten_ma_tran']),
                'nguongTrungLap' => round($d['nguong'] / 100, 4),
            ]);
            ChiTietMaTran::where('maMaTranDeThi', $maTran->getKey())->delete();
            $this->luuCacDong($maTran, $cacDong);
        });

        NhatKy::ghi($request->user()->getKey(), 'Sửa ma trận đề thi', 'Ma trận đề thi', $maTran->getKey());

        return redirect()->route('ma-tran.show', $maTran->getKey())->with('thanh_cong', 'Đã lưu ma trận.');
    }

    public function chot(Request $request, MaTranDeThi $maTran): RedirectResponse
    {
        abort_unless($this->duocThaoTac($request, $maTran), 403);

        if ($maTran->trangThai !== 'Nháp') {
            return back()->with('loi', 'Ma trận đã được chốt.');
        }
        if (! $maTran->chiTietMaTrans()->exists()) {
            return back()->with('loi', 'Ma trận chưa có dòng nào, không thể chốt.');
        }

        $maTran->update(['trangThai' => 'Đã chốt']);
        NhatKy::ghi($request->user()->getKey(), 'Chốt ma trận đề thi', 'Ma trận đề thi', $maTran->getKey());

        return back()->with('thanh_cong', 'Đã chốt ma trận. Ma trận đã chốt dùng được để tạo đề thi.');
    }

    public function boChot(Request $request, MaTranDeThi $maTran): RedirectResponse
    {
        abort_unless($this->duocThaoTac($request, $maTran), 403);

        if ($maTran->deThis()->exists()) {
            return back()->with('loi', 'Ma trận đang được dùng cho đề thi nên không thể mở lại.');
        }

        $maTran->update(['trangThai' => 'Nháp']);
        NhatKy::ghi($request->user()->getKey(), 'Mở lại ma trận đề thi', 'Ma trận đề thi', $maTran->getKey());

        return back()->with('thanh_cong', 'Đã chuyển ma trận về Nháp, bạn có thể sửa.');
    }

    public function destroy(Request $request, MaTranDeThi $maTran): RedirectResponse
    {
        abort_unless($this->duocThaoTac($request, $maTran), 403);

        if ($maTran->deThis()->exists()) {
            return back()->with('loi', 'Ma trận đang được dùng cho đề thi nên không thể xóa.');
        }

        $ma = $maTran->getKey();
        DB::transaction(function () use ($maTran) {
            ChiTietMaTran::where('maMaTranDeThi', $maTran->getKey())->delete();
            $maTran->delete();
        });
        NhatKy::ghi($request->user()->getKey(), 'Xóa ma trận đề thi', 'Ma trận đề thi', $ma);

        return redirect()->route('ma-tran.index')->with('thanh_cong', 'Đã xóa ma trận.');
    }

    private function kiemTraThongTin(Request $request, bool $khiTao): array
    {
        $quyTac = [
            'ten_ma_tran' => ['required', 'string', 'max:255'],
            'nguong' => ['required', 'numeric', 'min:50', 'max:100'],
        ];
        if ($khiTao) {
            $quyTac['ma_hoc_phan'] = ['required', 'exists:hocphan,maHocPhan'];
        }

        return $request->validate($quyTac, [
            'ten_ma_tran.required' => 'Vui lòng nhập tên ma trận.',
            'ma_hoc_phan.required' => 'Vui lòng chọn học phần.',
            'nguong.required' => 'Vui lòng nhập ngưỡng cảnh báo trùng lặp.',
            'nguong.numeric' => 'Ngưỡng cảnh báo trùng lặp phải là số (phần trăm).',
            'nguong.min' => 'Ngưỡng cảnh báo trùng lặp phải từ 50% đến 100%.',
            'nguong.max' => 'Ngưỡng cảnh báo trùng lặp phải từ 50% đến 100%.',
        ]);
    }

    /**
     * Đọc các dòng của ma trận. Dòng không chọn CLO và không nhập số câu bị bỏ qua.
     *
     * @return list<array{clo: int, bloom: string, do_kho: string, so_cau: int}>
     */
    private function docCacDong(Request $request, string $maHocPhan): array
    {
        $cloHopLe = Clo::where('maHocPhan', $maHocPhan)->pluck('maClo')->map(fn ($v) => (int) $v)->all();

        $hang = [];
        $loi = [];
        $daCo = [];

        foreach ((array) $request->input('dong', []) as $i => $dong) {
            $clo = trim((string) ($dong['clo'] ?? ''));
            $soCau = trim((string) ($dong['so_cau'] ?? ''));
            $bloom = (string) ($dong['bloom'] ?? '');
            $doKho = (string) ($dong['do_kho'] ?? '');

            if ($clo === '' && $soCau === '') {
                continue;
            }

            $stt = (int) $i + 1;
            if ($clo === '' || ! in_array((int) $clo, $cloHopLe, true)) {
                $loi[] = "Dòng {$stt}: hãy chọn CLO thuộc học phần.";

                continue;
            }
            if (! in_array($bloom, MucDoBloom::nhanTiengViet(), true)) {
                $loi[] = "Dòng {$stt}: mức Bloom không hợp lệ.";

                continue;
            }
            if (! in_array($doKho, self::DO_KHO, true)) {
                $loi[] = "Dòng {$stt}: độ khó không hợp lệ.";

                continue;
            }
            if (! ctype_digit($soCau) || (int) $soCau < 1 || (int) $soCau > self::SO_CAU_TOI_DA_MOI_DONG) {
                $loi[] = "Dòng {$stt}: số câu phải là số nguyên từ 1 đến ".self::SO_CAU_TOI_DA_MOI_DONG.'.';

                continue;
            }

            $khoa = (int) $clo.'|'.$bloom.'|'.$doKho;
            if (isset($daCo[$khoa])) {
                $loi[] = "Dòng {$stt}: trùng với dòng {$daCo[$khoa]} (cùng CLO, mức Bloom và độ khó), hãy gộp số câu vào một dòng.";

                continue;
            }
            $daCo[$khoa] = $stt;

            $hang[] = ['clo' => (int) $clo, 'bloom' => $bloom, 'do_kho' => $doKho, 'so_cau' => (int) $soCau];
        }

        if (! $hang && ! $loi) {
            $loi[] = 'Ma trận cần có ít nhất một dòng.';
        }
        if ($loi) {
            throw ValidationException::withMessages(['dong' => $loi]);
        }

        return $hang;
    }

    private function luuCacDong(MaTranDeThi $maTran, array $cacDong): void
    {
        foreach ($cacDong as $d) {
            ChiTietMaTran::create([
                'maMaTranDeThi' => $maTran->getKey(),
                'maClo' => $d['clo'],
                'mucDoBloom' => MucDoBloom::sangCsdl($d['bloom']),
                'soLuongCau' => $d['so_cau'],
                'doKho' => $d['do_kho'],
            ]);
        }
    }

    private function duLieuForm(MaTranDeThi $maTran, ?HocPhan $hocPhan, array $hang, int $nguong): array
    {
        return [
            'maTran' => $maTran,
            'hocPhan' => $hocPhan,
            'hocPhans' => HocPhan::orderBy('maHocPhan')->get(['maHocPhan', 'tenHocPhan']),
            'clos' => $hocPhan ? Clo::where('maHocPhan', $hocPhan->maHocPhan)->orderBy('kyHieu')->get() : collect(),
            'hang' => $hang,
            'nguong' => $nguong,
            'soDong' => max(self::SO_DONG_TOI_THIEU, count($hang) + 3),
            'bloom' => MucDoBloom::nhanTiengViet(),
            'doKho' => self::DO_KHO,
        ];
    }

    /** @return list<array{ten: string, so: int, ty_le: float}> */
    private function phanBo($cacDong, callable $nhom, int $tong): array
    {
        return $cacDong->groupBy($nhom)->map(fn ($nhomDong, $ten) => [
            'ten' => (string) $ten,
            'so' => (int) $nhomDong->sum('soLuongCau'),
            'ty_le' => $tong > 0 ? round($nhomDong->sum('soLuongCau') * 100 / $tong, 1) : 0.0,
        ])->values()->all();
    }

    private function duocThaoTac(Request $request, MaTranDeThi $maTran): bool
    {
        return $request->user()->vaiTro->tenVaiTro === 'Quản trị viên'
            || (int) $maTran->maNguoiTao === (int) $request->user()->getKey();
    }

    private function chanNeuKhongDuocSua(Request $request, MaTranDeThi $maTran): void
    {
        abort_unless($this->duocThaoTac($request, $maTran), 403, 'Bạn không có quyền sửa ma trận này.');
        abort_if($maTran->trangThai !== 'Nháp', 403, 'Ma trận đã chốt. Hãy mở lại (bỏ chốt) trước khi sửa.');
    }
}
