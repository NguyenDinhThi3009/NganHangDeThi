@extends('layouts.app')

@section('tieu_de', 'Sinh câu hỏi - Ngân hàng đề thi')

@section('noi_dung')
    @include('cau-hoi._trang-thai')

    <div class="the" style="max-width:720px;">
        <h2>Sinh câu hỏi bằng AI</h2>

        {{-- Bước 1: chọn học phần (tải lại trang để lấy CLO và tài liệu của học phần đó) --}}
        <form method="GET" action="{{ route('cau-hoi.sinh') }}">
            <label for="hoc_phan">Học phần</label>
            <select id="hoc_phan" name="hoc_phan" onchange="this.form.submit()">
                <option value="">-- Chọn học phần --</option>
                @foreach ($hocPhans as $hp)
                    <option value="{{ $hp->maHocPhan }}" @selected($hocPhanChon?->maHocPhan === $hp->maHocPhan)>{{ $hp->maHocPhan }} - {{ $hp->tenHocPhan }}</option>
                @endforeach
            </select>
        </form>

        @if ($hocPhanChon)
            @if ($errors->any())
                <div class="loi-khung" style="margin-top:16px;">
                    <ul style="margin:0; padding-left:18px;">
                        @foreach ($errors->all() as $loi)
                            <li>{{ $loi }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('cau-hoi.bat-dau-sinh') }}">
                @csrf
                <input type="hidden" name="ma_hoc_phan" value="{{ $hocPhanChon->maHocPhan }}">

                <label for="ma_clo">Chuẩn đầu ra học phần (CLO)</label>
                <select id="ma_clo" name="ma_clo" required>
                    <option value="">-- Chọn CLO --</option>
                    @foreach ($clos as $clo)
                        <option value="{{ $clo->maClo }}" @selected((string) old('ma_clo') === (string) $clo->maClo)>{{ $clo->kyHieu }} - {{ \Illuminate\Support\Str::limit($clo->moTa, 110) }}</option>
                    @endforeach
                </select>
                @if ($clos->isEmpty())
                    <div class="loi">Học phần này chưa có CLO nào.</div>
                @endif

                <label for="loai_cau_hoi">Loại câu hỏi</label>
                <select id="loai_cau_hoi" name="loai_cau_hoi" required>
                    @foreach ($loaiCauHoi as $loai)
                        <option value="{{ $loai }}" @selected(old('loai_cau_hoi') === $loai)>{{ $loai }}</option>
                    @endforeach
                </select>

                <label for="muc_do_bloom">Mức độ Bloom</label>
                <select id="muc_do_bloom" name="muc_do_bloom" required>
                    @foreach ($bloom as $b)
                        <option value="{{ $b }}" @selected(old('muc_do_bloom', 'Hiểu') === $b)>{{ $b }}</option>
                    @endforeach
                </select>

                <label for="do_kho">Độ khó</label>
                <select id="do_kho" name="do_kho" required>
                    @foreach ($doKho as $d)
                        <option value="{{ $d }}" @selected(old('do_kho', 'Trung bình') === $d)>{{ $d }}</option>
                    @endforeach
                </select>

                <label for="so_luong">Số câu cần sinh (1 - 10)</label>
                <input type="text" id="so_luong" name="so_luong" value="{{ old('so_luong', 3) }}" required>

                <label for="diem_cau">Điểm của mỗi câu tự luận (mỗi ý tối đa 0,5 điểm)</label>
                <input type="text" id="diem_cau" name="diem_cau" value="{{ old('diem_cau', 2) }}">
                <div class="ghi-chu">Chỉ áp dụng cho câu tự luận. Phải là bội số của 0,5 (ví dụ 2 hoặc 3).</div>

                <label>Tài liệu làm nguồn</label>
                @forelse ($taiLieus as $tl)
                    <div>
                        <label style="font-weight:normal; margin:4px 0;">
                            <input type="checkbox" name="tai_lieu[]" value="{{ $tl->maTaiLieu }}"
                                   @checked(in_array($tl->maTaiLieu, (array) old('tai_lieu', $taiLieus->pluck('maTaiLieu')->all())))>
                            {{ $tl->tenTaiLieu }} <span class="ghi-chu">({{ $tl->loaiTaiLieu }})</span>
                        </label>
                    </div>
                @empty
                    <div class="loi">Chưa có tài liệu nào "Sẵn sàng sử dụng" cho học phần này (hãy tải giáo trình/slide ở mục Tài liệu).</div>
                @endforelse

                <div style="margin-top:20px;">
                    <button type="submit" @disabled($taiLieus->isEmpty() || $clos->isEmpty())>Sinh câu hỏi</button>
                    <a href="{{ route('cau-hoi.index') }}" style="margin-left:12px;">Xem danh sách câu hỏi</a>
                </div>
            </form>
        @endif
    </div>
@endsection
