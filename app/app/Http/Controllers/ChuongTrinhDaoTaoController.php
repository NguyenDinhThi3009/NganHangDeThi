<?php

namespace App\Http\Controllers;

use App\Models\NganHangDeThi\ChuongTrinhDaoTao;
use App\Models\NganHangDeThi\CloPlo;
use App\Models\NganHangDeThi\HocPhanPlo;
use App\Models\NganHangDeThi\Plo;
use App\Support\DanhSachPlo;
use App\Support\NhatKy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Quản trị viên quản lý chương trình đào tạo và danh sách PLO (FR-15, UC-14). */
class ChuongTrinhDaoTaoController extends Controller
{
    private const SO_DONG_TOI_THIEU = 12;

    public function index(): View
    {
        return view('chuong-trinh.index', [
            'ctdts' => ChuongTrinhDaoTao::withCount(['plos', 'hocPhans'])->orderBy('maChuongTrinhDaoTao')->get(),
        ]);
    }

    public function create(): View
    {
        return view('chuong-trinh.form', [
            'ctdt' => new ChuongTrinhDaoTao,
            'plos' => collect(),
            'soDong' => self::SO_DONG_TOI_THIEU,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $du_lieu = $this->kiemTraThongTin($request);
        $cacPlo = DanhSachPlo::doc($request);

        $ctdt = DB::transaction(function () use ($du_lieu, $cacPlo) {
            $ctdt = ChuongTrinhDaoTao::create($this->mapCot($du_lieu));

            foreach ($cacPlo as $p) {
                Plo::create([
                    'maChuongTrinhDaoTao' => $ctdt->getKey(),
                    'kyHieu' => $p['ky_hieu'],
                    'moTa' => $p['mo_ta'],
                ]);
            }

            return $ctdt;
        });

        NhatKy::ghi($request->user()->getKey(), 'Thêm chương trình đào tạo', 'Chương trình đào tạo', $ctdt->getKey());

        return redirect()->route('chuong-trinh.index')
            ->with('thanh_cong', 'Đã tạo chương trình đào tạo "'.$ctdt->tenChuongTrinhDaoTao.'" với '.count($cacPlo).' PLO.');
    }

    public function edit(ChuongTrinhDaoTao $chuongTrinh): View
    {
        $plos = Plo::where('maChuongTrinhDaoTao', $chuongTrinh->getKey())->get()
            ->sortBy(fn ($p) => (int) substr($p->kyHieu, 3))
            ->values();

        return view('chuong-trinh.form', [
            'ctdt' => $chuongTrinh,
            'plos' => $plos,
            'soDong' => max(self::SO_DONG_TOI_THIEU, $plos->count() + 4),
        ]);
    }

    public function update(Request $request, ChuongTrinhDaoTao $chuongTrinh): RedirectResponse
    {
        $du_lieu = $this->kiemTraThongTin($request);
        $cacPlo = DanhSachPlo::doc($request);

        DB::transaction(function () use ($chuongTrinh, $du_lieu, $cacPlo) {
            $chuongTrinh->update($this->mapCot($du_lieu));

            $hienCo = Plo::where('maChuongTrinhDaoTao', $chuongTrinh->getKey())->get()
                ->keyBy(fn ($p) => (int) $p->getKey());
            $giuLai = [];

            foreach ($cacPlo as $p) {
                if ($p['ma'] !== null && $hienCo->has($p['ma'])) {
                    $hienCo[$p['ma']]->update(['kyHieu' => $p['ky_hieu'], 'moTa' => $p['mo_ta']]);
                    $giuLai[] = $p['ma'];
                } else {
                    $moi = Plo::create([
                        'maChuongTrinhDaoTao' => $chuongTrinh->getKey(),
                        'kyHieu' => $p['ky_hieu'],
                        'moTa' => $p['mo_ta'],
                    ]);
                    $giuLai[] = (int) $moi->getKey();
                }
            }

            // PLO bị bỏ khỏi danh sách: chỉ xóa được khi chưa học phần hay CLO nào dùng
            foreach ($hienCo as $ma => $plo) {
                if (in_array($ma, $giuLai, true)) {
                    continue;
                }
                if (HocPhanPlo::where('maPlo', $ma)->exists() || CloPlo::where('maPlo', $ma)->exists()) {
                    throw ValidationException::withMessages([
                        'plo' => ["{$plo->kyHieu} đang được học phần hoặc CLO sử dụng nên không xóa được."],
                    ]);
                }
                $plo->delete();
            }
        });

        NhatKy::ghi($request->user()->getKey(), 'Sửa chương trình đào tạo', 'Chương trình đào tạo', $chuongTrinh->getKey());

        return redirect()->route('chuong-trinh.index')->with('thanh_cong', 'Đã lưu chương trình đào tạo và danh sách PLO.');
    }

    public function destroy(Request $request, ChuongTrinhDaoTao $chuongTrinh): RedirectResponse
    {
        if ($chuongTrinh->hocPhans()->exists()) {
            return back()->with('loi', 'Chương trình đang có học phần nên không thể xóa.');
        }

        $ma = $chuongTrinh->getKey();
        DB::transaction(function () use ($chuongTrinh) {
            Plo::where('maChuongTrinhDaoTao', $chuongTrinh->getKey())->delete();
            $chuongTrinh->delete();
        });

        NhatKy::ghi($request->user()->getKey(), 'Xóa chương trình đào tạo', 'Chương trình đào tạo', $ma);

        return redirect()->route('chuong-trinh.index')->with('thanh_cong', 'Đã xóa chương trình đào tạo.');
    }

    private function kiemTraThongTin(Request $request): array
    {
        return $request->validate([
            'ten_chuong_trinh' => ['required', 'string', 'max:255'],
            'ten_tieng_anh' => ['nullable', 'string', 'max:255'],
            'trinh_do' => ['required', 'string', 'max:50'],
            'tong_so_tin_chi' => ['required', 'integer', 'min:1', 'max:400'],
            'khoa_quan_ly' => ['required', 'string', 'max:100'],
            'khoa_hoc' => ['required', 'string', 'max:20'],
        ], [
            'ten_chuong_trinh.required' => 'Vui lòng nhập tên chương trình đào tạo.',
            'trinh_do.required' => 'Vui lòng nhập trình độ đào tạo.',
            'tong_so_tin_chi.required' => 'Vui lòng nhập tổng số tín chỉ.',
            'tong_so_tin_chi.integer' => 'Tổng số tín chỉ phải là số nguyên.',
            'khoa_quan_ly.required' => 'Vui lòng nhập khoa quản lý.',
            'khoa_hoc.required' => 'Vui lòng nhập khóa học áp dụng.',
        ]);
    }

    private function mapCot(array $d): array
    {
        return [
            'tenChuongTrinhDaoTao' => trim($d['ten_chuong_trinh']),
            'tenTiengAnh' => ($d['ten_tieng_anh'] ?? null) !== null && trim($d['ten_tieng_anh']) !== '' ? trim($d['ten_tieng_anh']) : null,
            'trinhDo' => trim($d['trinh_do']),
            'tongSoTinChi' => (int) $d['tong_so_tin_chi'],
            'khoaQuanLy' => trim($d['khoa_quan_ly']),
            'khoaHoc' => trim($d['khoa_hoc']),
        ];
    }
}
