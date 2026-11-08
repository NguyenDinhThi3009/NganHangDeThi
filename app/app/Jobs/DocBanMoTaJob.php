<?php

namespace App\Jobs;

use App\Http\Controllers\NhapBanMoTaController;
use App\Models\NganHangDeThi\NhatKyGoiLLM;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Nhờ RAG service đọc các trang đã chọn của Bản mô tả CTĐT (bản scan) bằng mô hình đọc ảnh.
 * Việc này mất vài phút nên chạy nền; kết quả và trạng thái ghi vào cache để trang theo dõi hiển thị.
 */
class DocBanMoTaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 880; // giây, nhỏ hơn DB_QUEUE_RETRY_AFTER (900); chế độ tự động có thể gọi AI tối đa 3 lần

    public function __construct(public string $token)
    {
    }

    public function handle(): void
    {
        $khoa = NhapBanMoTaController::khoaCache($this->token);
        $duLieu = Cache::get($khoa);
        if (! $duLieu) {
            return;
        }

        $batDau = microtime(true);

        try {
            if (! Storage::exists($duLieu['duong_dan_tam'])) {
                throw new RuntimeException('Không tìm thấy file tạm, hãy tải lại Bản mô tả.');
            }

            $phanHoi = Http::timeout(840)
                ->acceptJson()
                ->attach('file', Storage::get($duLieu['duong_dan_tam']), 'ban-mo-ta.pdf')
                ->post(rtrim(config('rag.url'), '/').'/v1/ctdt/parse', ['pages' => $duLieu['trang']]);

            if ($phanHoi->failed()) {
                $chiTiet = $phanHoi->json('detail') ?? 'mã lỗi '.$phanHoi->status();
                throw new RuntimeException(is_string($chiTiet) ? $chiTiet : json_encode($chiTiet, JSON_UNESCAPED_UNICODE));
            }

            $ketQua = $phanHoi->json();
            $duLieu['trang_thai'] = 'Hoàn tất';
            $duLieu['thong_bao'] = 'Đã đọc xong '.($ketQua['so_trang_da_doc'] ?? '?').' trang (trang '.($ketQua['trang_da_doc'] ?? '?').').';
            $duLieu['ket_qua'] = $ketQua;

            $this->ghiNhatKy($duLieu['ma_nguoi_dung'], $ketQua['ten_model'] ?? 'unknown', (int) ($ketQua['so_token_vao'] ?? 0), (int) ($ketQua['so_token_ra'] ?? 0), $batDau, 'Thành công', null);
        } catch (Throwable $loi) {
            $duLieu['trang_thai'] = 'Lỗi';
            $duLieu['thong_bao'] = Str::limit($loi->getMessage(), 500);
            $this->ghiNhatKy($duLieu['ma_nguoi_dung'], 'unknown', 0, 0, $batDau, 'Lỗi', Str::limit($loi->getMessage(), 2000));
        }

        Cache::put($khoa, $duLieu, now()->addHours(3));
    }

    /** Job bị dừng đột ngột (ví dụ quá thời gian) mà chưa chạy tới khối catch. */
    public function failed(Throwable $loi): void
    {
        $khoa = NhapBanMoTaController::khoaCache($this->token);
        if ($duLieu = Cache::get($khoa)) {
            $duLieu['trang_thai'] = 'Lỗi';
            $duLieu['thong_bao'] = 'Tiến trình bị dừng: '.Str::limit($loi->getMessage(), 300);
            Cache::put($khoa, $duLieu, now()->addHours(3));
        }
    }

    private function ghiNhatKy(int $maNguoiDung, string $tenModel, int $tokenVao, int $tokenRa, float $batDau, string $trangThai, ?string $loi): void
    {
        NhatKyGoiLLM::create([
            'maNguoiDung' => $maNguoiDung,
            'maMauPrompt' => null,
            'loaiTacVu' => 'Đọc Bản mô tả CTĐT',
            'tenModel' => Str::limit($tenModel, 100, ''),
            'soTokenVao' => $tokenVao,
            'soTokenRa' => $tokenRa,
            'thoiGianXuLyMs' => (int) round((microtime(true) - $batDau) * 1000),
            'trangThai' => $trangThai,
            'thongBaoLoi' => $loi,
            'ngayTao' => now(),
        ]);
    }
}
