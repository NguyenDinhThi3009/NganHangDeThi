<?php

namespace App\Http\Controllers;

use App\Models\NganHangDeThi\DeThi;
use App\Models\NganHangDeThi\HocPhan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Theo dõi cơ cấu ngân hàng đề thi theo học phần so với mức tối thiểu quy định (UC-17, FR-18). */
class NganHangDeThiController extends Controller
{
    /** Mức tối thiểu theo quy định: 12 đề tự luận, 5 đề trắc nghiệm, 18 đề vấn đáp mỗi học phần. */
    public const TOI_THIEU = ['Tự luận' => 12, 'Trắc nghiệm' => 5, 'Vấn đáp' => 18];

    public function index(): View
    {
        $dem = DB::table('dethi')
            ->join('matrandethi', 'matrandethi.maMaTranDeThi', '=', 'dethi.maMaTranDeThi')
            ->where('dethi.trangThai', 'Đã duyệt')
            ->selectRaw('matrandethi.maHocPhan as ma, dethi.hinhThucThi as ht, COUNT(*) as so')
            ->groupBy('matrandethi.maHocPhan', 'dethi.hinhThucThi')->get();

        $theoHocPhan = [];
        foreach ($dem as $d) {
            $theoHocPhan[$d->ma][$d->ht] = (int) $d->so;
        }

        $hang = HocPhan::orderBy('maHocPhan')->get(['maHocPhan', 'tenHocPhan'])->map(function ($h) use ($theoHocPhan) {
            $cu = [];
            $thieu = 0;
            foreach (self::TOI_THIEU as $ht => $min) {
                $co = $theoHocPhan[$h->maHocPhan][$ht] ?? 0;
                $cu[$ht] = ['co' => $co, 'min' => $min, 'thieu' => max(0, $min - $co)];
                $thieu += max(0, $min - $co);
            }

            return ['hoc_phan' => $h, 'theo_hinh_thuc' => $cu, 'tong_thieu' => $thieu, 'co_de' => isset($theoHocPhan[$h->maHocPhan])];
        });

        return view('ngan-hang.index', ['hang' => $hang, 'toiThieu' => self::TOI_THIEU]);
    }

    public function show(string $maHocPhan): View
    {
        $hocPhan = HocPhan::findOrFail($maHocPhan);

        $cacDe = DeThi::where('trangThai', 'Đã duyệt')
            ->whereHas('maTranDeThi', fn ($q) => $q->where('maHocPhan', $maHocPhan))
            ->with(['nguoiTao', 'nguoiDuyet', 'suDungDeThis'])->withCount('deThiCauHois')
            ->orderBy('hinhThucThi')->orderBy('maDeThi')->get();

        return view('ngan-hang.show', [
            'hocPhan' => $hocPhan,
            'theoHinhThuc' => $cacDe->groupBy('hinhThucThi'),
            'toiThieu' => self::TOI_THIEU,
        ]);
    }
}
