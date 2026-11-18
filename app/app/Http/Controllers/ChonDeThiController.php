<?php

namespace App\Http\Controllers;

use App\Models\NganHangDeThi\HocPhan;
use App\Models\NganHangDeThi\SuDungDeThi;
use App\Services\ChonDeThiService;
use App\Services\TaoDeThiService;
use App\Services\XuatDeThiService;
use App\Support\NhatKy;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Chọn ngẫu nhiên đề thi cho ca thi và tạo mã đề trắc nghiệm (UC-18, FR-19). */
class ChonDeThiController extends Controller
{
    public function __construct(
        private readonly ChonDeThiService $chonDe,
        private readonly XuatDeThiService $xuat,
    ) {}

    public function index(Request $request): View
    {
        $locHocPhan = $request->query('hoc_phan');

        $lichSu = SuDungDeThi::with(['deThi.maTranDeThi.hocPhan'])->withCount('maDeTracNghiems')
            ->when($locHocPhan, fn ($q) => $q->whereHas('deThi.maTranDeThi', fn ($m) => $m->where('maHocPhan', $locHocPhan)))
            ->orderByDesc('maSuDung')->limit(100)->get();

        $nam = (int) now()->format('Y');

        return view('chon-de.index', [
            'hocPhans' => HocPhan::orderBy('maHocPhan')->get(['maHocPhan', 'tenHocPhan']),
            'hinhThucs' => TaoDeThiService::HINH_THUC,
            'lichSu' => $lichSu,
            'locHocPhan' => $locHocPhan,
            'namHocMacDinh' => (int) now()->format('n') >= 8 ? $nam.'-'.($nam + 1) : ($nam - 1).'-'.$nam,
        ]);
    }

    public function chon(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'hoc_phan' => ['required', 'string', Rule::exists('hocphan', 'maHocPhan')],
            'hinh_thuc' => ['required', Rule::in(TaoDeThiService::HINH_THUC)],
            'hoc_ky' => ['required', Rule::in(['1', '2', '3'])],
            'nam_hoc' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'ngay_thi' => ['required', 'date', 'after_or_equal:today'],
            'so_ma_de' => ['nullable', Rule::in(['3', '4'])],
        ], [
            'hoc_phan.required' => 'Vui lòng chọn học phần.',
            'hinh_thuc.required' => 'Vui lòng chọn hình thức thi.',
            'hoc_ky.in' => 'Học kỳ phải là 1, 2 hoặc 3 (học kỳ hè).',
            'nam_hoc.regex' => 'Năm học có dạng 2026-2027.',
            'ngay_thi.required' => 'Vui lòng chọn ngày thi.',
            'ngay_thi.date' => 'Ngày thi không hợp lệ.',
            'ngay_thi.after_or_equal' => 'Ngày thi không được ở quá khứ.',
            'so_ma_de.in' => 'Số mã đề là 3 hoặc 4.',
        ]);

        [$dau, $cuoi] = array_map('intval', explode('-', $d['nam_hoc']));
        if ($cuoi !== $dau + 1) {
            return back()->withInput()->withErrors(['nam_hoc' => 'Năm học phải gồm hai năm liên tiếp, ví dụ 2026-2027.']);
        }

        try {
            $ketQua = $this->chonDe->chon($d['hoc_phan'], $d['hinh_thuc'], (int) $d['hoc_ky'], $d['nam_hoc'], Carbon::parse($d['ngay_thi']), (int) ($d['so_ma_de'] ?? 4));
        } catch (RuntimeException $e) {
            return back()->withInput()->with('loi', $e->getMessage().' Hãy xem cơ cấu ngân hàng đề và thông báo bộ môn bổ sung đề.')
                ->with('goi_y_ngan_hang', $d['hoc_phan']);
        }

        $suDung = $ketQua['su_dung'];
        NhatKy::ghi($request->user()->getKey(), 'Chọn đề thi cho ca thi', 'Sử dụng đề thi', $suDung->getKey(),
            "Đề #{$ketQua['de_thi']->getKey()} ({$d['hinh_thuc']}), {$d['hoc_phan']}, học kỳ {$d['hoc_ky']} {$d['nam_hoc']}, ngày thi {$d['ngay_thi']}");

        $phanHoi = redirect()->route('chon-de.show', $suDung->getKey())->with('thanh_cong', 'Đã chọn ngẫu nhiên đề thi và ghi nhận lần sử dụng.');

        return $ketQua['canh_bao'] ? $phanHoi->with('canh_bao', implode(' ', $ketQua['canh_bao'])) : $phanHoi;
    }

    public function show(SuDungDeThi $suDung): View
    {
        $suDung->load(['deThi.maTranDeThi.hocPhan', 'deThi.nguoiTao', 'deThi.nguoiDuyet', 'maDeTracNghiems']);
        $deThi = $suDung->deThi;

        $cacMa = [];
        foreach ($suDung->maDeTracNghiems->sortBy('kyHieuMa') as $ma) {
            $cacMa[] = ['ma' => $ma, 'du_lieu' => $this->xuat->duLieuMaDe($deThi, $ma)];
        }

        return view('chon-de.show', [
            'suDung' => $suDung,
            'deThi' => $deThi,
            'cacMa' => $cacMa,
            'soCau' => $deThi->deThiCauHois()->count(),
        ]);
    }

    /** Hủy lần chọn (ví dụ khi đề bị cảnh báo BR-13) để đề được chọn lại; ghi nhật ký để truy vết. */
    public function huy(Request $request, SuDungDeThi $suDung): RedirectResponse
    {
        $ly = trim((string) $request->input('ly_do', ''));
        $ma = $suDung->getKey();
        $moTa = "Đề #{$suDung->maDeThi}, học kỳ {$suDung->hocKy} {$suDung->namHoc}, ngày thi {$suDung->ngaySuDung?->format('d/m/Y')}".($ly !== '' ? ". Lý do: {$ly}" : '');

        \Illuminate\Support\Facades\DB::transaction(function () use ($suDung) {
            foreach ($suDung->maDeTracNghiems as $ma) {
                $ma->maDeCauHois()->delete();
                $ma->delete();
            }
            $suDung->delete();
        });
        NhatKy::ghi($request->user()->getKey(), 'Hủy lần chọn đề thi', 'Sử dụng đề thi', $ma, $moTa);

        return redirect()->route('chon-de.index')->with('thanh_cong', 'Đã hủy lần chọn, đề thi được trả lại danh sách có thể chọn.');
    }
}
