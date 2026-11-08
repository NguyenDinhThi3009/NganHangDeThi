@extends('layouts.app')

@section('tieu_de', 'Tài liệu - Ngân hàng đề thi')

@if ($dangXuLy)
    @push('head')
        {{-- Có tài liệu đang xử lý nền: tự tải lại trang mỗi 10 giây --}}
        <meta http-equiv="refresh" content="10">
    @endpush
@endif

@section('noi_dung')
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
        <h2 style="margin:0;">Tài liệu học phần</h2>
        <a class="nut" href="{{ route('tai-lieu.create') }}">+ Tải tài liệu lên</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tên tài liệu</th>
                <th>Loại</th>
                <th>Học phần</th>
                <th>Định dạng</th>
                <th>Trạng thái</th>
                <th>Số đoạn</th>
                <th>Người tải</th>
                <th>Ngày tải</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($taiLieus as $tl)
                <tr>
                    <td>{{ $tl->tenTaiLieu }}</td>
                    <td>{{ $tl->loaiTaiLieu }}</td>
                    <td>{{ $tl->hocPhan ? $tl->maHocPhan.' - '.$tl->hocPhan->tenHocPhan : '(Tài liệu chung)' }}</td>
                    <td>{{ $tl->dinhDang }}</td>
                    <td>{{ $tl->trangThaiXuLy }}</td>
                    <td>{{ $tl->loaiTaiLieu === 'Đề cương chi tiết' ? '-' : $tl->doan_tai_lieus_count }}</td>
                    <td>{{ $tl->nguoiTaiLen?->hoTen }}</td>
                    <td>{{ $tl->ngayTaiLen?->format('d/m/Y H:i') }}</td>
                    <td>
                        @if ($laQuanTri && $tl->trangThaiXuLy === 'Lỗi')
                            <form method="POST" action="{{ route('tai-lieu.xu-ly-lai', $tl->maTaiLieu) }}">
                                @csrf
                                <button type="submit" class="nut-nho">Xử lý lại</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="ghi-chu">Chưa có tài liệu nào.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection