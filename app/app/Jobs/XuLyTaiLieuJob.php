<?php

namespace App\Jobs;

use App\Models\NganHangDeThi\CauHoi;
use App\Models\NganHangDeThi\DoanTaiLieu;
use App\Models\NganHangDeThi\NhatKyGoiLLM;
use App\Models\NganHangDeThi\TaiLieu;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Xử lý nền một tài liệu: gửi sang RAG service để trích văn bản, chia đoạn, tạo vector,
 * rồi lưu các đoạn vào bảng doantailieu. Vòng đời: Chờ xử lý -> Đang xử lý -> Sẵn sàng sử dụng | Lỗi.
 */
class XuLyTaiLieuJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;      // không tự thử lại, tránh xử lý trùng; lỗi thì chuyển trạng thái "Lỗi"
    public int $timeout = 600;  // giây. Phải nhỏ hơn DB_QUEUE_RETRY_AFTER trong app/.env

    public function __construct(public int $maTaiLieu)
    {
    }

    public function handle(): void
    {
        $taiLieu = TaiLieu::find($this->maTaiLieu);
        if (! $taiLieu) {
            return;
        }

        $batDau = microtime(true);
        $taiLieu->update(['trangThaiXuLy' => 'Đang xử lý']);

        try {
            if (! Storage::exists($taiLieu->duongDanFile)) {
                throw new RuntimeException('Không tìm thấy file trên máy chủ: '.$taiLieu->duongDanFile);
            }

            // Tên file chỉ cần đúng phần mở rộng để RAG service nhận diện định dạng
            $tenFile = 'tai-lieu-'.$taiLieu->maTaiLieu.'.'.strtolower($taiLieu->dinhDang);

            $phanHoi = Http::timeout(config('rag.timeout'))
                ->attach('file', Storage::get($taiLieu->duongDanFile), $tenFile)
                ->post(rtrim(config('rag.url'), '/').'/v1/documents/ingest', [
                    'document_id' => $taiLieu->maTaiLieu,
                ]);

            if ($phanHoi->failed()) {
                $chiTiet = $phanHoi->json('detail') ?? $phanHoi->body();
                throw new RuntimeException('RAG service trả về '.$phanHoi->status().': '.(is_string($chiTiet) ? $chiTiet : json_encode($chiTiet, JSON_UNESCAPED_UNICODE)));
            }

            $ketQua = $phanHoi->json();
            $cacDoan = $ketQua['chunks'] ?? [];
            $mo_hinh = $ketQua['model_embedding'] ?? 'unknown';

            DB::transaction(function () use ($taiLieu, $cacDoan, $mo_hinh) {
                // Xử lý lại: bỏ liên kết nguồn của các câu hỏi cũ rồi xóa đoạn cũ (tránh vướng khóa ngoại)
                $idCu = DoanTaiLieu::where('maTaiLieu', $taiLieu->maTaiLieu)->pluck('maDoanTaiLieu');
                if ($idCu->isNotEmpty()) {
                    CauHoi::whereIn('maDoanTaiLieu', $idCu)->update(['maDoanTaiLieu' => null]);
                    DoanTaiLieu::whereIn('maDoanTaiLieu', $idCu)->delete();
                }

                $bayGio = now()->toDateTimeString();
                $hang = array_map(fn (array $doan) => [
                    'maTaiLieu' => $taiLieu->maTaiLieu,
                    'noiDungDoan' => $doan['text'],
                    'viTriTrang' => $doan['page'],
                    'vectorId' => $doan['vector_id'],
                    'modelEmbedding' => $mo_hinh,
                    'ngayTao' => $bayGio,
                ], $cacDoan);

                foreach (array_chunk($hang, 100) as $lo) {
                    DoanTaiLieu::insert($lo);
                }
            });

            $taiLieu->update(['trangThaiXuLy' => 'Sẵn sàng sử dụng']);
            $this->ghiNhatKy($taiLieu, $mo_hinh, $batDau, 'Thành công', null);
        } catch (Throwable $loi) {
            $taiLieu->update(['trangThaiXuLy' => 'Lỗi']);
            $this->ghiNhatKy($taiLieu, config('rag.url'), $batDau, 'Lỗi', Str::limit($loi->getMessage(), 2000));
        }
    }

    /** Được gọi khi job bị dừng đột ngột (ví dụ quá thời gian) mà không chạy tới khối catch ở trên. */
    public function failed(Throwable $loi): void
    {
        TaiLieu::where('maTaiLieu', $this->maTaiLieu)->update(['trangThaiXuLy' => 'Lỗi']);
    }

    private function ghiNhatKy(TaiLieu $taiLieu, string $tenMoHinh, float $batDau, string $trangThai, ?string $loi): void
    {
        NhatKyGoiLLM::create([
            'maNguoiDung' => $taiLieu->maNguoiTaiLen,
            'maMauPrompt' => null,
            'loaiTacVu' => 'Embedding',
            'tenModel' => Str::limit($tenMoHinh, 100, ''),
            'soTokenVao' => 0,
            'soTokenRa' => 0,
            'thoiGianXuLyMs' => (int) round((microtime(true) - $batDau) * 1000),
            'trangThai' => $trangThai,
            'thongBaoLoi' => $loi,
            'ngayTao' => now(),
        ]);
    }
}
