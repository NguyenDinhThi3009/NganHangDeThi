@extends('layouts.app')

@section('tieu_de', 'Học phần - Ngân hàng đề thi')

@section('noi_dung')
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
        <h2 style="margin:0;">Học phần</h2>
        <a class="nut" href="{{ route('hoc-phan.nhap') }}">+ Nhập từ đề cương chi tiết</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Mã học phần</th>
                <th>Tên học phần</th>
                <th>Tín chỉ (LT / TH)</th>
                <th>Học kỳ</th>
                <th>Bộ môn</th>
                <th>Chương trình đào tạo</th>
                <th>Số CLO</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($hocPhans as $hp)
                <tr>
                    <td>{{ $hp->maHocPhan }}</td>
                    <td>{{ $hp->tenHocPhan }}</td>
                    <td>{{ $hp->soTinChi }} ({{ $hp->soTinChiLT }} / {{ $hp->soTinChiTH }})</td>
                    <td>{{ $hp->hocKyDuKien > 0 ? $hp->hocKyDuKien : '(chưa nhập)' }}</td>
                    <td>{{ $hp->boMon?->tenBoMon }}</td>
                    <td>{{ $hp->chuongTrinhDaoTao?->tenChuongTrinhDaoTao }}</td>
                    <td>{{ $hp->clos_count }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="ghi-chu">Chưa có học phần nào.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
