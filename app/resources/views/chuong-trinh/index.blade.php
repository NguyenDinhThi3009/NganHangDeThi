@extends('layouts.app')

@section('tieu_de', 'Chương trình đào tạo - Ngân hàng đề thi')

@section('noi_dung')
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
        <h2 style="margin:0;">Chương trình đào tạo</h2>
        <div style="display:flex; gap:10px;">
            <a class="nut nut-xam" href="{{ route('ban-mo-ta.nhap') }}">Nhập từ Bản mô tả (PDF)</a>
            <a class="nut" href="{{ route('chuong-trinh.create') }}">+ Thêm chương trình</a>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tên chương trình</th>
                <th>Tên tiếng Anh</th>
                <th>Trình độ</th>
                <th>Tổng tín chỉ</th>
                <th>Khoa quản lý</th>
                <th>Khóa học</th>
                <th>Số PLO</th>
                <th>Số học phần</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ctdts as $ct)
                <tr>
                    <td>{{ $ct->tenChuongTrinhDaoTao }}</td>
                    <td>{{ $ct->tenTiengAnh }}</td>
                    <td>{{ $ct->trinhDo }}</td>
                    <td>{{ $ct->tongSoTinChi }}</td>
                    <td>{{ $ct->khoaQuanLy }}</td>
                    <td>{{ $ct->khoaHoc }}</td>
                    <td>{{ $ct->plos_count }}</td>
                    <td>{{ $ct->hoc_phans_count }}</td>
                    <td style="white-space:nowrap;">
                        <a href="{{ route('chuong-trinh.edit', $ct->maChuongTrinhDaoTao) }}">Sửa</a>
                        @if ($ct->hoc_phans_count === 0)
                            <form method="POST" action="{{ route('chuong-trinh.destroy', $ct->maChuongTrinhDaoTao) }}" style="display:inline;"
                                  onsubmit="return confirm('Xóa chương trình này cùng các PLO của nó?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="nut-nho nut-do">Xóa</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="ghi-chu">Chưa có chương trình đào tạo nào.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
