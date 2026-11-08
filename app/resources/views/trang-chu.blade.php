@extends('layouts.app')

@section('tieu_de', 'Trang chủ - Ngân hàng đề thi')

@section('noi_dung')
    <div class="the">
        <h2>Xin chào, {{ auth()->user()->hoTen }}</h2>
        <p>Vai trò: <strong>{{ auth()->user()->vaiTro->tenVaiTro }}</strong></p>

        @if (in_array(auth()->user()->vaiTro->tenVaiTro, ['Giảng viên', 'Quản trị viên', 'Phòng ĐT&ĐBCLGD'], true))
            <p><a href="{{ route('tai-lieu.index') }}">Tài liệu học phần</a></p>
        @endif

        @if (auth()->user()->vaiTro->tenVaiTro === 'Quản trị viên')
            <p><a href="{{ route('quan-tri') }}">Khu vực Quản trị viên (thử phân quyền)</a></p>
        @endif
    </div>
@endsection
