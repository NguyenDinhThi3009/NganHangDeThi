<?php

namespace App\Http\Controllers;

use App\Models\NganHangDeThi\BoMon;
use App\Models\NganHangDeThi\ChuongTrinhDaoTao;
use App\Models\NganHangDeThi\Clo;
use App\Models\NganHangDeThi\CloPlo;
use App\Models\NganHangDeThi\HocPhan;
use App\Models\NganHangDeThi\HocPhanPlo;
use App\Models\NganHangDeThi\Plo;
use App\Models\NganHangDeThi\TaiLieu;
use App\Support\NhatKy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/** Quản trị viên nhập học phần, CLO và ma trận PLO từ đề cương chi tiết (UC-14). */
class HocPhanController extends Controller
{
    public function index(): View
    {
        return view('hoc-phan.index', [
            'hocPhans' => HocPhan::with(['boMon', 'chuongTrinhDaoTao'])->withCount('clos')->orderBy('maHocPhan')->get(),
        ]);
    }

    public function formNhap(): View
    {
        return view('hoc-phan.nhap');
    }

    /** Bước 1: nhận file đề cương, nhờ RAG service đọc, lưu kết quả tạm rồi chuyển sang trang xem trước. */
    public function docDeCuong(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:docx', 'max:20480'],
        ], [
            'file.required' => 'Vui lòng chọn file đề cương.',
            'file.extensions' => 'Chỉ hỗ trợ đề cương định dạng .docx.',
            'file.max' => 'File vượt quá 20 MB.',
        ]);

        $file = $request->file('file');
        $duongDanTam = $file->storeAs('tam-de-cuong', Str::uuid().'.docx');

        try {
            $phanHoi = Http::timeout(config('rag.timeout'))
                ->acceptJson()
                ->attach('file', Storage::get($duongDanTam), 'de-cuong.docx')
                ->post(rtrim(config('rag.url'), '/').'/v1/syllabus/parse');
        } catch (Throwable) {
            Storage::delete($duongDanTam);

            return back()->with('loi', 'Không kết nối được RAG service. Hãy kiểm tra RAG service đang chạy (uvicorn).');
        }

        if ($phanHoi->failed()) {
            Storage::delete($duongDanTam);
            $chiTiet = $phanHoi->json('detail') ?? 'mã lỗi '.$phanHoi->status();

            return back()->with('loi', 'Không đọc được đề cương: '.(is_string($chiTiet) ? $chiTiet : json_encode($chiTiet, JSON_UNESCAPED_UNICODE)));
        }

        $token = (string) Str::uuid();
        Cache::put(self::khoaCache($token), [
            'ma_nguoi_dung' => (int) $request->user()->getKey(),
            'duong_dan_tam' => $duongDanTam,
            'ten_file' => $file->getClientOriginalName(),
            'ket_qua' => $phanHoi->json(),
        ], now()->addHours(2));

        return redirect()->route('hoc-phan.xem-truoc', $token);
    }

    /** Bước 2: hiển thị kết quả đọc được để người dùng kiểm tra và chỉnh trước khi lưu. */
    public function xemTruoc(Request $request, string $token): View
    {
        $duLieu = $this->layDuLieuTam($request, $token);
        $ketQua = $duLieu['ket_qua'];

        $ctdts = ChuongTrinhDaoTao::orderBy('maChuongTrinhDaoTao')->get(['maChuongTrinhDaoTao', 'tenChuongTrinhDaoTao']);
        $ctdtMacDinh = $ctdts->first(fn ($c) => $ketQua['ma_nganh'] && str_contains($c->tenChuongTrinhDaoTao, $ketQua['ma_nganh']))
            ?? $ctdts->first();

        $hocPhanDaCo = ! empty($ketQua['ma_hoc_phan'])
            ? HocPhan::with('clos')->find($ketQua['ma_hoc_phan'])
            : null;

        [$tinChiLt, $tinChiTh] = $this->goiYTinChi($ketQua);

        return view('hoc-phan.xem-truoc', [
            'token' => $token,
            'tenFile' => $duLieu['ten_file'],
            'ketQua' => $ketQua,
            'ctdts' => $ctdts,
            'ctdtMacDinh' => $ctdtMacDinh?->maChuongTrinhDaoTao,
            'hocPhanDaCo' => $hocPhanDaCo,
            'tinChiLt' => $hocPhanDaCo?->soTinChiLT ?? $tinChiLt,
            'tinChiTh' => $hocPhanDaCo?->soTinChiTH ?? $tinChiTh,
            'hocKyMacDinh' => ($hocPhanDaCo && $hocPhanDaCo->hocKyDuKien > 0) ? $hocPhanDaCo->hocKyDuKien : null,
            'closDaCoKhongTrongDeCuong' => $hocPhanDaCo
                ? $hocPhanDaCo->clos->pluck('kyHieu')->diff(collect($ketQua['clos'])->pluck('ky_hieu'))->values()->all()
                : [],
        ]);
    }

    /** Bước 3: lưu học phần, CLO, ma trận PLO và file đề cương (UC-14). */
    public function xacNhan(Request $request, string $token): RedirectResponse
    {
        $duLieu = $this->layDuLieuTam($request, $token);
        $ketQua = $duLieu['ket_qua'];

        $d = Validator::make($request->all(), [
            'ma_hoc_phan' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'ten_hoc_phan' => ['required', 'string', 'max:255'],
            'so_tin_chi' => ['required', 'integer', 'min:0', 'max:30'],
            'so_tin_chi_lt' => ['required', 'integer', 'min:0', 'max:30'],
            'so_tin_chi_th' => ['required', 'integer', 'min:0', 'max:30'],
            'so_tin_chi_thuc_tap' => ['nullable', 'integer', 'min:0', 'max:30'],
            'loai_hoc_phan' => ['required', 'string', 'max:50'],
            'hoc_ky_du_kien' => ['required', 'integer', 'min:1', 'max:12'],
            'ma_chuong_trinh_dao_tao' => ['required', 'integer', 'exists:chuongtrinhdaotao,maChuongTrinhDaoTao'],
            'bo_mon' => ['required', 'string', 'max:255'],
            'clo_mo_ta' => ['required', 'array'],
            'clo_mo_ta.*' => ['required', 'string', 'max:2000'],
        ], [
            'ma_hoc_phan.regex' => 'Mã học phần chỉ gồm chữ và số.',
            'hoc_ky_du_kien.required' => 'Vui lòng nhập học kỳ dự kiến (đề cương không có thông tin này).',
            'clo_mo_ta.*.required' => 'Mô tả CLO không được để trống.',
        ])->validate();

        // Kiểm tra theo UC-14: CLO phải có PLO, và chỉ ánh xạ tới PLO thuộc ma trận của học phần
        $loi = [];
        $soTinChiThucTap = (int) ($d['so_tin_chi_thuc_tap'] ?? 0);
        if ((int) $d['so_tin_chi_lt'] + (int) $d['so_tin_chi_th'] + $soTinChiThucTap !== (int) $d['so_tin_chi']) {
            $loi[] = 'Tổng tín chỉ lý thuyết + thực hành + thực tập phải bằng tổng số tín chỉ của học phần.';
        }
        if (empty($ketQua['clos'])) {
            $loi[] = 'Đề cương không có CLO nào để lưu.';
        }

        $plos = Plo::where('maChuongTrinhDaoTao', $d['ma_chuong_trinh_dao_tao'])->get()->keyBy('kyHieu');
        $tongHop = collect($ketQua['tong_hop'])->keyBy('ky_hieu');

        foreach ($ketQua['clos'] as $clo) {
            if (empty($clo['plo'])) {
                $loi[] = "{$clo['ky_hieu']} chưa được ánh xạ tới PLO nào trong đề cương.";
            }
            foreach ($clo['plo'] as $p) {
                if (! $tongHop->has($p['ky_hieu'])) {
                    $loi[] = "{$clo['ky_hieu']} ánh xạ tới {$p['ky_hieu']} nhưng PLO này không có trong dòng Tổng hợp của học phần.";
                }
            }
        }
        foreach ($tongHop->keys() as $ky) {
            if (! $plos->has($ky)) {
                $loi[] = "Chương trình đào tạo đã chọn chưa có {$ky}. Hãy bổ sung PLO của chương trình (seeder hoặc quản lý PLO) rồi thử lại.";
            }
        }

        if ($loi) {
            return back()->withErrors(['de_cuong' => $loi])->withInput();
        }

        $ma = $d['ma_hoc_phan'];
        $duongDanMoi = 'tai-lieu/'.Str::uuid().'.docx';
        Storage::copy($duLieu['duong_dan_tam'], $duongDanMoi);

        try {
            $thongKe = DB::transaction(function () use ($d, $ma, $ketQua, $plos, $tongHop, $duongDanMoi, $duLieu, $request, $soTinChiThucTap) {
                $boMon = BoMon::firstOrCreate(['tenBoMon' => trim($d['bo_mon'])]);

                HocPhan::updateOrCreate(['maHocPhan' => $ma], [
                    'tenHocPhan' => trim($d['ten_hoc_phan']),
                    'soTinChi' => (int) $d['so_tin_chi'],
                    'soTinChiLT' => (int) $d['so_tin_chi_lt'],
                    'soTinChiTH' => (int) $d['so_tin_chi_th'],
                    'soTinChiThucTap' => $soTinChiThucTap > 0 ? $soTinChiThucTap : null,
                    'loaiHocPhan' => trim($d['loai_hoc_phan']),
                    'hocKyDuKien' => (int) $d['hoc_ky_du_kien'],
                    'maChuongTrinhDaoTao' => (int) $d['ma_chuong_trinh_dao_tao'],
                    'maBoMon' => $boMon->getKey(),
                ]);

                // Ma trận học phần - PLO (dòng "Tổng hợp")
                $maPloTongHop = [];
                foreach ($tongHop as $ky => $p) {
                    $plo = $plos[$ky];
                    HocPhanPlo::updateOrCreate(
                        ['maHocPhan' => $ma, 'maPlo' => $plo->getKey()],
                        ['mucDo' => $p['muc_do']]
                    );
                    $maPloTongHop[] = $plo->getKey();
                }
                HocPhanPlo::where('maHocPhan', $ma)->whereNotIn('maPlo', $maPloTongHop)->delete();

                // CLO và ánh xạ CLO - PLO (kèm chỉ số PI dạng "1.1" hoặc "2.1, 2.2")
                foreach ($ketQua['clos'] as $c) {
                    $clo = Clo::updateOrCreate(
                        ['maHocPhan' => $ma, 'kyHieu' => $c['ky_hieu']],
                        ['moTa' => trim($d['clo_mo_ta'][$c['ky_hieu']] ?? $c['mo_ta'])]
                    );

                    CloPlo::where('maClo', $clo->getKey())->delete();
                    foreach ($c['plo'] as $p) {
                        $soPlo = (int) substr($p['ky_hieu'], 3);
                        $chiSoPi = collect($c['pi'])
                            ->filter(fn ($pi) => (int) explode('.', $pi)[0] === $soPlo)
                            ->implode(', ');

                        CloPlo::create([
                            'maClo' => $clo->getKey(),
                            'maPlo' => $plos[$p['ky_hieu']]->getKey(),
                            'chiSoPI' => $chiSoPi !== '' ? $chiSoPi : null,
                        ]);
                    }
                }

                // Lưu file đề cương thành tài liệu của học phần (chỉ lưu file, không đưa vào RAG)
                TaiLieu::create([
                    'tenTaiLieu' => 'Đề cương chi tiết '.$ma,
                    'duongDanFile' => $duongDanMoi,
                    'dinhDang' => 'DOCX',
                    'loaiTaiLieu' => 'Đề cương chi tiết',
                    'trangThaiXuLy' => 'Sẵn sàng sử dụng',
                    'maHocPhan' => $ma,
                    'maNguoiTaiLen' => $request->user()->getKey(),
                    'ngayTaiLen' => now(),
                ]);

                return ['clo' => count($ketQua['clos']), 'plo' => $tongHop->count()];
            });
        } catch (Throwable $loiLuu) {
            Storage::delete($duongDanMoi);
            throw $loiLuu;
        }

        Storage::delete($duLieu['duong_dan_tam']);
        Cache::forget(self::khoaCache($token));

        NhatKy::ghi(
            $request->user()->getKey(),
            'Nhập học phần từ đề cương',
            'Học phần',
            null,
            "Mã học phần {$ma}: {$thongKe['clo']} CLO, {$thongKe['plo']} PLO"
        );

        return redirect()->route('hoc-phan.index')
            ->with('thanh_cong', "Đã lưu học phần {$ma} cùng {$thongKe['clo']} CLO và ma trận {$thongKe['plo']} PLO từ đề cương.");
    }

    /** Gợi ý chia tín chỉ lý thuyết / thực hành theo tỷ lệ số tiết (đề cương chỉ ghi số tiết, không ghi tín chỉ từng loại). */
    private function goiYTinChi(array $ketQua): array
    {
        $tinChi = (int) ($ketQua['so_tin_chi'] ?? 0);
        $lyThuyet = (int) ($ketQua['gio_ly_thuyet'] ?? 0) + (int) ($ketQua['gio_bai_tap'] ?? 0);
        $thucHanh = (int) ($ketQua['gio_thuc_hanh'] ?? 0);

        if ($thucHanh === 0) {
            return [$tinChi, 0];
        }
        if ($lyThuyet === 0) {
            return [0, $tinChi];
        }

        $tinChiLt = (int) round($tinChi * $lyThuyet / ($lyThuyet + $thucHanh));

        return [$tinChiLt, $tinChi - $tinChiLt];
    }

    private function layDuLieuTam(Request $request, string $token): array
    {
        $duLieu = Cache::get(self::khoaCache($token));

        abort_if(
            ! $duLieu || (int) $duLieu['ma_nguoi_dung'] !== (int) $request->user()->getKey(),
            404,
            'Phiên xem trước đã hết hạn hoặc không tồn tại, hãy tải lại đề cương.'
        );

        return $duLieu;
    }

    private static function khoaCache(string $token): string
    {
        return 'de_cuong:'.$token;
    }
}
