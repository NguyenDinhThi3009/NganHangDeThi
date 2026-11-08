@extends('layouts.app')

@section('tieu_de', 'Ma trận đề thi - Ngân hàng đề thi')

@section('noi_dung')
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
        <h2 style="margin:0;">Ma trận đề thi</h2>
        <a class="nut" href="{{ route('ma-tran.create') }}">+ Tạo ma trận</a>
    </div>

    <form method="GET" action="{{ route('ma-tran.index') }}" style="display:flex; gap:10px; align-items:center; margin-bottom:14px;">
        <select name="trang_thai" style="width:180px;" onchange="this.form.submit()">
            <option value="">Mọi trạng thái</option>
            @foreach (['Nháp', 'Đã chốt'] as $tt)
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
            <a href="{{ route('ma-tran.index') }}">Bỏ lọc</a>
        @endif
    </form>

    <table>
        <thead>
            <tr>
                <th>Mã</th>
                <th>Tên ma trận</th>
                <th>Học phần</th>
                <th>Tổng số câu</th>
                <th>Ngưỡng trùng lặp</th>
                <th>Trạng thái</th>
                <th>Người tạo</th>
                <th>Ngày tạo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($maTrans as $mt)
                <tr>
                    <td><a href="{{ route('ma-tran.show', $mt->maMaTranDeThi) }}">#{{ $mt->maMaTranDeThi }}</a></td>
                    <td><a href="{{ route('ma-tran.show', $mt->maMaTranDeThi) }}">{{ $mt->tenMaTran }}</a></td>
                    <td>{{ $mt->maHocPhan }} - {{ $mt->hocPhan?->tenHocPhan }}</td>
                    <td>{{ (int) $mt->tong_so_cau }}</td>
                    <td>{{ number_format((float) $mt->nguongTrungLap * 100, 0) }}%</td>
                    <td>{{ $mt->trangThai }}</td>
                    <td>{{ $mt->nguoiTao?->hoTen }}</td>
                    <td>{{ $mt->ngayTao?->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="ghi-chu">Chưa có ma trận nào.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
