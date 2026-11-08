<?php

namespace App\Http\Controllers;

use App\Jobs\XuLyTaiLieuJob;
use App\Models\NganHangDeThi\HocPhan;
use App\Models\NganHangDeThi\TaiLieu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaiLieuController extends Controller
{
    public const LOAI_TAI_LIEU = ['Giáo trình/Slide', 'Bài tập', 'Đề cương chi tiết', 'Tài liệu chung'];

    // Chỉ các vai trò này được tải "Tài liệu chung" (BR-12)
    private const VAI_TRO_TAI_CHUNG = ['Quản trị viên', 'Phòng ĐT&ĐBCLGD'];

    // Vai trò xem được tài liệu của mọi người
    private const VAI_TRO_XEM_TAT_CA = ['Quản trị viên', 'Phòng ĐT&ĐBCLGD'];

    public function index(Request $request): View
    {
        $nguoiDung = $request->user();

        $truyVan = TaiLieu::with(['hocPhan', 'nguoiTaiLen'])
            ->withCount('doanTaiLieus')
            ->orderByDesc('ngayTaiLen');

        // Giảng viên chỉ thấy tài liệu do mình tải lên
        if (! in_array($nguoiDung->vaiTro->tenVaiTro, self::VAI_TRO_XEM_TAT_CA, true)) {
            $truyVan->where('maNguoiTaiLen', $nguoiDung->getKey());
        }

        $taiLieus = $truyVan->limit(200)->get();

        return view('tai-lieu.index', [
            'taiLieus' => $taiLieus,
            // Có tài liệu đang chờ RAG xử lý -> trang tự tải lại để cập nhật trạng thái
            'dangXuLy' => $taiLieus->contains(
                fn (TaiLieu $t) => $t->loaiTaiLieu !== 'Đề cương chi tiết'
                    && in_array($t->trangThaiXuLy, ['Chờ xử lý', 'Đang xử lý'], true)
            ),
            'laQuanTri' => $nguoiDung->vaiTro->tenVaiTro === 'Quản trị viên',
        ]);
    }

    public function create(Request $request): View
    {
        return view('tai-lieu.create', [
            'hocPhans' => HocPhan::orderBy('maHocPhan')->get(['maHocPhan', 'tenHocPhan']),
            'loaiTaiLieus' => self::LOAI_TAI_LIEU,
            'duocTaiChung' => $this->duocTaiTaiLieuChung($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $loai = $request->input('loai_tai_lieu');

        $du_lieu = $request->validate([
            'loai_tai_lieu' => ['required', Rule::in(self::LOAI_TAI_LIEU)],
            'ma_hoc_phan' => [Rule::requiredIf($loai !== 'Tài liệu chung'), 'nullable', 'exists:hocphan,maHocPhan'],
            'ten_tai_lieu' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'extensions:pdf,docx,pptx', 'max:51200'], // 50 MB, khớp giới hạn của RAG service
        ], [
            'loai_tai_lieu.required' => 'Vui lòng chọn loại tài liệu.',
            'loai_tai_lieu.in' => 'Loại tài liệu không hợp lệ.',
            'ma_hoc_phan.required' => 'Vui lòng chọn học phần (chỉ "Tài liệu chung" mới không cần học phần).',
            'ma_hoc_phan.exists' => 'Học phần không tồn tại.',
            'file.required' => 'Vui lòng chọn file.',
            'file.extensions' => 'Chỉ chấp nhận file PDF, DOCX hoặc PPTX.',
            'file.max' => 'File vượt quá 50 MB.',
            'file.uploaded' => 'Tải file lên thất bại. Có thể file lớn hơn giới hạn của PHP (upload_max_filesize).',
        ]);

        if ($du_lieu['loai_tai_lieu'] === 'Tài liệu chung' && ! $this->duocTaiTaiLieuChung($request)) {
            return back()
                ->withErrors(['loai_tai_lieu' => 'Chỉ Quản trị viên và Phòng ĐT&ĐBCLGD được tải tài liệu chung.'])
                ->withInput();
        }

        $file = $request->file('file');

        // Đề cương chi tiết chỉ lưu file (dùng để nhập CLO/PLO), không đưa vào RAG
        $chiLuuFile = $du_lieu['loai_tai_lieu'] === 'Đề cương chi tiết';

        $taiLieu = TaiLieu::create([
            'tenTaiLieu' => $du_lieu['ten_tai_lieu'] ?: $file->getClientOriginalName(),
            'duongDanFile' => $file->store('tai-lieu'), // lưu trong storage/app/private/tai-lieu
            'dinhDang' => strtoupper($file->getClientOriginalExtension()),
            'loaiTaiLieu' => $du_lieu['loai_tai_lieu'],
            'trangThaiXuLy' => $chiLuuFile ? 'Sẵn sàng sử dụng' : 'Chờ xử lý',
            // CHECK của ERD: maHocPhan NULL khi và chỉ khi loại = Tài liệu chung
            'maHocPhan' => $du_lieu['loai_tai_lieu'] === 'Tài liệu chung' ? null : $du_lieu['ma_hoc_phan'],
            'maNguoiTaiLen' => $request->user()->getKey(),
            'ngayTaiLen' => now(),
        ]);

        if ($chiLuuFile) {
            return redirect()->route('tai-lieu.index')->with('thanh_cong', 'Đã lưu đề cương chi tiết.');
        }

        XuLyTaiLieuJob::dispatch($taiLieu->getKey());

        return redirect()->route('tai-lieu.index')
            ->with('thanh_cong', 'Đã tải tài liệu lên. Hệ thống đang xử lý nền, trạng thái sẽ tự cập nhật.');
    }

    // Chỉ Quản trị viên (UC-19): xử lý lại tài liệu bị lỗi
    public function xuLyLai(TaiLieu $taiLieu): RedirectResponse
    {
        abort_if($taiLieu->loaiTaiLieu === 'Đề cương chi tiết', 422, 'Đề cương chi tiết không đi qua RAG.');

        $taiLieu->update(['trangThaiXuLy' => 'Chờ xử lý']);
        XuLyTaiLieuJob::dispatch($taiLieu->getKey());

        return redirect()->route('tai-lieu.index')->with('thanh_cong', 'Đã đưa tài liệu vào hàng đợi xử lý lại.');
    }

    private function duocTaiTaiLieuChung(Request $request): bool
    {
        return in_array($request->user()->vaiTro->tenVaiTro, self::VAI_TRO_TAI_CHUNG, true);
    }
}
