@extends('layouts.app')

@php
    $dangSua = $ctdt->exists;
@endphp

@section('tieu_de', ($dangSua ? 'Sửa' : 'Thêm').' chương trình đào tạo - Ngân hàng đề thi')

@section('noi_dung')
    <p><a href="{{ route('chuong-trinh.index') }}">&larr; Danh sách chương trình đào tạo</a></p>

    <div class="the">
        <h2>{{ $dangSua ? 'Sửa chương trình đào tạo' : 'Thêm chương trình đào tạo' }}</h2>

        @if ($errors->any())
            <div class="loi-khung">
                <ul style="margin:0; padding-left:18px;">
                    @foreach ($errors->all() as $loi)
                        <li>{{ $loi }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $dangSua ? route('chuong-trinh.update', $ctdt->maChuongTrinhDaoTao) : route('chuong-trinh.store') }}">
            @csrf
            @if ($dangSua)
                @method('PUT')
            @endif

            <h3>Thông tin chung</h3>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0 18px;">
                <div>
                    <label for="ten_chuong_trinh">Tên chương trình đào tạo (tiếng Việt)</label>
                    <input type="text" id="ten_chuong_trinh" name="ten_chuong_trinh" value="{{ old('ten_chuong_trinh', $ctdt->tenChuongTrinhDaoTao) }}" maxlength="255" required>
                </div>
                <div>
                    <label for="ten_tieng_anh">Tên tiếng Anh</label>
                    <input type="text" id="ten_tieng_anh" name="ten_tieng_anh" value="{{ old('ten_tieng_anh', $ctdt->tenTiengAnh) }}" maxlength="255">
                </div>
            </div>
            <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:0 18px;">
                <div>
                    <label for="trinh_do">Trình độ đào tạo</label>
                    <input type="text" id="trinh_do" name="trinh_do" value="{{ old('trinh_do', $ctdt->trinhDo ?? 'Đại học') }}" maxlength="50" required>
                </div>
                <div>
                    <label for="tong_so_tin_chi">Tổng số tín chỉ</label>
                    <input type="text" id="tong_so_tin_chi" name="tong_so_tin_chi" value="{{ old('tong_so_tin_chi', $ctdt->tongSoTinChi) }}" required>
                </div>
                <div>
                    <label for="khoa_quan_ly">Khoa quản lý</label>
                    <input type="text" id="khoa_quan_ly" name="khoa_quan_ly" value="{{ old('khoa_quan_ly', $ctdt->khoaQuanLy) }}" maxlength="100" required>
                </div>
                <div>
                    <label for="khoa_hoc">Khóa học áp dụng</label>
                    <input type="text" id="khoa_hoc" name="khoa_hoc" value="{{ old('khoa_hoc', $ctdt->khoaHoc) }}" maxlength="20" required>
                </div>
            </div>

            <h3>Chuẩn đầu ra chương trình (PLO)</h3>
            <p class="ghi-chu" style="line-height:1.5;">
                Ký hiệu có dạng PLO1, PLO2... Để trống cả ký hiệu và nội dung của một dòng sẽ bỏ dòng đó
                (với PLO đã có thì là xóa, chỉ xóa được khi chưa có học phần hoặc CLO nào dùng PLO đó).
            </p>
            <table>
                <thead><tr><th style="width:100px;">Ký hiệu</th><th>Nội dung PLO</th></tr></thead>
                <tbody>
                    @for ($i = 0; $i < $soDong; $i++)
                        @php
                            $plo = $plos[$i] ?? null;
                        @endphp
                        <tr>
                            <td>
                                <input type="hidden" name="plo_ma[{{ $i }}]" value="{{ old('plo_ma.'.$i, $plo?->maPlo) }}">
                                <input type="text" name="plo_ky_hieu[{{ $i }}]" value="{{ old('plo_ky_hieu.'.$i, $plo?->kyHieu) }}" maxlength="10" placeholder="PLO{{ $i + 1 }}">
                            </td>
                            <td><textarea name="plo_mo_ta[{{ $i }}]" rows="2">{{ old('plo_mo_ta.'.$i, $plo?->moTa) }}</textarea></td>
                        </tr>
                    @endfor
                </tbody>
            </table>

            <div style="margin-top:22px;">
                <button type="submit" class="nut-xanh">Lưu</button>
                <a href="{{ route('chuong-trinh.index') }}" style="margin-left:12px;">Hủy</a>
            </div>
        </form>
    </div>
@endsection
