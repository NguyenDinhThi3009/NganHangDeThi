<?php

namespace App\Http\Controllers;

use App\Models\NganHangDeThi\DeThi;
use App\Models\NganHangDeThi\DeThiCauHoi;
use App\Models\NganHangDeThi\MaTranDeThi;
use App\Models\NganHangDeThi\NhatKyThaoTac;
use App\Models\NganHangDeThi\PhanCongHocPhan;
use App\Services\TaoDeThiService;
use App\Support\NhatKy;
use App\Support\QuyenDeThi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Tạo bộ đề thi từ ma trận đã chốt và câu hỏi đã duyệt (UC-10, FR-11). */
class DeThiController extends Controller
{
    private const TRANG_THAI = ['Nháp', 'Chờ phản biện', 'Chờ duyệt', 'Đã duyệt', 'Bị từ chối'];

    /** Bản nháp giữ trong bộ nhớ đệm 3 giờ, chưa ghi vào CSDL cho đến khi giảng viên xác nhận lưu. */
    private const THOI_GIAN_NHAP_PHUT = 180;

    public function __construct(private readonly TaoDeThiService $dich) {}

    public function index(Request $request): View
    {
        $truyVan = DeThi::with(['maTranDeThi.hocPhan', 'nguoiTao'])->withCount('deThiCauHois')->orderByDesc('maDeThi');

        if (! $this->laQuanTri($request)) {
            $truyVan->where('maNguoiTao', $request->user()->getKey());
        }

        $locTrangThai = in_array($request->query('trang_thai'), self::TRANG_THAI, true) ? $request->query('trang_thai') : null;
        if ($locTrangThai) {
            $truyVan->where('trangThai', $locTrangThai);
        }
        $locHinhThuc = in_array($request->query('hinh_thuc'), TaoDeThiService::HINH_THUC, true) ? $request->query('hinh_thuc') : null;
        if ($locHinhThuc) {
            $truyVan->where('hinhThucThi', $locHinhThuc);
        }

        return view('de-thi.index', [
            'deThis' => $truyVan->limit(200)->get(),
            'trangThais' => self::TRANG_THAI,
            'hinhThucs' => TaoDeThiService::HINH_THUC,
            'locTrangThai' => $locTrangThai,
            'locHinhThuc' => $locHinhThuc,
            'hocPhanXuat' => $this->hocPhanCoDeDaDuyet($request),
        ]);
    }

    /** Học phần có đề đã duyệt mà người dùng được xuất ngân hàng đề (UC-11, BR-12). */
    private function hocPhanCoDeDaDuyet(Request $request): \Illuminate\Support\Collection
    {
        $nguoi = $request->user();
        $truyVan = \App\Models\NganHangDeThi\HocPhan::whereIn('maHocPhan', function ($q) {
            $q->select('matrandethi.maHocPhan')->from('matrandethi')
                ->join('dethi', 'dethi.maMaTranDeThi', '=', 'matrandethi.maMaTranDeThi')
                ->where('dethi.trangThai', 'Đã duyệt');
        });
        if (! $this->laQuanTri($request)) {
            $truyVan->whereIn('maHocPhan', PhanCongHocPhan::where('maNguoiDung', $nguoi->getKey())
                ->where('trangThai', 'Đang hiệu lực')->select('maHocPhan'));
        }

        return $truyVan->orderBy('maHocPhan')->get(['maHocPhan', 'tenHocPhan']);
    }

    public function create(Request $request): View
    {
        $maTrans = MaTranDeThi::with('hocPhan')
            ->where('trangThai', 'Đã chốt')
            ->withSum('chiTietMaTrans as tong_so_cau', 'soLuongCau')
            ->orderByDesc('maMaTranDeThi')
            ->get();

        $maTranChon = (int) $request->query('ma_tran');

        return view('de-thi.create', [
            'maTrans' => $maTrans,
            'maTranChon' => $maTrans->contains('maMaTranDeThi', $maTranChon) ? $maTranChon : null,
            'hinhThucs' => TaoDeThiService::HINH_THUC,
        ]);
    }

    /** Bước 2 đến 4 của UC-10: chọn câu, chia điểm, kiểm tra cấu trúc rồi hiển thị bản nháp. */
    public function taoNhap(Request $request): RedirectResponse
    {
        $thongTin = $this->kiemTraThongTin($request);
        $maTran = MaTranDeThi::with('hocPhan')->findOrFail($thongTin['ma_tran']);
        $this->kiemTraDieuKien($request, $maTran);

        $token = (string) Str::uuid();
        $this->lapVaLuuNhap($request, $maTran, $thongTin, $token);

        return redirect()->route('de-thi.xem-truoc', $token);
    }

    public function xemTruoc(Request $request, string $token): View|RedirectResponse
    {
        $nhap = $this->layNhap($request, $token);
        $maTran = MaTranDeThi::with('hocPhan')->findOrFail($nhap['ma_tran']);

        $muc = $this->dich->mucTuBanNhap($nhap['muc'], $maTran->maHocPhan, $nhap['hinh_thuc']);
        if ($muc === null) {
            Cache::forget(self::khoaCache($token));

            return redirect()->route('de-thi.create', ['ma_tran' => $maTran->getKey()])
                ->with('loi', 'Một số câu trong bản nháp đã bị xóa, từ chối hoặc đổi học phần/loại câu. Hãy tạo lại đề.');
        }

        return view('de-thi.xem-truoc', [
            'token' => $token,
            'nhap' => $nhap,
            'maTran' => $maTran,
            'muc' => $this->dich->dungHienThi($muc),
            'tongDiem' => round(array_sum(array_map(fn ($m) => $m['diemSo'], $muc)), 2),
        ]);
    }

    public function taoLai(Request $request, string $token): RedirectResponse
    {
        $nhap = $this->layNhap($request, $token);
        $maTran = MaTranDeThi::with('hocPhan')->findOrFail($nhap['ma_tran']);
        $this->kiemTraDieuKien($request, $maTran);

        $tokenMoi = (string) Str::uuid();
        $this->lapVaLuuNhap($request, $maTran, $nhap['thong_tin'], $tokenMoi);
        Cache::forget(self::khoaCache($token));

        return redirect()->route('de-thi.xem-truoc', $tokenMoi)->with('thanh_cong', 'Đã chọn lại câu hỏi cho bản nháp.');
    }

    /** Bước 5 của UC-10: lưu đề ở trạng thái Nháp, hoặc lưu rồi gửi phản biện. */
    public function luu(Request $request, string $token): RedirectResponse
    {
        $nhap = $this->layNhap($request, $token);
        $guiNgay = $request->input('hanh_dong') === 'gui';

        $maTran = MaTranDeThi::with('hocPhan')->findOrFail($nhap['ma_tran']);
        $this->kiemTraDieuKien($request, $maTran);

        $muc = $this->dich->mucTuBanNhap($nhap['muc'], $maTran->maHocPhan, $nhap['hinh_thuc']);
        if ($muc === null) {
            Cache::forget(self::khoaCache($token));

            return redirect()->route('de-thi.create', ['ma_tran' => $maTran->getKey()])
                ->with('loi', 'Một số câu trong bản nháp không còn hợp lệ. Hãy tạo lại đề.');
        }

        // Kiểm tra lại cấu trúc ngay trước khi ghi (BR-01 đến BR-06)
        $ketQua = $this->dich->kiemTraCauTruc($nhap['hinh_thuc'], $this->thongSoThoiGian($nhap['thong_tin']), $muc, $maTran->hocPhan);
        if ($ketQua['loi']) {
            return redirect()->route('de-thi.xem-truoc', $token)->with('loi', 'Không thể lưu: '.implode(' ', $ketQua['loi']));
        }

        $deThi = DB::transaction(function () use ($request, $nhap, $maTran, $muc, $guiNgay) {
            $deThi = DeThi::create([
                'tenDeThi' => $nhap['ten'],
                'maMaTranDeThi' => $maTran->getKey(),
                'maNguoiTao' => $request->user()->getKey(),
                'ngayTao' => now(),
                'trangThai' => 'Nháp',
                'hinhThucThi' => $nhap['hinh_thuc'],
                'thoiGianLamBai' => $nhap['thoi_gian']['lam_bai'],
                'thoiGianChuanBi' => $nhap['thoi_gian']['chuan_bi'],
                'thoiGianTraLoi' => $nhap['thoi_gian']['tra_loi'],
            ]);

            foreach ($muc as $m) {
                DeThiCauHoi::create([
                    'maDeThi' => $deThi->getKey(),
                    'maCauHoi' => $m['cauHoi']->getKey(),
                    'thuTuCau' => $m['thuTu'],
                    'phan' => $m['phan'],
                    'diemSo' => $m['diemSo'],
                ]);
            }

            return $deThi;
        });

        Cache::forget(self::khoaCache($token));
        $nguoi = $request->user()->getKey();
        NhatKy::ghi($nguoi, 'Tạo đề thi', 'Đề thi', $deThi->getKey(), "{$nhap['hinh_thuc']}, ".count($muc).' câu, ma trận #'.$maTran->getKey());

        if ($guiNgay) {
            $deThi->update(['trangThai' => 'Chờ phản biện']);
            NhatKy::ghi($nguoi, 'Gửi phản biện đề thi', 'Đề thi', $deThi->getKey());

            return redirect()->route('de-thi.show', $deThi->getKey())->with('thanh_cong', 'Đã lưu đề thi và gửi phản biện.');
        }

        return redirect()->route('de-thi.show', $deThi->getKey())->with('thanh_cong', 'Đã lưu đề thi ở trạng thái Nháp.');
    }

    public function huyNhap(Request $request, string $token): RedirectResponse
    {
        $this->layNhap($request, $token);
        Cache::forget(self::khoaCache($token));

        return redirect()->route('de-thi.create')->with('thanh_cong', 'Đã hủy bản nháp.');
    }

    public function show(Request $request, DeThi $deThi): View
    {
        $this->chanNeuKhongXem($request, $deThi);
        $deThi->load(['maTranDeThi.hocPhan', 'nguoiTao', 'nguoiPhanBien', 'nguoiDuyet']);

        $muc = $this->dich->mucTuDeThi($deThi);
        $ketQua = $this->dich->kiemTraCauTruc(
            $deThi->hinhThucThi,
            ['lam_bai' => $deThi->thoiGianLamBai, 'chuan_bi' => $deThi->thoiGianChuanBi, 'tra_loi' => $deThi->thoiGianTraLoi],
            $muc,
            $deThi->maTranDeThi?->hocPhan
        );

        return view('de-thi.show', [
            'deThi' => $deThi,
            'muc' => $this->dich->dungHienThi($muc),
            'tongDiem' => round(array_sum(array_map(fn ($m) => $m['diemSo'], $muc)), 2),
            'loiCauTruc' => $ketQua['loi'],
            'canhBao' => $ketQua['canh_bao'],
            'duocThaoTac' => $this->duocThaoTac($request, $deThi),
            'nhanXet' => $this->nhatKyGanNhat($deThi, 'Phản biện đề thi'),
            'lyDoTuChoi' => $deThi->trangThai === 'Bị từ chối' ? $this->nhatKyGanNhat($deThi, 'Từ chối đề thi') : null,
        ]);
    }

    /** Gửi phản biện: Nháp thành Chờ phản biện (BR-11), chỉ khi cấu trúc đề thỏa BR-01 đến BR-06. */
    public function guiPhanBien(Request $request, DeThi $deThi): RedirectResponse
    {
        abort_unless($this->duocThaoTac($request, $deThi), 403);

        if ($deThi->trangThai !== 'Nháp') {
            return back()->with('loi', 'Chỉ đề ở trạng thái Nháp mới gửi phản biện được.');
        }

        $ketQua = $this->dich->kiemTraCauTruc(
            $deThi->hinhThucThi,
            ['lam_bai' => $deThi->thoiGianLamBai, 'chuan_bi' => $deThi->thoiGianChuanBi, 'tra_loi' => $deThi->thoiGianTraLoi],
            $this->dich->mucTuDeThi($deThi),
            $deThi->maTranDeThi?->hocPhan
        );
        if ($ketQua['loi']) {
            return back()->with('loi', 'Không thể gửi phản biện: '.implode(' ', $ketQua['loi']));
        }

        $deThi->update(['trangThai' => 'Chờ phản biện']);
        NhatKy::ghi($request->user()->getKey(), 'Gửi phản biện đề thi', 'Đề thi', $deThi->getKey());

        return back()->with('thanh_cong', 'Đã gửi đề thi đi phản biện. Các câu hỏi trong đề đã bị khóa sửa (BR-15).');
    }

    public function destroy(Request $request, DeThi $deThi): RedirectResponse
    {
        abort_unless($this->duocThaoTac($request, $deThi), 403);

        if ($deThi->trangThai !== 'Nháp') {
            return back()->with('loi', 'Chỉ xóa được đề ở trạng thái Nháp.');
        }

        $ma = $deThi->getKey();
        DB::transaction(function () use ($deThi) {
            DeThiCauHoi::where('maDeThi', $deThi->getKey())->delete();
            $deThi->delete();
        });
        NhatKy::ghi($request->user()->getKey(), 'Xóa đề thi', 'Đề thi', $ma);

        return redirect()->route('de-thi.index')->with('thanh_cong', 'Đã xóa đề thi.');
    }

    /** @return array{ma_tran: int, hinh_thuc: string, ten: string, lam_bai: ?int, chuan_bi: ?int, tra_loi: ?int, so_bat_buoc: int, diem_bat_buoc: float} */
    private function kiemTraThongTin(Request $request): array
    {
        $d = $request->validate([
            'ma_tran' => ['required', 'integer', Rule::exists('matrandethi', 'maMaTranDeThi')],
            'hinh_thuc' => ['required', Rule::in(TaoDeThiService::HINH_THUC)],
            'ten' => ['required', 'string', 'max:255'],
            'lam_bai' => ['nullable', 'integer', 'min:1', 'max:300'],
            'chuan_bi' => ['nullable', 'integer', 'min:1', 'max:300'],
            'tra_loi' => ['nullable', 'integer', 'min:1', 'max:300'],
            'so_bat_buoc' => ['nullable', 'integer', 'min:1', 'max:2'],
            'diem_bat_buoc' => ['nullable', 'numeric', 'min:1', 'max:9'],
        ], [
            'ma_tran.required' => 'Vui lòng chọn ma trận đề.',
            'ma_tran.exists' => 'Ma trận không tồn tại.',
            'hinh_thuc.required' => 'Vui lòng chọn hình thức thi.',
            'ten.required' => 'Vui lòng nhập tên đề thi.',
            'lam_bai.integer' => 'Thời gian làm bài phải là số phút nguyên.',
            'chuan_bi.integer' => 'Thời gian chuẩn bị phải là số phút nguyên.',
            'tra_loi.integer' => 'Thời gian trình bày phải là số phút nguyên.',
            'so_bat_buoc.min' => 'Đề vấn đáp có 1 đến 2 câu hỏi bắt buộc.',
            'so_bat_buoc.max' => 'Đề vấn đáp có 1 đến 2 câu hỏi bắt buộc.',
            'diem_bat_buoc.min' => 'Điểm phần bắt buộc phải từ 1 đến 9.',
            'diem_bat_buoc.max' => 'Điểm phần bắt buộc phải từ 1 đến 9.',
        ]);

        $laVanDap = $d['hinh_thuc'] === 'Vấn đáp';
        $thieu = [];
        if ($laVanDap) {
            if (empty($d['chuan_bi'])) {
                $thieu['chuan_bi'] = 'Đề vấn đáp cần thời gian chuẩn bị (tối đa 15 phút).';
            }
            if (empty($d['tra_loi'])) {
                $thieu['tra_loi'] = 'Đề vấn đáp cần thời gian trình bày (tối đa 7 phút).';
            }
        } elseif (empty($d['lam_bai'])) {
            $thieu['lam_bai'] = 'Vui lòng nhập thời gian làm bài (phút).';
        }
        if ($thieu) {
            throw ValidationException::withMessages($thieu);
        }

        return [
            'ma_tran' => (int) $d['ma_tran'],
            'hinh_thuc' => $d['hinh_thuc'],
            'ten' => trim($d['ten']),
            'lam_bai' => $laVanDap ? null : (int) $d['lam_bai'],
            'chuan_bi' => $laVanDap ? (int) $d['chuan_bi'] : null,
            'tra_loi' => $laVanDap ? (int) $d['tra_loi'] : null,
            'so_bat_buoc' => $laVanDap ? (int) ($d['so_bat_buoc'] ?? 2) : 0,
            'diem_bat_buoc' => $laVanDap ? (float) ($d['diem_bat_buoc'] ?? 7) : 0.0,
        ];
    }

    /** Điều kiện của UC-10: ma trận đã chốt; giảng viên được phân công biên soạn học phần (UC-20). */
    private function kiemTraDieuKien(Request $request, MaTranDeThi $maTran): void
    {
        if ($maTran->trangThai !== 'Đã chốt') {
            throw ValidationException::withMessages(['ma_tran' => 'Chỉ dùng được ma trận ở trạng thái Đã chốt.']);
        }
        if ($this->laQuanTri($request)) {
            return;
        }

        // UC-20: giảng viên phải được Trưởng bộ môn phân công biên soạn học phần này
        if (! QuyenDeThi::duocPhanCong($request->user(), $maTran->maHocPhan, 'Biên soạn')) {
            throw ValidationException::withMessages(['ma_tran' => 'Bạn chưa được phân công biên soạn đề thi học phần này. Hãy nhờ Trưởng bộ môn phân công (menu Phân công học phần).']);
        }
    }

    /** Chọn câu, kiểm tra cấu trúc và lưu bản nháp vào bộ nhớ đệm; lỗi thì quay lại form kèm lý do. */
    private function lapVaLuuNhap(Request $request, MaTranDeThi $maTran, array $thongTin, string $token): void
    {
        $thongSo = $this->thongSoThoiGian($thongTin) + [
            'so_bat_buoc' => $thongTin['so_bat_buoc'],
            'diem_bat_buoc' => $thongTin['diem_bat_buoc'],
        ];

        $ketQua = $this->dich->lapDe($maTran, $thongTin['hinh_thuc'], $thongSo);

        if ($ketQua['thieu']) {
            throw ValidationException::withMessages(['ma_tran' => 'Ngân hàng chưa đủ câu hỏi đã duyệt cho ma trận này.'])
                ->redirectTo(route('de-thi.thieu', ['ma_tran' => $maTran->getKey(), 'hinh_thuc' => $thongTin['hinh_thuc']]));
        }
        if ($ketQua['loi']) {
            throw ValidationException::withMessages(['cau_truc' => $ketQua['loi']]);
        }

        $thoiGian = ['lam_bai' => $thongTin['lam_bai'], 'chuan_bi' => $thongTin['chuan_bi'], 'tra_loi' => $thongTin['tra_loi']];
        if ($thongTin['hinh_thuc'] === 'Vấn đáp') {
            $thoiGian['lam_bai'] = (int) $thongTin['chuan_bi'] + (int) $thongTin['tra_loi'];
        }

        Cache::put(self::khoaCache($token), [
            'nguoi' => $request->user()->getKey(),
            'ma_tran' => $maTran->getKey(),
            'hinh_thuc' => $thongTin['hinh_thuc'],
            'ten' => $thongTin['ten'],
            'thong_tin' => $thongTin,
            'thoi_gian' => $thoiGian,
            'canh_bao' => $ketQua['canh_bao'],
            'muc' => array_map(fn ($m) => [
                'ma' => (int) $m['cauHoi']->getKey(),
                'thu_tu' => $m['thuTu'],
                'phan' => $m['phan'],
                'diem' => $m['diemSo'],
            ], $ketQua['muc']),
        ], now()->addMinutes(self::THOI_GIAN_NHAP_PHUT));
    }

    /** @return array{lam_bai: ?int, chuan_bi: ?int, tra_loi: ?int} */
    private function thongSoThoiGian(array $thongTin): array
    {
        return [
            'lam_bai' => $thongTin['lam_bai'],
            'chuan_bi' => $thongTin['chuan_bi'],
            'tra_loi' => $thongTin['tra_loi'],
        ];
    }

    private function layNhap(Request $request, string $token): array
    {
        $nhap = Cache::get(self::khoaCache($token));
        abort_if(! is_array($nhap), 404, 'Bản nháp đã hết hạn hoặc không tồn tại. Hãy tạo lại đề thi.');
        abort_unless((int) $nhap['nguoi'] === (int) $request->user()->getKey(), 403);

        return $nhap;
    }

    private static function khoaCache(string $token): string
    {
        return 'de_thi_nhap:'.$token;
    }

    private function laQuanTri(Request $request): bool
    {
        return $request->user()->vaiTro->tenVaiTro === 'Quản trị viên';
    }

    private function duocThaoTac(Request $request, DeThi $deThi): bool
    {
        return $this->laQuanTri($request) || (int) $deThi->maNguoiTao === (int) $request->user()->getKey();
    }

    /** Các bước sau (phản biện, phê duyệt) sẽ mở thêm quyền xem cho người được phân công. */
    private function chanNeuKhongXem(Request $request, DeThi $deThi): void
    {
        abort_unless($this->duocThaoTac($request, $deThi), 403, 'Bạn không có quyền xem đề thi này.');
    }

    private function nhatKyGanNhat(DeThi $deThi, string $hanhDong): ?string
    {
        return NhatKyThaoTac::where('doiTuongTacDong', 'Đề thi')->where('maDoiTuong', $deThi->getKey())
            ->where('hanhDong', $hanhDong)->orderByDesc('maNhatKyThaoTac')->value('chiTiet');
    }

    /** Trang liệt kê các mục còn thiếu khi ngân hàng không đủ câu cho ma trận (luồng thay thế của UC-10). */
    public function thieu(Request $request): View|RedirectResponse
    {
        $maTran = MaTranDeThi::with(['hocPhan', 'chiTietMaTrans.clo'])->findOrFail((int) $request->query('ma_tran'));
        $hinhThuc = in_array($request->query('hinh_thuc'), TaoDeThiService::HINH_THUC, true) ? $request->query('hinh_thuc') : 'Trắc nghiệm';

        $ketQua = $this->dich->lapDe($maTran, $hinhThuc, [
            'lam_bai' => 45, 'chuan_bi' => 10, 'tra_loi' => 5, 'so_bat_buoc' => 2, 'diem_bat_buoc' => 7,
        ]);

        if (! $ketQua['thieu']) {
            return redirect()->route('de-thi.create', ['ma_tran' => $maTran->getKey()])
                ->with('thanh_cong', 'Ngân hàng hiện đã đủ câu đã duyệt cho ma trận này, bạn có thể tạo đề.');
        }

        return view('de-thi.thieu', [
            'maTran' => $maTran,
            'hinhThuc' => $hinhThuc,
            'thieu' => $ketQua['thieu'],
            'tongThieu' => array_sum(array_column($ketQua['thieu'], 'thieu')),
        ]);
    }
}
