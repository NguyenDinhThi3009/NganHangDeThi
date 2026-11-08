<?php

namespace App\Jobs;

use App\Models\NganHangDeThi\CauHoi;
use App\Models\NganHangDeThi\Clo;
use App\Models\NganHangDeThi\DapAnTracNghiem;
use App\Models\NganHangDeThi\DapAnTuLuanY;
use App\Models\NganHangDeThi\DoanTaiLieu;
use App\Models\NganHangDeThi\HocPhan;
use App\Models\NganHangDeThi\NhatKyGoiLLM;
use App\Services\KiemTraTrungLapService;
use App\Support\MucDoBloom;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Sinh câu hỏi bằng RAG service rồi lưu vào cauhoi (trạng thái "Nháp", nguồn "AI sinh")
 * cùng đáp án và nhật ký gọi LLM. Trạng thái tiến trình ghi vào cache để hiển thị cho người dùng.
 */
class SinhCauHoiJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 600; // giây, phải nhỏ hơn DB_QUEUE_RETRY_AFTER

    /**
     * @param  list<int>  $maTaiLieus
     */
    public function __construct(
        public int $maNguoiDung,
        public string $maHocPhan,
        public int $maClo,
        public string $nhanBloom,   // nhãn tiếng Việt, ví dụ "Hiểu"
        public string $doKho,
        public string $loaiCauHoi,  // "Trắc nghiệm" hoặc "Tự luận"
        public int $soLuong,
        public float $diemCau,
        public array $maTaiLieus,
    ) {
    }

    public static function khoaTrangThai(int $maNguoiDung): string
    {
        return 'sinh_cau_hoi:'.$maNguoiDung;
    }

    public function handle(): void
    {
        $batDau = microtime(true);
        $clo = Clo::find($this->maClo);
        $hocPhan = HocPhan::find($this->maHocPhan);

        try {
            if (! $clo || ! $hocPhan || $clo->maHocPhan !== $hocPhan->maHocPhan) {
                throw new RuntimeException('CLO không tồn tại hoặc không thuộc học phần đã chọn.');
            }

            $phanHoi = Http::timeout(config('rag.timeout'))
                ->acceptJson()
                ->post(rtrim(config('rag.url'), '/').'/v1/questions/generate', [
                    'hoc_phan' => $hocPhan->tenHocPhan,
                    'clo_ky_hieu' => $clo->kyHieu,
                    'clo_mo_ta' => $clo->moTa,
                    'muc_do_bloom' => $this->nhanBloom,
                    'do_kho' => $this->doKho,
                    'loai_cau_hoi' => $this->loaiCauHoi,
                    'so_luong' => $this->soLuong,
                    'diem_cau' => $this->diemCau,
                    'document_ids' => $this->maTaiLieus,
                ]);

            if ($phanHoi->failed()) {
                $chiTiet = $phanHoi->json('detail') ?? $phanHoi->body();
                throw new RuntimeException('RAG service trả về '.$phanHoi->status().': '.(is_string($chiTiet) ? $chiTiet : json_encode($chiTiet, JSON_UNESCAPED_UNICODE)));
            }

            $ketQua = $phanHoi->json();
            $cauHois = $ketQua['questions'] ?? [];
            $canhBao = $ketQua['canh_bao'] ?? [];

            $daTao = [];

            DB::transaction(function () use ($ketQua, $cauHois, $batDau, &$daTao) {
                $nhatKy = NhatKyGoiLLM::create([
                    'maNguoiDung' => $this->maNguoiDung,
                    'maMauPrompt' => null,
                    'loaiTacVu' => 'Sinh câu hỏi',
                    'tenModel' => Str::limit($ketQua['ten_model'] ?? 'unknown', 100, ''),
                    'soTokenVao' => (int) ($ketQua['so_token_vao'] ?? 0),
                    'soTokenRa' => (int) ($ketQua['so_token_ra'] ?? 0),
                    'thoiGianXuLyMs' => (int) round((microtime(true) - $batDau) * 1000),
                    'trangThai' => 'Thành công',
                    'thongBaoLoi' => null,
                    'ngayTao' => now(),
                ]);

                foreach ($cauHois as $q) {
                    // Đoạn nguồn chính = đoạn đầu tiên trong các đoạn LLM đã dùng (khớp qua vectorId)
                    $maDoan = null;
                    if (! empty($q['nguon_vector_ids'])) {
                        $maDoan = DoanTaiLieu::where('vectorId', $q['nguon_vector_ids'][0])->value('maDoanTaiLieu');
                    }

                    $cauHoi = CauHoi::create([
                        'maHocPhan' => $this->maHocPhan,
                        'noiDungCauHoi' => $q['noi_dung'],
                        'loaiCauHoi' => $this->loaiCauHoi,
                        'mucDoBloom' => MucDoBloom::sangCsdl($this->nhanBloom),
                        'doKho' => $this->doKho,
                        'doPhanHoa' => null,
                        'nguonGoc' => 'AI sinh',
                        'trangThaiDuyet' => 'Nháp',
                        'maClo' => $this->maClo,
                        'maDoanTaiLieu' => $maDoan,
                        'maNguoiDuyet' => null,
                        'maNguoiTao' => $this->maNguoiDung,
                        'ngayTao' => now(),
                        'ngayDuyet' => null,
                        'vectorId' => null,
                        'modelEmbedding' => null,
                        'maNhatKyLLM' => $nhatKy->getKey(),
                    ]);
                    $daTao[] = $cauHoi;

                    foreach (array_values($q['dap_an_trac_nghiem'] ?? []) as $i => $da) {
                        DapAnTracNghiem::create([
                            'maCauHoi' => $cauHoi->getKey(),
                            'noiDungDapAn' => $da['noi_dung'],
                            'laDapAnDung' => (bool) $da['la_dap_an_dung'],
                            'thuTu' => $i + 1,
                        ]);
                    }

                    foreach (array_values($q['y_dap_an_tu_luan'] ?? []) as $i => $y) {
                        DapAnTuLuanY::create([
                            'maCauHoi' => $cauHoi->getKey(),
                            'noiDungY' => $y['noi_dung'],
                            'diemY' => $y['diem'],
                            'thuTu' => $i + 1,
                        ]);
                    }
                }
            });

            // Kiểm tra trùng lặp ngữ nghĩa (UC-07). Nếu lỗi thì không làm hỏng kết quả sinh câu hỏi.
            $soCanhBaoTrung = 0;
            try {
                $soCanhBaoTrung = app(KiemTraTrungLapService::class)->kiemTra($daTao)['canh_bao'];
            } catch (Throwable $loiTrung) {
                $canhBao[] = 'Chưa kiểm tra được trùng lặp ('.Str::limit($loiTrung->getMessage(), 200).'). Chạy lại bằng lệnh: php artisan cau-hoi:kiem-tra-trung-lap';
            }

            $thongBao = count($cauHois).' câu hỏi đã được lưu (trạng thái Nháp).';
            if ($soCanhBaoTrung > 0) {
                $thongBao .= " Có {$soCanhBaoTrung} câu bị cảnh báo trùng lặp, xem cột Trùng lặp trong danh sách.";
            }

            $this->ghiTrangThai('Hoàn tất', $thongBao, $canhBao);
        } catch (Throwable $loi) {
            NhatKyGoiLLM::create([
                'maNguoiDung' => $this->maNguoiDung,
                'maMauPrompt' => null,
                'loaiTacVu' => 'Sinh câu hỏi',
                'tenModel' => 'unknown',
                'soTokenVao' => 0,
                'soTokenRa' => 0,
                'thoiGianXuLyMs' => (int) round((microtime(true) - $batDau) * 1000),
                'trangThai' => 'Lỗi',
                'thongBaoLoi' => Str::limit($loi->getMessage(), 2000),
                'ngayTao' => now(),
            ]);
            $this->ghiTrangThai('Lỗi', Str::limit($loi->getMessage(), 500), []);
        }
    }

    /** Job bị dừng đột ngột (ví dụ quá thời gian) mà chưa chạy tới khối catch. */
    public function failed(Throwable $loi): void
    {
        $this->ghiTrangThai('Lỗi', 'Tiến trình bị dừng: '.Str::limit($loi->getMessage(), 300), []);
    }

    private function ghiTrangThai(string $trangThai, string $thongBao, array $canhBao): void
    {
        Cache::put(self::khoaTrangThai($this->maNguoiDung), [
            'trang_thai' => $trangThai,
            'thong_bao' => $thongBao,
            'canh_bao' => $canhBao,
            'ma_hoc_phan' => $this->maHocPhan,
            'cap_nhat' => now()->format('H:i:s d/m/Y'),
        ], now()->addHours(6));
    }
}
