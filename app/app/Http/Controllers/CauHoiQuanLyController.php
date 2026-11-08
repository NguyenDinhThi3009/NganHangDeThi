<?php

namespace App\Http\Controllers;

use App\Models\NganHangDeThi\CauHoi;
use App\Models\NganHangDeThi\Clo;
use App\Models\NganHangDeThi\DapAnTracNghiem;
use App\Models\NganHangDeThi\DapAnTuLuanY;
use App\Models\NganHangDeThi\KiemTraTrungLap;
use App\Services\KiemTraTrungLapService;
use App\Support\MucDoBloom;
use App\Support\NhatKy;
use App\Support\QuyenCauHoi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Sửa, duyệt, từ chối và xóa câu hỏi (UC-08, UC-09). */
class CauHoiQuanLyController extends Controller
{
    private const DO_KHO = ['Dễ', 'Trung bình', 'Khó'];

    private const SO_DONG_PHUONG_AN = 6;

    private const SO_DONG_Y = 12;

    public function sua(Request $request, CauHoi $cauHoi): View
    {
        $this->chanNeuKhongCoQuyen($request, $cauHoi);
        $this->chanNeuBiKhoa($cauHoi);

        $cauHoi->load([
            'dapAnTracNghiems' => fn ($q) => $q->orderBy('thuTu'),
            'dapAnTuLuanYs' => fn ($q) => $q->orderBy('thuTu'),
        ]);

        $phuongAn = $cauHoi->dapAnTracNghiems->values();
        $viTriDung = $phuongAn->search(fn ($p) => (bool) $p->laDapAnDung);

        return view('cau-hoi.sua', [
            'cauHoi' => $cauHoi,
            'phuongAn' => $phuongAn,
            'viTriDung' => $viTriDung === false ? null : $viTriDung,
            'cacY' => $cauHoi->dapAnTuLuanYs->values(),
            'clos' => Clo::where('maHocPhan', $cauHoi->maHocPhan)->orderBy('kyHieu')->get(),
            'bloom' => MucDoBloom::nhanTiengViet(),
            'nhanBloomHienTai' => MucDoBloom::sangNhan($cauHoi->mucDoBloom),
            'doKho' => self::DO_KHO,
            'soDongPhuongAn' => self::SO_DONG_PHUONG_AN,
            'soDongY' => self::SO_DONG_Y,
        ]);
    }

    public function capNhat(Request $request, CauHoi $cauHoi): RedirectResponse
    {
        $this->chanNeuKhongCoQuyen($request, $cauHoi);
        $this->chanNeuBiKhoa($cauHoi);

        $laTracNghiem = $cauHoi->loaiCauHoi === 'Trắc nghiệm';

        $validator = Validator::make($request->all(), [
            'noi_dung' => ['required', 'string', 'max:5000'],
            'muc_do_bloom' => ['required', Rule::in(MucDoBloom::nhanTiengViet())],
            'do_kho' => ['required', Rule::in(self::DO_KHO)],
            // BR-16: CLO phải thuộc cùng học phần với câu hỏi
            'ma_clo' => ['required', 'integer', Rule::exists('clo', 'maClo')->where('maHocPhan', $cauHoi->maHocPhan)],
        ], [
            'noi_dung.required' => 'Vui lòng nhập nội dung câu hỏi.',
            'ma_clo.exists' => 'CLO không thuộc học phần của câu hỏi.',
        ]);

        // Gom các dòng đáp án đã nhập (bỏ dòng trống)
        $phuongAn = [];
        $cacY = [];
        $dapAnDung = $request->input('dap_an_dung');

        if ($laTracNghiem) {
            foreach ((array) $request->input('phuong_an', []) as $i => $noiDung) {
                $noiDung = trim((string) $noiDung);
                if ($noiDung !== '') {
                    $phuongAn[$i] = $noiDung;
                }
            }
        } else {
            foreach ((array) $request->input('y_noi_dung', []) as $i => $noiDung) {
                $noiDung = trim((string) $noiDung);
                if ($noiDung === '') {
                    continue;
                }
                $diem = str_replace(',', '.', trim((string) ($request->input('y_diem', [])[$i] ?? '')));
                $cacY[] = ['noi_dung' => $noiDung, 'diem' => $diem];
            }
        }

        $validator->after(function ($v) use ($laTracNghiem, $phuongAn, $cacY, $dapAnDung) {
            if ($laTracNghiem) {
                if (count($phuongAn) < 3) {
                    $v->errors()->add('phuong_an', 'Câu trắc nghiệm cần ít nhất 3 phương án.');
                }
                if ($dapAnDung === null || ! array_key_exists((int) $dapAnDung, $phuongAn)) {
                    $v->errors()->add('dap_an_dung', 'Hãy chọn đúng 1 đáp án đúng trong số các phương án đã nhập.');
                }
            } else {
                if (! $cacY) {
                    $v->errors()->add('y_noi_dung', 'Câu tự luận cần ít nhất một ý đáp án.');
                }
                foreach ($cacY as $k => $y) {
                    if (! is_numeric($y['diem']) || (float) $y['diem'] <= 0 || (float) $y['diem'] > 0.5) {
                        $v->errors()->add('y_diem', 'Ý '.($k + 1).': điểm phải lớn hơn 0 và tối đa 0,5 (BR-02).');
                    }
                }
            }
        });

        $du_lieu = $validator->validate();

        DB::transaction(function () use ($cauHoi, $du_lieu, $laTracNghiem, $phuongAn, $cacY, $dapAnDung) {
            $cauHoi->update([
                'noiDungCauHoi' => trim($du_lieu['noi_dung']),
                'mucDoBloom' => MucDoBloom::sangCsdl($du_lieu['muc_do_bloom']),
                'doKho' => $du_lieu['do_kho'],
                'maClo' => (int) $du_lieu['ma_clo'],
            ]);

            DapAnTracNghiem::where('maCauHoi', $cauHoi->getKey())->delete();
            DapAnTuLuanY::where('maCauHoi', $cauHoi->getKey())->delete();

            $thuTu = 0;
            if ($laTracNghiem) {
                foreach ($phuongAn as $i => $noiDung) {
                    DapAnTracNghiem::create([
                        'maCauHoi' => $cauHoi->getKey(),
                        'noiDungDapAn' => $noiDung,
                        'laDapAnDung' => (int) $i === (int) $dapAnDung,
                        'thuTu' => ++$thuTu,
                    ]);
                }
            } else {
                foreach ($cacY as $y) {
                    DapAnTuLuanY::create([
                        'maCauHoi' => $cauHoi->getKey(),
                        'noiDungY' => $y['noi_dung'],
                        'diemY' => round((float) $y['diem'], 2),
                        'thuTu' => ++$thuTu,
                    ]);
                }
            }
        });

        NhatKy::ghi($request->user()->getKey(), 'Sửa câu hỏi', 'Câu hỏi', $cauHoi->getKey());

        return redirect()->route('cau-hoi.show', $cauHoi->getKey())
            ->with('thanh_cong', 'Đã lưu thay đổi.')
            ->with('canh_bao', $this->kiemTraLaiTrungLap($cauHoi));
    }

    public function duyet(Request $request, CauHoi $cauHoi): RedirectResponse
    {
        $this->chanNeuKhongCoQuyen($request, $cauHoi);

        if ($cauHoi->trangThaiDuyet !== 'Nháp') {
            return back()->with('loi', 'Chỉ câu hỏi ở trạng thái Nháp mới duyệt được.');
        }

        $cauHoi->load(['dapAnTracNghiems', 'dapAnTuLuanYs']);
        $loi = QuyenCauHoi::loiToanVen($cauHoi);
        if ($loi) {
            return back()->with('loi', 'Chưa thể duyệt: '.implode(' ', $loi).' Hãy bấm Sửa để chỉnh lại.');
        }

        $cauHoi->update([
            'trangThaiDuyet' => 'Đã duyệt',
            'maNguoiDuyet' => $request->user()->getKey(),
            'ngayDuyet' => now(),
        ]);

        $this->danhDauDaXuLyCanhBao($cauHoi);
        NhatKy::ghi($request->user()->getKey(), 'Duyệt câu hỏi', 'Câu hỏi', $cauHoi->getKey());

        return redirect()->route('cau-hoi.show', $cauHoi->getKey())
            ->with('thanh_cong', 'Đã duyệt, câu hỏi đã được lưu vào ngân hàng câu hỏi.');
    }

    public function tuChoi(Request $request, CauHoi $cauHoi): RedirectResponse
    {
        $this->chanNeuKhongCoQuyen($request, $cauHoi);

        if ($cauHoi->trangThaiDuyet !== 'Nháp') {
            return back()->with('loi', 'Chỉ câu hỏi ở trạng thái Nháp mới từ chối được.');
        }

        $du_lieu = $request->validate(['ly_do' => ['nullable', 'string', 'max:500']]);

        $cauHoi->update([
            'trangThaiDuyet' => 'Từ chối',
            'maNguoiDuyet' => $request->user()->getKey(),
            'ngayDuyet' => now(),
        ]);

        $this->danhDauDaXuLyCanhBao($cauHoi);
        NhatKy::ghi($request->user()->getKey(), 'Từ chối câu hỏi', 'Câu hỏi', $cauHoi->getKey(), $du_lieu['ly_do'] ?? null);

        return redirect()->route('cau-hoi.show', $cauHoi->getKey())->with('thanh_cong', 'Đã từ chối câu hỏi.');
    }

    public function xoa(Request $request, CauHoi $cauHoi): RedirectResponse
    {
        $this->chanNeuKhongCoQuyen($request, $cauHoi);

        if (QuyenCauHoi::trongDeThi($cauHoi)) {
            return back()->with('loi', 'Câu hỏi đang nằm trong một đề thi nên không được xóa.');
        }

        $ma = $cauHoi->getKey();
        $this->xoaCauHoi($cauHoi, $request->user()->getKey());

        return redirect()->route('cau-hoi.index')
            ->with('thanh_cong', 'Đã xóa câu hỏi #'.$ma.'.')
            ->with('canh_bao', $this->xoaVector([$ma]));
    }

    /** Xóa hàng loạt: chỉ câu Nháp/Từ chối, của chính mình (hoặc Quản trị viên), không nằm trong đề thi. */
    public function xoaNhieu(Request $request): RedirectResponse
    {
        $ids = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));
        if (! $ids) {
            return back()->with('loi', 'Chưa chọn câu hỏi nào.');
        }

        $nguoiDung = $request->user();
        $daXoa = 0;
        $boQua = 0;
        $maDaXoa = [];

        foreach (CauHoi::whereIn('maCauHoi', $ids)->get() as $cauHoi) {
            if (
                ! QuyenCauHoi::duocThaoTac($nguoiDung, $cauHoi)
                || ! in_array($cauHoi->trangThaiDuyet, ['Nháp', 'Từ chối'], true)
                || QuyenCauHoi::trongDeThi($cauHoi)
            ) {
                $boQua++;

                continue;
            }
            $maDaXoa[] = $cauHoi->getKey();
            $this->xoaCauHoi($cauHoi, $nguoiDung->getKey());
            $daXoa++;
        }

        $thongBao = "Đã xóa {$daXoa} câu hỏi.";
        if ($boQua > 0) {
            $thongBao .= " Bỏ qua {$boQua} câu (xóa hàng loạt chỉ áp dụng cho câu Nháp hoặc Từ chối của bạn, không nằm trong đề thi).";
        }

        return redirect()->route('cau-hoi.index')
            ->with('thanh_cong', $thongBao)
            ->with('canh_bao', $this->xoaVector($maDaXoa));
    }

    private function xoaCauHoi(CauHoi $cauHoi, int $maNguoiDung): void
    {
        $ma = $cauHoi->getKey();

        DB::transaction(function () use ($cauHoi, $ma) {
            DapAnTracNghiem::where('maCauHoi', $ma)->delete();
            DapAnTuLuanY::where('maCauHoi', $ma)->delete();
            KiemTraTrungLap::where('maCauHoi', $ma)->orWhere('maCauHoiTrung', $ma)->delete();
            $cauHoi->delete();
        });

        NhatKy::ghi($maNguoiDung, 'Xóa câu hỏi', 'Câu hỏi', $ma);
    }

    /** Kiểm tra lại trùng lặp sau khi sửa. Trả về thông báo cảnh báo (hoặc null nếu không có gì cần báo). */
    private function kiemTraLaiTrungLap(CauHoi $cauHoi): ?string
    {
        try {
            $ketQua = app(KiemTraTrungLapService::class)->kiemTra([$cauHoi->fresh()]);

            return $ketQua['canh_bao'] > 0
                ? 'Câu hỏi này có thể trùng lặp với một câu khác, xem mục "Kiểm tra trùng lặp" bên dưới.'
                : null;
        } catch (Throwable) {
            return 'Chưa kiểm tra được trùng lặp lúc này (RAG service hoặc Qdrant chưa chạy). Nội dung câu hỏi đã được lưu.';
        }
    }

    /** UC-08: sau khi giảng viên duyệt hoặc từ chối, cảnh báo trùng lặp của câu đó chuyển thành "Đã xử lý". */
    private function danhDauDaXuLyCanhBao(CauHoi $cauHoi): void
    {
        $ma = $cauHoi->getKey();

        KiemTraTrungLap::where('trangThaiCanhBao', 'Cảnh báo trùng lặp')
            ->where(fn ($q) => $q->where('maCauHoi', $ma)->orWhere('maCauHoiTrung', $ma))
            ->update(['trangThaiCanhBao' => 'Đã xử lý']);
    }

    /** Xóa vector trong Qdrant. Lỗi chỉ trả về thông báo (vector sót không ảnh hưởng vì kết quả luôn đối chiếu lại MySQL). */
    private function xoaVector(array $maCauHois): ?string
    {
        try {
            app(KiemTraTrungLapService::class)->xoaVector($maCauHois);

            return null;
        } catch (Throwable) {
            return 'Đã xóa câu hỏi nhưng chưa xóa được vector trong Qdrant (RAG service chưa chạy). Không ảnh hưởng kết quả sử dụng.';
        }
    }

    private function chanNeuKhongCoQuyen(Request $request, CauHoi $cauHoi): void
    {
        abort_unless(QuyenCauHoi::duocThaoTac($request->user(), $cauHoi), 403, 'Bạn không có quyền thao tác câu hỏi này.');
    }

    private function chanNeuBiKhoa(CauHoi $cauHoi): void
    {
        abort_if(
            QuyenCauHoi::biKhoaSua($cauHoi),
            403,
            'Câu hỏi nằm trong đề thi đã rời trạng thái Nháp nên bị khóa sửa (BR-15).'
        );
    }
}
