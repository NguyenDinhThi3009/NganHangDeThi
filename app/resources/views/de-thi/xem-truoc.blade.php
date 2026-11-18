@extends('layouts.app')

@section('tieu_de', 'Xem trước đề thi - Ngân hàng đề thi')

@section('noi_dung')
    <p><a href="{{ route('de-thi.create', ['ma_tran' => $maTran->maMaTranDeThi]) }}">&larr; Quay lại tạo đề</a></p>

    <div class="the">
        <h2>Bản nháp: {{ $nhap['ten'] }}</h2>
        <div class="canh-bao">Đây là bản nháp, chưa được lưu. Hãy xem lại rồi chọn lưu, gửi phản biện, tạo lại hoặc hủy.</div>

        @if ($errors->any())
            <div class="loi-khung">
                <ul style="margin:0 0 0 18px; padding:0;">
                    @foreach ($errors->all() as $loi)
                        <li>{{ $loi }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @foreach ($nhap['canh_bao'] as $cb)
            <div class="canh-bao">{{ $cb }}</div>
        @endforeach

        <table style="margin:12px 0;">
            <tr><th style="width:200px;">Học phần</th><td>{{ $maTran->maHocPhan }} - {{ $maTran->hocPhan?->tenHocPhan }}</td></tr>
            <tr><th>Ma trận</th><td><a href="{{ route('ma-tran.show', $maTran->maMaTranDeThi) }}">#{{ $maTran->maMaTranDeThi }} - {{ $maTran->tenMaTran }}</a></td></tr>
            <tr><th>Hình thức thi</th><td>{{ $nhap['hinh_thuc'] }}</td></tr>
            @if ($nhap['hinh_thuc'] === 'Vấn đáp')
                <tr><th>Thời gian</th><td>Chuẩn bị {{ $nhap['thoi_gian']['chuan_bi'] }} phút, trình bày {{ $nhap['thoi_gian']['tra_loi'] }} phút</td></tr>
            @else
                <tr><th>Thời gian làm bài</th><td>{{ $nhap['thoi_gian']['lam_bai'] }} phút</td></tr>
            @endif
        </table>

        <div class="hang-nut">
            <form method="POST" action="{{ route('de-thi.luu', $token) }}">
                @csrf
                <input type="hidden" name="hanh_dong" value="luu">
                <button type="submit" class="nut-xanh">Lưu đề (Nháp)</button>
            </form>
            <form method="POST" action="{{ route('de-thi.luu', $token) }}"
                  onsubmit="return confirm('Lưu và gửi phản biện? Sau khi gửi, các câu hỏi trong đề bị khóa sửa.');">
                @csrf
                <input type="hidden" name="hanh_dong" value="gui">
                <button type="submit">Lưu và gửi phản biện</button>
            </form>
            <form method="POST" action="{{ route('de-thi.tao-lai', $token) }}">
                @csrf
                <button type="submit" class="nut-xam">Tạo lại (chọn câu khác)</button>
            </form>
            <form method="POST" action="{{ route('de-thi.huy-nhap', $token) }}">
                @csrf
                <button type="submit" class="nut-do">Hủy</button>
            </form>
        </div>

        <h3>Nội dung đề và đáp án</h3>
        @include('de-thi._noi-dung', ['muc' => $muc, 'hinhThuc' => $nhap['hinh_thuc'], 'tongDiem' => $tongDiem, 'hienDapAn' => true])
    </div>
@endsection
