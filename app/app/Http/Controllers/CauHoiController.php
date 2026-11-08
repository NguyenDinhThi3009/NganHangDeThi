<?php

namespace App\Http\Controllers;

use App\Jobs\SinhCauHoiJob;
use App\Models\NganHangDeThi\CauHoi;
use App\Models\NganHangDeThi\Clo;
use App\Models\NganHangDeThi\HocPhan;
use App\Models\NganHangDeThi\KiemTraTrungLap;
use App\Models\NganHangDeThi\TaiLieu;
use App\Support\MucDoBloom;
use App\Support\QuyenCauHoi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CauHoiController extends Controller
{
    private const DO_KHO = ['Dễ', 'Trung bình', 'Khó'];

    private const LOAI_CAU_HOI = ['Trắc nghiệm', 'Tự luận'];

    // Loại tài liệu được đưa vào RAG (đề cương chi tiết thì không)
    private const LOAI_TAI_LIEU_RAG = ['Giáo trình/Slide', 'Bài tập', 'Tài liệu chung'];

    public function formSinh(Request $request): View
    {
        $maHocPhan = $request->query('hoc_phan');
        $hocPhan = $maHocPhan ? HocPhan::find($maHocPhan) : null;

        return view('cau-hoi.sinh', [
            'hocPhans' => HocPhan::orderBy('maHocPhan')->get(['maHocPhan', 'tenHocPhan']),
            'hocPhanChon' => $hocPhan,
            'clos' => $hocPhan ? Clo::where('maHocPhan', $hocPhan->maHocPhan)->orderBy('kyHieu')->get() : collect(),
            'taiLieus' => $hocPhan ? $this->taiLieuKhaDung($hocPhan->maHocPhan)->get() : collect(),
            'bloom' => MucDoBloom::nhanTiengViet(),
            'doKho' => self::DO_KHO,
            'loaiCauHoi' => self::LOAI_CAU_HOI,
            'trangThai' => Cache::get(SinhCauHoiJob::khoaTrangThai($request->user()->getKey())),
        ]);
    }

    public function batDauSinh(Request $request): RedirectResponse
    {
        $maHocPhan = $request->input('ma_hoc_phan');

        $du_lieu = $request->validate([
            'ma_hoc_phan' => ['required', 'exists:hocphan,maHocPhan'],
            // Ràng buộc ERD: CLO phải thuộc cùng học phần với câu hỏi
            'ma_clo' => ['required', 'integer', Rule::exists('clo', 'maClo')->where('maHocPhan', $maHocPhan)],
            'muc_do_bloom' => ['required', Rule::in(MucDoBloom::nhanTiengViet())],
            'do_kho' => ['required', Rule::in(self::DO_KHO)],
            'loai_cau_hoi' => ['required', Rule::in(self::LOAI_CAU_HOI)],
            'so_luong' => ['required', 'integer', 'min:1', 'max:10'],
            'diem_cau' => ['nullable', 'numeric', 'min:0.5', 'max:10', 'multiple_of:0.5'],
            'tai_lieu' => ['required', 'array', 'min:1'],
            'tai_lieu.*' => ['integer'],
        ], [
            'ma_hoc_phan.required' => 'Vui lòng chọn học phần.',
            'ma_clo.required' => 'Vui lòng chọn chuẩn đầu ra (CLO).',
            'ma_clo.exists' => 'CLO không thuộc học phần đã chọn.',
            'tai_lieu.required' => 'Vui lòng chọn ít nhất một tài liệu làm nguồn.',
            'tai_lieu.min' => 'Vui lòng chọn ít nhất một tài liệu làm nguồn.',
            'diem_cau.multiple_of' => 'Điểm của câu phải là bội số của 0,5.',
        ]);

        // Mọi tài liệu chọn phải khả dụng: sẵn sàng, cùng học phần hoặc là tài liệu chung
        $hopLe = $this->taiLieuKhaDung($du_lieu['ma_hoc_phan'])
            ->whereIn('maTaiLieu', $du_lieu['tai_lieu'])
            ->pluck('maTaiLieu')
            ->all();

        if (count($hopLe) !== count(array_unique($du_lieu['tai_lieu']))) {
            return back()->withErrors(['tai_lieu' => 'Có tài liệu không hợp lệ hoặc chưa sẵn sàng sử dụng.'])->withInput();
        }

        $nguoiDung = $request->user()->getKey();

        Cache::put(SinhCauHoiJob::khoaTrangThai($nguoiDung), [
            'trang_thai' => 'Đang sinh',
            'thong_bao' => 'Đang sinh '.$du_lieu['so_luong'].' câu hỏi, có thể mất vài phút.',
            'canh_bao' => [],
            'ma_hoc_phan' => $du_lieu['ma_hoc_phan'],
            'cap_nhat' => now()->format('H:i:s d/m/Y'),
        ], now()->addHours(6));

        SinhCauHoiJob::dispatch(
            $nguoiDung,
            $du_lieu['ma_hoc_phan'],
            (int) $du_lieu['ma_clo'],
            $du_lieu['muc_do_bloom'],
            $du_lieu['do_kho'],
            $du_lieu['loai_cau_hoi'],
            (int) $du_lieu['so_luong'],
            (float) ($du_lieu['diem_cau'] ?? 2),
            array_map('intval', $hopLe),
        );

        return redirect()->route('cau-hoi.index');
    }

    public function index(Request $request): View
    {
        $nguoiDung = $request->user();

        $truyVan = CauHoi::with(['hocPhan', 'clo', 'nguoiTao'])->orderByDesc('maCauHoi');

        // Giảng viên chỉ thấy câu hỏi do mình tạo
        if ($nguoiDung->vaiTro->tenVaiTro === 'Giảng viên') {
            $truyVan->where('maNguoiTao', $nguoiDung->getKey());
        }

        // Bộ lọc theo trạng thái duyệt và học phần
        $locTrangThai = $request->query('trang_thai');
        if (in_array($locTrangThai, ['Nháp', 'Đã duyệt', 'Từ chối'], true)) {
            $truyVan->where('trangThaiDuyet', $locTrangThai);
        } else {
            $locTrangThai = null;
        }

        $locHocPhan = $request->query('hoc_phan') ?: null;
        if ($locHocPhan) {
            $truyVan->where('maHocPhan', $locHocPhan);
        }

        $trangThai = Cache::get(SinhCauHoiJob::khoaTrangThai($nguoiDung->getKey()));

        return view('cau-hoi.index', [
            'cauHois' => $truyVan->limit(200)->get(),
            'trangThai' => $trangThai,
            'dangSinh' => ($trangThai['trang_thai'] ?? null) === 'Đang sinh',
            'hocPhans' => HocPhan::orderBy('maHocPhan')->get(['maHocPhan', 'tenHocPhan']),
            'locTrangThai' => $locTrangThai,
            'locHocPhan' => $locHocPhan,
            'canhBaoTrung' => $this->mapCanhBaoTrung(),
        ]);
    }

    public function show(Request $request, CauHoi $cauHoi): View
    {
        $nguoiDung = $request->user();

        // Giảng viên chỉ xem câu hỏi của mình
        abort_if(
            $nguoiDung->vaiTro->tenVaiTro === 'Giảng viên' && $cauHoi->maNguoiTao !== $nguoiDung->getKey(),
            403
        );

        $cauHoi->load([
            'hocPhan', 'clo', 'nguoiTao', 'nhatKyLLM',
            'doanTaiLieu.taiLieu',
            'dapAnTracNghiems' => fn ($q) => $q->orderBy('thuTu'),
            'dapAnTuLuanYs' => fn ($q) => $q->orderBy('thuTu'),
        ]);

        return view('cau-hoi.show', [
            'cauHoi' => $cauHoi,
            'trongDeThi' => QuyenCauHoi::trongDeThi($cauHoi),
            'biKhoaSua' => QuyenCauHoi::biKhoaSua($cauHoi),
            'trungLaps' => KiemTraTrungLap::with(['cauHoi', 'cauHoiTrung'])
                ->where(fn ($q) => $q->where('maCauHoi', $cauHoi->getKey())->orWhere('maCauHoiTrung', $cauHoi->getKey()))
                ->orderByDesc('doTuongDong')
                ->get(),
        ]);
    }

    /** @return array<int, float> mã câu hỏi => độ tương đồng cao nhất trong các cảnh báo trùng lặp chưa xử lý */
    private function mapCanhBaoTrung(): array
    {
        $ketQua = [];

        foreach (KiemTraTrungLap::where('trangThaiCanhBao', 'Cảnh báo trùng lặp')->get(['maCauHoi', 'maCauHoiTrung', 'doTuongDong']) as $kt) {
            foreach ([(int) $kt->maCauHoi, (int) $kt->maCauHoiTrung] as $ma) {
                $ketQua[$ma] = max($ketQua[$ma] ?? 0.0, (float) $kt->doTuongDong);
            }
        }

        return $ketQua;
    }

    private function taiLieuKhaDung(string $maHocPhan)
    {
        return TaiLieu::query()
            ->where('trangThaiXuLy', 'Sẵn sàng sử dụng')
            ->whereIn('loaiTaiLieu', self::LOAI_TAI_LIEU_RAG)
            ->where(fn ($q) => $q->where('maHocPhan', $maHocPhan)->orWhereNull('maHocPhan'))
            ->orderBy('maTaiLieu');
    }
}
