<?php

namespace App\Console\Commands;

use App\Models\NganHangDeThi\CauHoi;
use App\Services\KiemTraTrungLapService;
use Illuminate\Console\Command;
use Throwable;

/** Tạo vector và kiểm tra trùng lặp cho các câu hỏi đã có trong ngân hàng (chưa được kiểm tra). */
class KiemTraTrungLapCommand extends Command
{
    protected $signature = 'cau-hoi:kiem-tra-trung-lap {--lai : Kiểm tra lại toàn bộ, kể cả câu hỏi đã có vector}';

    protected $description = 'Tạo vector và kiểm tra trùng lặp cho các câu hỏi chưa được kiểm tra';

    public function handle(KiemTraTrungLapService $dichVu): int
    {
        $truyVan = CauHoi::query();
        if (! $this->option('lai')) {
            $truyVan->whereNull('vectorId');
        }

        $tong = (clone $truyVan)->count();
        if ($tong === 0) {
            $this->info('Không có câu hỏi nào cần kiểm tra.');

            return self::SUCCESS;
        }

        $this->info("Đang kiểm tra {$tong} câu hỏi (lần đầu có thể mất vài phút do RAG service nạp mô hình)...");

        $daKiemTra = 0;
        $canhBao = 0;

        try {
            // Duyệt theo mã tăng dần để câu sau được so với các câu trước
            $truyVan->chunkById(20, function ($lo) use ($dichVu, &$daKiemTra, &$canhBao) {
                $kq = $dichVu->kiemTra($lo);
                $daKiemTra += $kq['da_kiem_tra'];
                $canhBao += $kq['canh_bao'];
                $this->line("  đã xử lý {$daKiemTra}...");
            }, 'maCauHoi');
        } catch (Throwable $loi) {
            $this->error('Dừng do lỗi: '.$loi->getMessage());

            return self::FAILURE;
        }

        $this->info("Hoàn tất: {$daKiemTra} câu đã kiểm tra, {$canhBao} câu bị cảnh báo trùng lặp.");

        return self::SUCCESS;
    }
}
