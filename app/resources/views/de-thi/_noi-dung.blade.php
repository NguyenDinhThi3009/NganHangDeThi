@php
    $dinhDang = fn ($d) => rtrim(rtrim(number_format((float) $d, 2, ',', ''), '0'), ',');
    $nhomHienTai = null;
@endphp

@foreach ($muc as $m)
    @php $c = $m['cauHoi']; @endphp

    @if ($hinhThuc === 'Vấn đáp' && $nhomHienTai !== $m['phan'])
        @php $nhomHienTai = $m['phan']; @endphp
        <h3 style="margin:22px 0 6px;">{{ $m['phan'] === 1 ? 'Phần 1 - Câu hỏi bắt buộc' : 'Phần 2 - Câu hỏi thêm' }}</h3>
    @endif

    <div style="border-top:1px solid #e5e7eb; padding:12px 0;">
        <div>
            <strong>Câu {{ $m['thuTu'] }}</strong>
            <span class="ghi-chu">({{ $dinhDang($m['diemSo']) }} điểm)</span>
            <span class="ghi-chu"> · #{{ $c->maCauHoi }} · {{ $c->clo?->kyHieu }} · {{ \App\Support\MucDoBloom::sangNhan($c->mucDoBloom) }} · {{ $c->doKho }}</span>
        </div>
        <div style="margin:6px 0; line-height:1.5;">{{ $c->noiDungCauHoi }}</div>

        @if ($c->loaiCauHoi === 'Trắc nghiệm')
            @foreach ($m['phuong_an'] as $i => $pa)
                <div @class(['dap-an-dung' => $hienDapAn && $pa->laDapAnDung]) style="margin-left:16px;">
                    {{ chr(65 + $i) }}. {{ $pa->noiDungDapAn }}@if ($hienDapAn && $pa->laDapAnDung) &nbsp;(đáp án đúng)@endif
                </div>
            @endforeach
        @elseif ($hienDapAn)
            <table style="margin-top:6px; width:auto; min-width:60%;">
                <thead><tr><th style="width:60px;">Ý</th><th>Đáp án</th><th style="width:80px;">Điểm</th></tr></thead>
                <tbody>
                    @foreach ($m['cac_y'] as $i => $y)
                        <tr><td>{{ $i + 1 }}</td><td>{{ $y['noi_dung'] }}</td><td>{{ $dinhDang($y['diem']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endforeach

<div style="border-top:2px solid #111827; padding-top:10px;"><strong>Tổng điểm: {{ $dinhDang($tongDiem) }}</strong> &nbsp;·&nbsp; {{ count($muc) }} câu</div>
