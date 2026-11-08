@extends('layouts.app')

@section('tieu_de', 'Câu hỏi #'.$cauHoi->maCauHoi.' - Ngân hàng đề thi')

@section('noi_dung')
    <p><a href="{{ route('cau-hoi.index') }}">&larr; Danh sách câu hỏi</a></p>

    <div class="the">
        <h2>Câu hỏi #{{ $cauHoi->maCauHoi }}</h2>
        <p style="font-size:16px; line-height:1.5;">{{ $cauHoi->noiDungCauHoi }}</p>

        <table style="margin:16px 0;">
            <tr><th style="width:180px;">Học phần</th><td>{{ $cauHoi->maHocPhan }} - {{ $cauHoi->hocPhan?->tenHocPhan }}</td></tr>
            <tr><th>CLO</th><td>{{ $cauHoi->clo?->kyHieu }} - {{ $cauHoi->clo?->moTa }}</td></tr>
            <tr><th>Loại / Bloom / Độ khó</th><td>{{ $cauHoi->loaiCauHoi }} / {{ \App\Support\MucDoBloom::sangNhan($cauHoi->mucDoBloom) }} ({{ $cauHoi->mucDoBloom }}) / {{ $cauHoi->doKho }}</td></tr>
            <tr><th>Nguồn gốc / Trạng thái</th><td>{{ $cauHoi->nguonGoc }} / <strong>{{ $cauHoi->trangThaiDuyet }}</strong></td></tr>
            <tr><th>Người tạo</th><td>{{ $cauHoi->nguoiTao?->hoTen }} - {{ $cauHoi->ngayTao?->format('d/m/Y H:i') }}</td></tr>
            @if ($cauHoi->nguoiDuyet)
                <tr><th>Người duyệt</th><td>{{ $cauHoi->nguoiDuyet->hoTen }} - {{ $cauHoi->ngayDuyet?->format('d/m/Y H:i') }}</td></tr>
            @endif
            @if ($cauHoi->nhatKyLLM)
                <tr><th>Lần gọi LLM</th><td>{{ $cauHoi->nhatKyLLM->tenModel }} · {{ $cauHoi->nhatKyLLM->soTokenVao }} token vào / {{ $cauHoi->nhatKyLLM->soTokenRa }} token ra · {{ round($cauHoi->nhatKyLLM->thoiGianXuLyMs / 1000, 1) }} giây</td></tr>
            @endif
        </table>

        {{-- Nút thao tác --}}
        <div class="hang-nut">
            @if ($cauHoi->trangThaiDuyet === 'Nháp')
                <form method="POST" action="{{ route('cau-hoi.duyet', $cauHoi->maCauHoi) }}">
                    @csrf
                    <button type="submit" class="nut-xanh">Duyệt</button>
                </form>
                <form method="POST" action="{{ route('cau-hoi.tu-choi', $cauHoi->maCauHoi) }}" style="display:flex; gap:6px;">
                    @csrf
                    <input type="text" name="ly_do" placeholder="Lý do từ chối (không bắt buộc)" maxlength="500" style="width:260px;">
                    <button type="submit" class="nut-xam">Từ chối</button>
                </form>
            @endif

            @if (! $biKhoaSua)
                <a class="nut" href="{{ route('cau-hoi.sua', $cauHoi->maCauHoi) }}">Sửa</a>
            @endif

            @if (! $trongDeThi)
                <form method="POST" action="{{ route('cau-hoi.xoa', $cauHoi->maCauHoi) }}"
                      onsubmit="return confirm('Xóa câu hỏi #{{ $cauHoi->maCauHoi }}? Thao tác này không hoàn tác được.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="nut-do">Xóa</button>
                </form>
            @endif
        </div>
        @if ($biKhoaSua)
            <div class="ghi-chu">Câu hỏi nằm trong đề thi đã rời trạng thái Nháp nên bị khóa sửa (BR-15).</div>
        @elseif ($trongDeThi)
            <div class="ghi-chu">Câu hỏi đang nằm trong một đề thi nên không xóa được.</div>
        @endif

        @if ($cauHoi->dapAnTracNghiems->isNotEmpty())
            <h3>Phương án</h3>
            <ol type="A">
                @foreach ($cauHoi->dapAnTracNghiems as $da)
                    <li class="{{ $da->laDapAnDung ? 'dap-an-dung' : '' }}">{{ $da->noiDungDapAn }} @if ($da->laDapAnDung) &#10003; (đáp án đúng) @endif</li>
                @endforeach
            </ol>
        @endif

        @if ($cauHoi->dapAnTuLuanYs->isNotEmpty())
            <h3>Đáp án theo ý</h3>
            <table>
                <thead><tr><th>Ý</th><th>Nội dung</th><th>Điểm</th></tr></thead>
                <tbody>
                    @foreach ($cauHoi->dapAnTuLuanYs as $y)
                        <tr><td>{{ $y->thuTu }}</td><td>{{ $y->noiDungY }}</td><td>{{ rtrim(rtrim(number_format((float) $y->diemY, 2, ',', ''), '0'), ',') }}</td></tr>
                    @endforeach
                    <tr><th colspan="2">Tổng</th><th>{{ rtrim(rtrim(number_format((float) $cauHoi->dapAnTuLuanYs->sum('diemY'), 2, ',', ''), '0'), ',') }}</th></tr>
                </tbody>
            </table>
        @endif

        <h3>Kiểm tra trùng lặp</h3>
        @if ($trungLaps->isEmpty())
            <p class="ghi-chu">
                @if ($cauHoi->vectorId)
                    Đã kiểm tra, không có câu hỏi nào gần nghĩa trong cùng học phần để so sánh.
                @else
                    Câu hỏi chưa được kiểm tra trùng lặp (chạy lệnh <code>php artisan cau-hoi:kiem-tra-trung-lap</code>).
                @endif
            </p>
        @else
            <table>
                <thead><tr><th>Câu hỏi gần nghĩa nhất</th><th>Độ tương đồng</th><th>Kết quả</th><th>Ngưỡng áp dụng</th><th>Ngày kiểm tra</th></tr></thead>
                <tbody>
                    @foreach ($trungLaps as $kt)
                        @php
                            $khac = (int) $kt->maCauHoi === (int) $cauHoi->maCauHoi ? $kt->cauHoiTrung : $kt->cauHoi;
                        @endphp
                        <tr @if ($kt->trangThaiCanhBao === 'Cảnh báo trùng lặp') style="background:#fef9c3;" @endif>
                            <td>
                                @if ($khac)
                                    <a href="{{ route('cau-hoi.show', $khac->maCauHoi) }}">#{{ $khac->maCauHoi }}</a>
                                    {{ \Illuminate\Support\Str::limit($khac->noiDungCauHoi, 110) }}
                                @else
                                    (câu hỏi đã bị xóa)
                                @endif
                            </td>
                            <td>{{ number_format((float) $kt->doTuongDong * 100, 1, ',', '') }}%</td>
                            <td><strong>{{ $kt->trangThaiCanhBao }}</strong></td>
                            <td>{{ number_format((float) $kt->nguongApDung * 100, 0) }}%</td>
                            <td>{{ $kt->ngayKiemTra?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <h3>Đoạn tài liệu nguồn</h3>
        @if ($cauHoi->doanTaiLieu)
            <p class="ghi-chu">Tài liệu: {{ $cauHoi->doanTaiLieu->taiLieu?->tenTaiLieu }} (trang {{ $cauHoi->doanTaiLieu->viTriTrang }})</p>
            <blockquote style="margin:0; padding:10px 14px; background:#f9fafb; border-left:4px solid #93c5fd; font-size:14px; line-height:1.5;">{{ $cauHoi->doanTaiLieu->noiDungDoan }}</blockquote>
        @else
            <p class="ghi-chu">Không có liên kết tới đoạn nguồn.</p>
        @endif
    </div>
@endsection
