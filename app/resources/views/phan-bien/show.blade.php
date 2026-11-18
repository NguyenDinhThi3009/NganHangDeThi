@extends('layouts.app')

@section('tieu_de', 'Phản biện đề #'.$deThi->maDeThi.' - Ngân hàng đề thi')

@section('noi_dung')
    <p><a href="{{ route('phan-bien.index') }}">&larr; Danh sách phản biện</a></p>

    <div class="the">
        <h2>{{ $deThi->tenDeThi }}</h2>
        @include('de-thi._thong-tin')

        @if ($duocPhanBien)
            <form method="POST" action="{{ route('phan-bien.ghi', $deThi->maDeThi) }}" style="border:1px solid #d1d5db; padding:14px; border-radius:6px; margin:14px 0;"
                  onsubmit="return confirm('Ghi kết quả phản biện? Thao tác này chuyển trạng thái của đề.');">
                @csrf
                <strong>Kết quả phản biện</strong>
                <label><input type="radio" name="ket_qua" value="Đạt" @checked(old('ket_qua') === 'Đạt') style="width:auto;"> Đạt (chuyển Trưởng bộ môn phê duyệt)</label>
                <label><input type="radio" name="ket_qua" value="Cần chỉnh sửa" @checked(old('ket_qua') === 'Cần chỉnh sửa') style="width:auto;"> Cần chỉnh sửa (trả về giảng viên biên soạn)</label>
                @error('ket_qua')<div style="color:#b91c1c;">{{ $message }}</div>@enderror
                <label for="nhan_xet">Nhận xét (bắt buộc khi cần chỉnh sửa)</label>
                <textarea name="nhan_xet" id="nhan_xet" rows="4" maxlength="2000">{{ old('nhan_xet') }}</textarea>
                @error('nhan_xet')<div style="color:#b91c1c;">{{ $message }}</div>@enderror
                <div class="hang-nut"><button type="submit">Ghi kết quả</button></div>
            </form>
        @endif

        @include('de-thi._doi-chieu')

        <h3>Nội dung đề, đáp án và thang điểm</h3>
        @include('de-thi._noi-dung', ['muc' => $muc, 'hinhThuc' => $deThi->hinhThucThi, 'tongDiem' => $tongDiem, 'hienDapAn' => true])
    </div>
@endsection
