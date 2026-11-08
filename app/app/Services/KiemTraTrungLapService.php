<?php

namespace App\Services;

use App\Models\NganHangDeThi\CauHoi;
use App\Models\NganHangDeThi\KiemTraTrungLap;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Phát hiện câu hỏi trùng lặp ngữ nghĩa (UC-07, FR-08).
 * RAG service tạo vector và tìm câu gần nghĩa nhất trong cùng học phần; lớp này so với ngưỡng và lưu kết quả.
 */
class KiemTraTrungLapService
{
    private const KICH_THUOC_LO = 25;

    /**
     * Tạo vector (lưu vectorId, modelEmbedding) và kiểm tra trùng lặp cho các câu hỏi, theo đúng thứ tự đưa vào.
     *
     * @param  iterable<CauHoi>  $cauHois
     * @return array{da_kiem_tra: int, canh_bao: int}
     */
    public function kiemTra(iterable $cauHois): array
    {
        $danhSach = collect($cauHois)->values();
        $daKiemTra = 0;
        $canhBao = 0;

        foreach ($danhSach->chunk(self::KICH_THUOC_LO) as $lo) {
            $theoMa = $lo->keyBy(fn (CauHoi $c) => (int) $c->getKey());

            $phanHoi = Http::timeout(config('rag.timeout'))
                ->acceptJson()
                ->post(rtrim(config('rag.url'), '/').'/v1/questions/check', [
                    'items' => $theoMa->map(fn (CauHoi $c) => [
                        'ma_cau_hoi' => (int) $c->getKey(),
                        'ma_hoc_phan' => $c->maHocPhan,
                        'noi_dung' => $c->noiDungCauHoi,
                    ])->values()->all(),
                    'top_k' => 5,
                ]);

            if ($phanHoi->failed()) {
                $chiTiet = $phanHoi->json('detail') ?? $phanHoi->body();
                throw new RuntimeException('RAG service trả về '.$phanHoi->status().': '.(is_string($chiTiet) ? $chiTiet : json_encode($chiTiet, JSON_UNESCAPED_UNICODE)));
            }

            $moHinh = Str::limit((string) ($phanHoi->json('model_embedding') ?? 'unknown'), 100, '');

            foreach ($phanHoi->json('results', []) as $ketQua) {
                $cauHoi = $theoMa->get((int) $ketQua['ma_cau_hoi']);
                if (! $cauHoi) {
                    continue;
                }

                $cauHoi->update(['vectorId' => $ketQua['vector_id'], 'modelEmbedding' => $moHinh]);
                $canhBao += $this->luuKetQua($cauHoi, $ketQua['neighbors'] ?? []);
                $daKiemTra++;
            }
        }

        return ['da_kiem_tra' => $daKiemTra, 'canh_bao' => $canhBao];
    }

    /** Xóa vector của các câu hỏi đã bị xóa khỏi ngân hàng. */
    public function xoaVector(array $maCauHois): void
    {
        if (! $maCauHois) {
            return;
        }

        $phanHoi = Http::timeout(60)
            ->acceptJson()
            ->post(rtrim(config('rag.url'), '/').'/v1/questions/delete', [
                'ma_cau_hoi' => array_values(array_map('intval', $maCauHois)),
            ]);

        if ($phanHoi->failed()) {
            throw new RuntimeException('RAG service trả về '.$phanHoi->status().' khi xóa vector câu hỏi.');
        }
    }

    /**
     * Lưu kết quả so với câu hỏi gần nghĩa nhất còn hiệu lực. Trả về 1 nếu vượt ngưỡng cảnh báo, ngược lại 0.
     * Mỗi cặp chỉ lưu một chiều (maCauHoi < maCauHoiTrung) theo ràng buộc của ERD.
     *
     * @param  list<array{ma_cau_hoi: int, score: float}>  $hangXom  xếp từ gần nhất tới xa nhất
     */
    private function luuKetQua(CauHoi $cauHoi, array $hangXom): int
    {
        $ma = (int) $cauHoi->getKey();

        // Kiểm tra lại: bỏ các kết quả cũ chưa xử lý của câu này (nội dung có thể đã đổi)
        KiemTraTrungLap::where('trangThaiCanhBao', '!=', 'Đã xử lý')
            ->where(fn ($q) => $q->where('maCauHoi', $ma)->orWhere('maCauHoiTrung', $ma))
            ->delete();

        if (! $hangXom) {
            return 0;
        }

        $nguong = (float) config('rag.nguong_trung_lap');

        // Chỉ so với câu còn tồn tại trong MySQL và không bị từ chối (vector câu đã xóa có thể còn sót trong Qdrant)
        $conHieuLuc = CauHoi::whereIn('maCauHoi', array_map(fn ($n) => (int) $n['ma_cau_hoi'], $hangXom))
            ->where('trangThaiDuyet', '!=', 'Từ chối')
            ->pluck('maCauHoi')
            ->map(fn ($v) => (int) $v)
            ->all();

        foreach ($hangXom as $hangXomNay) {
            $maKhac = (int) $hangXomNay['ma_cau_hoi'];
            if (! in_array($maKhac, $conHieuLuc, true)) {
                continue;
            }

            $diem = max(0.0, min(1.0, (float) $hangXomNay['score']));
            $nho = min($ma, $maKhac);
            $lon = max($ma, $maKhac);

            $cu = KiemTraTrungLap::where('maCauHoi', $nho)->where('maCauHoiTrung', $lon)->first();
            if ($cu && $cu->trangThaiCanhBao === 'Đã xử lý') {
                return 0; // giảng viên đã quyết định cho cặp này, không cảnh báo lại
            }

            $trungLap = $diem > $nguong;

            KiemTraTrungLap::updateOrCreate(
                ['maCauHoi' => $nho, 'maCauHoiTrung' => $lon],
                [
                    'doTuongDong' => round($diem, 4),
                    'ngayKiemTra' => now(),
                    'trangThaiCanhBao' => $trungLap ? 'Cảnh báo trùng lặp' : 'Không trùng',
                    'nguongApDung' => $nguong, // giữ lại ngưỡng đã dùng để kiểm chứng sau này
                ]
            );

            return $trungLap ? 1 : 0;
        }

        return 0;
    }
}
