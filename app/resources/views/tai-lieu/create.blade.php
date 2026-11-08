@extends('layouts.app')

@section('tieu_de', 'Tải tài liệu lên - Ngân hàng đề thi')

@section('noi_dung')
    <div class="the" style="max-width:640px;">
        <h2>Tải tài liệu lên</h2>

        <form method="POST" action="{{ route('tai-lieu.store') }}" enctype="multipart/form-data">
            @csrf

            <label for="loai_tai_lieu">Loại tài liệu</label>
            <select id="loai_tai_lieu" name="loai_tai_lieu" required>
                <option value="">-- Chọn loại --</option>
                @foreach ($loaiTaiLieus as $loai)
                    @continue($loai === 'Tài liệu chung' && ! $duocTaiChung)
                    <option value="{{ $loai }}" @selected(old('loai_tai_lieu') === $loai)>{{ $loai }}</option>
                @endforeach
            </select>
            @error('loai_tai_lieu') <div class="loi">{{ $message }}</div> @enderror

            <label for="ma_hoc_phan">Học phần</label>
            <select id="ma_hoc_phan" name="ma_hoc_phan">
                <option value="">-- Chọn học phần --</option>
                @foreach ($hocPhans as $hp)
                    <option value="{{ $hp->maHocPhan }}" @selected(old('ma_hoc_phan') === $hp->maHocPhan)>{{ $hp->maHocPhan }} - {{ $hp->tenHocPhan }}</option>
                @endforeach
            </select>
            <div class="ghi-chu">Bắt buộc chọn học phần, trừ loại "Tài liệu chung".</div>
            @error('ma_hoc_phan') <div class="loi">{{ $message }}</div> @enderror

            <label for="ten_tai_lieu">Tên tài liệu (để trống sẽ lấy tên file)</label>
            <input type="text" id="ten_tai_lieu" name="ten_tai_lieu" value="{{ old('ten_tai_lieu') }}" maxlength="255">
            @error('ten_tai_lieu') <div class="loi">{{ $message }}</div> @enderror

            <label for="file">File (PDF, DOCX hoặc PPTX, tối đa 50 MB)</label>
            <input type="file" id="file" name="file" accept=".pdf,.docx,.pptx" required>
            @error('file') <div class="loi">{{ $message }}</div> @enderror

            <div style="margin-top:20px;">
                <button type="submit">Tải lên</button>
                <a href="{{ route('tai-lieu.index') }}" style="margin-left:12px;">Hủy</a>
            </div>
        </form>
    </div>
@endsection
