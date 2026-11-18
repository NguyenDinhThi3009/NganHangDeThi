<?php

namespace App\Http\Controllers;

use App\Models\NganHangDeThi\DeThi;
use App\Models\NganHangDeThi\PhanCongHocPhan;
use App\Services\XuatDeThiService;
use App\Support\NhatKy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** Xuất đề thi hoặc ngân hàng đề thi đã duyệt của học phần ra Word/PDF (UC-11, FR-12). */
class XuatDeThiController extends Controller
{
    public function __construct(private readonly XuatDeThiService $xuat) {}

    /** Xuất một đề (đề thi, đáp án hoặc cả hai). Người tạo đề và Quản trị viên được xuất (BR-12). */
    public function xuatDe(Request $request, DeThi $deThi): Response|BinaryFileResponse|RedirectResponse
    {
        $nguoi = $request->user();
        abort_unless($nguoi->vaiTro->tenVaiTro === 'Quản trị viên' || (int) $deThi->maNguoiTao === (int) $nguoi->getKey(), 403, 'Bạn không có quyền xuất đề thi này.');

        [$dinhDang, $mau] = $this->thongSo($request);

        return $this->taoFile($request, [$this->xuat->duLieu($deThi)], $dinhDang, $mau, Str::slug($deThi->tenDeThi) ?: 'de-thi-'.$deThi->getKey(), 'Đề thi', $deThi->getKey());
    }

    /** Xuất ngân hàng đề thi: gộp các đề đã duyệt của một học phần. */
    public function xuatNganHang(Request $request): Response|BinaryFileResponse|RedirectResponse
    {
        $nguoi = $request->user();
        $maHocPhan = (string) $request->validate(['hoc_phan' => ['required', 'string', Rule::exists('hocphan', 'maHocPhan')]], [
            'hoc_phan.required' => 'Vui lòng chọn học phần cần xuất ngân hàng đề.',
        ])['hoc_phan'];

        // BR-12: chỉ thành viên được phân công của học phần (hoặc Quản trị viên) truy cập ngân hàng đề thi
        $duocXem = in_array($nguoi->vaiTro->tenVaiTro, ['Quản trị viên', 'Phòng ĐT&ĐBCLGD'], true)
            || PhanCongHocPhan::where('maHocPhan', $maHocPhan)->where('maNguoiDung', $nguoi->getKey())->where('trangThai', 'Đang hiệu lực')->exists();
        abort_unless($duocXem, 403, 'Bạn không thuộc phân công của học phần này nên không xuất được ngân hàng đề thi.');

        [$dinhDang, $mau] = $this->thongSo($request);

        $cacDe = DeThi::where('trangThai', 'Đã duyệt')->whereHas('maTranDeThi', fn ($q) => $q->where('maHocPhan', $maHocPhan))
            ->orderBy('hinhThucThi')->orderBy('maDeThi')->get();
        if ($cacDe->isEmpty()) {
            return back()->with('loi', 'Học phần này chưa có đề thi nào ở trạng thái Đã duyệt để xuất.');
        }

        return $this->taoFile($request, $cacDe->map(fn (DeThi $d) => $this->xuat->duLieu($d))->all(), $dinhDang, $mau,
            'ngan-hang-de-'.Str::slug($maHocPhan), 'Ngân hàng đề thi', null, "{$maHocPhan}, {$cacDe->count()} đề");
    }

    /** Xuất đề đã chọn cho ca thi: với trắc nghiệm là các mã đề đã xáo trộn kèm đáp án từng mã (UC-18, UC-11). */
    public function xuatSuDung(Request $request, \App\Models\NganHangDeThi\SuDungDeThi $suDung): Response|BinaryFileResponse|RedirectResponse
    {
        [$dinhDang, $mau] = $this->thongSo($request);
        $suDung->load(['deThi', 'maDeTracNghiems']);
        $deThi = $suDung->deThi;

        if ($deThi->hinhThucThi === 'Trắc nghiệm' && $suDung->maDeTracNghiems->isNotEmpty()) {
            $chon = (string) $request->query('ma', 'tat-ca');
            $cacMa = $suDung->maDeTracNghiems->sortBy('kyHieuMa')->when($chon !== 'tat-ca', fn ($c) => $c->where('maMaDe', (int) $chon));
            abort_if($cacMa->isEmpty(), 404);
            $cacDe = $cacMa->map(fn ($ma) => $this->xuat->duLieuMaDe($deThi, $ma))->values()->all();
        } else {
            $cacDe = [$this->xuat->duLieu($deThi)];
        }

        return $this->taoFile($request, $cacDe, $dinhDang, $mau, 'ca-thi-'.$suDung->getKey().'-'.(Str::slug($deThi->tenDeThi) ?: 'de-thi'),
            'Sử dụng đề thi', $suDung->getKey(), "đề #{$deThi->getKey()}");
    }

    /** @return array{0: string, 1: string} */
    private function thongSo(Request $request): array
    {
        $d = $request->validate([
            'dinh_dang' => ['required', 'in:docx,pdf'],
            'mau' => ['required', Rule::in(array_keys(XuatDeThiService::MAU))],
        ], [
            'dinh_dang.required' => 'Vui lòng chọn định dạng Word hoặc PDF.',
            'mau.required' => 'Vui lòng chọn mẫu trình bày.',
        ]);

        return [$d['dinh_dang'], $d['mau']];
    }

    /** UC-11, luồng ngoại lệ: lỗi khi tạo file thì thông báo và ghi nhật ký. */
    private function taoFile(Request $request, array $cacDe, string $dinhDang, string $mau, string $tenFile, string $doiTuong, ?int $maDoiTuong, ?string $ghiChu = null): Response|BinaryFileResponse|RedirectResponse
    {
        $nguoi = $request->user()->getKey();

        try {
            if ($dinhDang === 'docx') {
                $duongDan = $this->xuat->taoWord($cacDe, $mau);
                $phanHoi = response()->download($duongDan, "{$tenFile}.docx", [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ])->deleteFileAfterSend(true);
            } else {
                $phanHoi = $this->phanHoiPdf($cacDe, $mau, $tenFile);
            }
        } catch (Throwable $e) {
            report($e);
            NhatKy::ghi($nguoi, 'Lỗi xuất file', $doiTuong, $maDoiTuong, mb_substr($e->getMessage(), 0, 500));

            return back()->with('loi', 'Không tạo được file. Lỗi đã được ghi lại để kiểm tra: '.mb_substr($e->getMessage(), 0, 200));
        }

        NhatKy::ghi($nguoi, 'Xuất file đề thi', $doiTuong, $maDoiTuong, strtoupper($dinhDang).', mẫu: '.XuatDeThiService::MAU[$mau].($ghiChu ? ", {$ghiChu}" : ''));

        return $phanHoi;
    }

    /** PDF thật khi đã cài dompdf; nếu chưa thì trả trang in để người dùng Lưu dưới dạng PDF. */
    private function phanHoiPdf(array $cacDe, string $mau, string $tenFile): Response
    {
        $html = view('xuat.html', ['cacDe' => $cacDe, 'mau' => $mau, 'tieuDe' => $tenFile, 'inTrucTiep' => ! class_exists(\Dompdf\Dompdf::class)])->render();

        if (! class_exists(\Dompdf\Dompdf::class)) {
            return response($html);
        }

        $dompdf = new \Dompdf\Dompdf(['defaultFont' => 'DejaVu Serif', 'isRemoteEnabled' => false]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() / 2 - 25, $canvas->get_height() - 32, 'Trang {PAGE} / {PAGE_COUNT}', $font, 8, [0, 0, 0]);

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$tenFile.'.pdf"',
        ]);
    }
}
