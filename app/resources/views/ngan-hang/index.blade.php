@extends('layouts.app')

@section('tieu_de', 'Cơ cấu ngân hàng đề thi - Ngân hàng đề thi')

@section('noi_dung')
    <h2>Cơ cấu ngân hàng đề thi</h2>
    <p class="ghi-chu">Số đề đã duyệt của từng học phần so với mức tối thiểu quy định:
        @foreach ($toiThieu as $ht => $min) {{ $ht }} {{ $min }} đề{{ ! $loop->last ? ',' : '.' }} @endforeach
    </p>

    <table>
        <thead>
            <tr>
                <th>Học phần</th>
                @foreach ($toiThieu as $ht => $min)<th>{{ $ht }} (cần {{ $min }})</th>@endforeach
                <th>Tình trạng</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($hang as $h)
                <tr>
                    <td><a href="{{ route('ngan-hang.show', $h['hoc_phan']->maHocPhan) }}">{{ $h['hoc_phan']->maHocPhan }} - {{ $h['hoc_phan']->tenHocPhan }}</a></td>
                    @foreach ($h['theo_hinh_thuc'] as $x)
                        <td @if ($x['thieu'] > 0) style="color:#b91c1c;" @endif>{{ $x['co'] }}@if ($x['thieu'] > 0) (thiếu {{ $x['thieu'] }})@endif</td>
                    @endforeach
                    <td>
                        @if (! $h['co_de']) <span style="color:#b91c1c;">Chưa có ngân hàng đề thi</span>
                        @elseif ($h['tong_thieu'] > 0) Còn thiếu đề
                        @else Đủ @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
