@extends('layouts.app')

@section('tieu_de', 'Câu hỏi - Ngân hàng đề thi')

@if ($dangSinh)
    @push('head')
        {{-- Đang sinh nền: tự tải lại trang mỗi 10 giây --}}
        <meta http-equiv="refresh" content="10">
    @endpush
@endif

@section('noi_dung')
    @include('cau-hoi._trang-thai')

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
        <h2 style="margin:0;">Ngân hàng câu hỏi</h2>
        <a class="nut" href="{{ route('cau-hoi.sinh') }}">+ Sinh câu hỏi bằng AI</a>
    </div>

    {{-- Bộ lọc --}}
    <form method="GET" action="{{ route('cau-hoi.index') }}" style="display:flex; gap:10px; align-items:center; margin-bottom:14px;">
        <select name="trang_thai" style="width:180px;" onchange="this.form.submit()">
            <option value="">Mọi trạng thái</option>
            @foreach (['Nháp', 'Đã duyệt', 'Từ chối'] as $tt)
                <option value="{{ $tt }}" @selected($locTrangThai === $tt)>{{ $tt }}</option>
            @endforeach
        </select>
        <select name="hoc_phan" style="width:280px;" onchange="this.form.submit()">
            <option value="">Mọi học phần</option>
            @foreach ($hocPhans as $hp)
                <option value="{{ $hp->maHocPhan }}" @selected($locHocPhan === $hp->maHocPhan)>{{ $hp->maHocPhan }} - {{ $hp->tenHocPhan }}</option>
            @endforeach
        </select>
        @if ($locTrangThai || $locHocPhan)
            <a href="{{ route('cau-hoi.index') }}">Bỏ lọc</a>
        @endif
    </form>

    <form method="POST" action="{{ route('cau-hoi.xoa-nhieu') }}"
          onsubmit="return confirm('Xóa các câu hỏi đã chọn? Thao tác này không hoàn tác được.');">
        @csrf
        <table>
            <thead>
                <tr>
                    <th style="width:28px;"><input type="checkbox" title="Chọn tất cả" style="width:auto;"
                        onclick="document.querySelectorAll('.chon-cau-hoi').forEach(function (c) { c.checked = this.checked; }, this)"></th>
                    <th>Mã</th>
                    <th>Nội dung</th>
                    <th>Loại</th>
                    <th>Bloom</th>
                    <th>Độ khó</th>
                    <th>CLO</th>
                    <th>Học phần</th>
                    <th>Trạng thái</th>
                    <th>Trùng lặp</th>
                    <th>Người tạo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cauHois as $ch)
                    <tr>
                        <td><input type="checkbox" class="chon-cau-hoi" name="ids[]" value="{{ $ch->maCauHoi }}" style="width:auto;"></td>
                        <td><a href="{{ route('cau-hoi.show', $ch->maCauHoi) }}">#{{ $ch->maCauHoi }}</a></td>
                        <td>{{ \Illuminate\Support\Str::limit($ch->noiDungCauHoi, 90) }}</td>
                        <td>{{ $ch->loaiCauHoi }}</td>
                        <td>{{ \App\Support\MucDoBloom::sangNhan($ch->mucDoBloom) }}</td>
                        <td>{{ $ch->doKho }}</td>
                        <td>{{ $ch->clo?->kyHieu }}</td>
                        <td>{{ $ch->maHocPhan }}</td>
                        <td>{{ $ch->trangThaiDuyet }}</td>
                        <td>
                            @if (isset($canhBaoTrung[(int) $ch->maCauHoi]))
                                <span style="color:#b45309; font-weight:bold;" title="Có thể trùng với câu hỏi khác">&#9888; {{ number_format($canhBaoTrung[(int) $ch->maCauHoi] * 100, 0) }}%</span>
                            @endif
                        </td>
                        <td>{{ $ch->nguoiTao?->hoTen }}</td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="ghi-chu">Chưa có câu hỏi nào.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($cauHois->isNotEmpty())
            <div style="margin-top:12px;">
                <button type="submit" class="nut-do">Xóa các câu đã chọn</button>
                <span class="ghi-chu" style="margin-left:10px;">Chỉ xóa được câu Nháp hoặc Từ chối của bạn, không nằm trong đề thi.</span>
            </div>
        @endif
    </form>
@endsection
