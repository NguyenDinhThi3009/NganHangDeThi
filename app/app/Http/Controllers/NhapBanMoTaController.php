<?php

namespace App\Http\Controllers;

use App\Jobs\DocBanMoTaJob;
use App\Models\NganHangDeThi\ChuongTrinhDaoTao;
use App\Models\NganHangDeThi\Plo;
use App\Support\DanhSachPlo;
use App\Support\NhatKy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Quản trị viên nhập chương trình đào tạo và PLO từ Bản mô tả CTĐT (PDF, kể cả bản scan). */
class NhapBanMoTaController extends Controller
{
    private const SO_DONG_TOI_THIEU = 12;

    public function form(): View
    {
        return view('chuong-trinh.nhap-ban-mo-ta');
    }

    /** Bước 1: nhận file và số trang, đưa việc đọc vào hàng đợi rồi chuyển sang trang theo dõi. */
    public function tai(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:pdf', 'max:51200'],
            'trang' => ['nullable', 'string', 'max:50', 'regex:/^[0-9,\-\s;]+$/'],
        ], [
            'file.required' => 'Vui lòng chọn file Bản mô tả (PDF).',
            'file.extensions' => 'Chỉ hỗ trợ file PDF.',
            'file.max' => 'File vượt quá 50 MB.',
            'trang.regex' => 'Số trang chỉ gồm chữ số, dấu phẩy và dấu gạch ngang (ví dụ 2,4).',
        ]);

        $file = $request->file('file');
        $token = (string) Str::uuid();

        Cache::put(self::khoaCache($token), [
            'ma_nguoi_dung' => (int) $request->user()->getKey(),
            'duong_dan_tam' => $file->storeAs('tam-ban-mo-ta', $token.'.pdf'),
            'ten_file' => $file->getClientOriginalName(),
            'trang' => trim((string) $request->input('trang', '')), // để trống = tự tìm
            'trang_thai' => 'Đang đọc',
            'thong_bao' => 'Đang đọc Bản mô tả bằng AI, có thể mất vài phút.',
            'ket_qua' => null,
        ], now()->addHours(3));

        DocBanMoTaJob::dispatch($token);

        return redirect()->route('ban-mo-ta.xem-truoc', $token);
    }

    /** Bước 2: trang theo dõi tiến trình, khi xong thì hiển thị kết quả để kiểm tra và chỉnh sửa. */
    public function xemTruoc(Request $request, string $token): View
    {
        $duLieu = $this->layDuLieuTam($request, $token);

        $ctdts = ChuongTrinhDaoTao::orderBy('maChuongTrinhDaoTao')->get();
        $ketQua = $duLieu['ket_qua'] ?? [];

        // Chương trình có sẵn trùng mã ngành thì mặc định là cập nhật chương trình đó
        $ctdtMacDinh = ! empty($ketQua['ma_nganh'])
            ? $ctdts->first(fn ($c) => str_contains($c->tenChuongTrinhDaoTao, $ketQua['ma_nganh']))
            : null;

        $tenMacDinh = $ctdtMacDinh?->tenChuongTrinhDaoTao
            ?? trim(($ketQua['ten_chuong_trinh'] ?? '').(! empty($ketQua['ma_nganh']) ? ' ('.$ketQua['ma_nganh'].')' : ''));

        // Khóa học: ưu tiên giá trị ghi trong Bản mô tả, rồi giá trị đã nhập ở chương trình có sẵn,
        // cuối cùng gợi ý từ năm ban hành ghi trên bìa (2025 -> K25)
        $khoaHocMacDinh = $ketQua['khoa_hoc'] ?? null;
        if (! $khoaHocMacDinh && $ctdtMacDinh && $ctdtMacDinh->khoaHoc !== 'Chưa cập nhật') {
            $khoaHocMacDinh = $ctdtMacDinh->khoaHoc;
        }
        $khoaHocTuNam = false;
        if (! $khoaHocMacDinh && ! empty($ketQua['nam_ban_hanh'])) {
            $khoaHocMacDinh = 'K'.substr((string) $ketQua['nam_ban_hanh'], -2);
            $khoaHocTuNam = true;
        }

        return view('chuong-trinh.ban-mo-ta', [
            'token' => $token,
            'duLieu' => $duLieu,
            'ketQua' => $ketQua,
            'ctdts' => $ctdts,
            'ctdtMacDinh' => $ctdtMacDinh?->maChuongTrinhDaoTao,
            'tenMacDinh' => $tenMacDinh,
            'khoaHocMacDinh' => $khoaHocMacDinh,
            'khoaHocTuNam' => $khoaHocTuNam,
            'soDong' => max(self::SO_DONG_TOI_THIEU, count($ketQua['plo'] ?? []) + 3),
        ]);
    }

    /** Bước 3: lưu chương trình đào tạo và các PLO. */
    public function xacNhan(Request $request, string $token): RedirectResponse
    {
        $duLieu = $this->layDuLieuTam($request, $token);
        abort_unless(($duLieu['trang_thai'] ?? null) === 'Hoàn tất', 422, 'Bản mô tả chưa được đọc xong.');

        $d = $request->validate([
            'ctdt_dich' => ['required', 'string'],
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
            'khoa_hoc.required' => 'Vui lòng nhập khóa học áp dụng (Bản mô tả không ghi nên cần nhập tay).',
        ]);

        $cacPlo = DanhSachPlo::doc($request);
        if (! $cacPlo) {
            return back()->withErrors(['plo' => 'Cần có ít nhất một PLO.'])->withInput();
        }

        $cot = [
            'tenChuongTrinhDaoTao' => trim($d['ten_chuong_trinh']),
            'tenTiengAnh' => trim((string) ($d['ten_tieng_anh'] ?? '')) !== '' ? trim($d['ten_tieng_anh']) : null,
            'trinhDo' => trim($d['trinh_do']),
            'tongSoTinChi' => (int) $d['tong_so_tin_chi'],
            'khoaQuanLy' => trim($d['khoa_quan_ly']),
            'khoaHoc' => trim($d['khoa_hoc']),
        ];

        $ctdt = DB::transaction(function () use ($d, $cot, $cacPlo) {
            if ($d['ctdt_dich'] === 'moi') {
                $ctdt = ChuongTrinhDaoTao::create($cot);
            } else {
                $ctdt = ChuongTrinhDaoTao::findOrFail((int) $d['ctdt_dich']);
                $ctdt->update($cot);
            }

            // Cập nhật theo ký hiệu: PLO đã có thì ghi đè nội dung, chưa có thì thêm mới (không xóa PLO nào)
            foreach ($cacPlo as $p) {
                Plo::updateOrCreate(
                    ['maChuongTrinhDaoTao' => $ctdt->getKey(), 'kyHieu' => $p['ky_hieu']],
                    ['moTa' => $p['mo_ta']]
                );
            }

            return $ctdt;
        });

        Storage::delete($duLieu['duong_dan_tam']);
        Cache::forget(self::khoaCache($token));

        NhatKy::ghi(
            $request->user()->getKey(),
            'Nhập chương trình đào tạo từ Bản mô tả',
            'Chương trình đào tạo',
            $ctdt->getKey(),
            count($cacPlo).' PLO'
        );

        return redirect()->route('chuong-trinh.index')
            ->with('thanh_cong', 'Đã lưu chương trình "'.$ctdt->tenChuongTrinhDaoTao.'" cùng '.count($cacPlo).' PLO từ Bản mô tả.');
    }

    public static function khoaCache(string $token): string
    {
        return 'ban_mo_ta:'.$token;
    }

    private function layDuLieuTam(Request $request, string $token): array
    {
        $duLieu = Cache::get(self::khoaCache($token));

        abort_if(
            ! $duLieu || (int) $duLieu['ma_nguoi_dung'] !== (int) $request->user()->getKey(),
            404,
            'Phiên đọc Bản mô tả đã hết hạn hoặc không tồn tại, hãy tải lại file.'
        );

        return $duLieu;
    }
}
