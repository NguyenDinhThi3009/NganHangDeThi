@extends('layouts.app')

@section('tieu_de', 'Chọn đề thi - Ngân hàng đề thi')

@section('noi_dung')
    <h2>Chọn đề thi cho ca thi</h2>

    <div class="the">
        @if ($errors->any())
            <div class="loi-khung"><ul style="margin:0 0 0 18px; padding:0;">@foreach ($errors->all() as $l)<li>{{ $l }}</li>@endforeach</ul></div>
        @endif
        @if (session('goi_y_ngan_hang'))
            <div class="canh-bao">
                <a href="{{ route('ngan-hang.show', session('goi_y_ngan_hang')) }}">Xem cơ cấu ngân hàng đề của học phần này</a>
                để biết còn thiếu bao nhiêu đề (UC-17).
            </div>
        @endif

        <form method="POST" action="{{ route('chon-de.chon') }}">
            @csrf
            <label for="hoc_phan">Học phần</label>
            <select name="hoc_phan" id="hoc_phan" required>
                <option value="">-- Chọn học phần --</option>
                @foreach ($hocPhans as $h)
                    <option value="{{ $h->maHocPhan }}" @selected(old('hoc_phan') === $h->maHocPhan)>{{ $h->maHocPhan }} - {{ $h->tenHocPhan }}</option>
                @endforeach
            </select>

            <label for="hinh_thuc">Hình thức thi</label>
            <select name="hinh_thuc" id="hinh_thuc" required>
                @foreach ($hinhThucs as $ht)
                    <option value="{{ $ht }}" @selected(old('hinh_thuc', 'Trắc nghiệm') === $ht)>{{ $ht }}</option>
                @endforeach
            </select>

            <label for="hoc_ky">Học kỳ</label>
            <select name="hoc_ky" id="hoc_ky" style="width:200px;">
                <option value="1" @selected(old('hoc_ky') === '1')>1</option>
                <option value="2" @selected(old('hoc_ky') === '2')>2</option>
                <option value="3" @selected(old('hoc_ky') === '3')>3 (học kỳ hè)</option>
            </select>

            <label for="nam_hoc">Năm học</label>
            <input type="text" name="nam_hoc" id="nam_hoc" value="{{ old('nam_hoc', $namHocMacDinh) }}" placeholder="2026-2027" style="width:200px;">

            <label for="ngay_thi">Ngày thi</label>
            <input type="date" name="ngay_thi" id="ngay_thi" value="{{ old('ngay_thi') }}" style="width:200px;" required>

            <div id="khung-ma-de">
                <label for="so_ma_de">Số mã đề (chỉ áp dụng cho đề trắc nghiệm)</label>
                <select name="so_ma_de" id="so_ma_de" style="width:200px;">
                    <option value="3" @selected(old('so_ma_de') === '3')>3 mã đề</option>
                    <option value="4" @selected(old('so_ma_de', '4') === '4')>4 mã đề</option>
                </select>
            </div>
            <div class="ghi-chu" style="margin-top:8px;">
                Hệ thống loại các đề đã dùng trong học kỳ này (BR-08), chọn ngẫu nhiên trong các đề đã duyệt còn lại và ghi nhận lần sử dụng.
                Đề được duyệt muộn hơn mốc 04 tuần trước ngày thi sẽ có cảnh báo (BR-13).
            </div>

            <div class="hang-nut"><button type="submit">Chọn ngẫu nhiên đề thi</button></div>
        </form>
        <script>
            (function () {
                var ht = document.getElementById('hinh_thuc');
                function cn() { document.getElementById('khung-ma-de').style.display = ht.value === 'Trắc nghiệm' ? '' : 'none'; }
                ht.addEventListener('change', cn); cn();
            })();
        </script>
    </div>

    <h3>Lịch sử chọn đề</h3>
    <form method="GET" action="{{ route('chon-de.index') }}" style="margin-bottom:10px;">
        <select name="hoc_phan" style="width:280px;" onchange="this.form.submit()">
            <option value="">Mọi học phần</option>
            @foreach ($hocPhans as $h)
                <option value="{{ $h->maHocPhan }}" @selected($locHocPhan === $h->maHocPhan)>{{ $h->maHocPhan }} - {{ $h->tenHocPhan }}</option>
            @endforeach
        </select>
    </form>
    <table>
        <thead><tr><th>Mã</th><th>Học phần</th><th>Đề</th><th>Hình thức</th><th>Học kỳ</th><th>Ngày thi</th><th>Mã đề</th></tr></thead>
        <tbody>
            @forelse ($lichSu as $s)
                <tr>
                    <td><a href="{{ route('chon-de.show', $s->maSuDung) }}">#{{ $s->maSuDung }}</a></td>
                    <td>{{ $s->deThi?->maTranDeThi?->maHocPhan }} - {{ $s->deThi?->maTranDeThi?->hocPhan?->tenHocPhan }}</td>
                    <td>{{ $s->deThi?->tenDeThi }}</td>
                    <td>{{ $s->deThi?->hinhThucThi }}</td>
                    <td>{{ $s->hocKy }} / {{ $s->namHoc }}</td>
                    <td>{{ $s->ngaySuDung?->format('d/m/Y') }}</td>
                    <td>{{ $s->ma_de_trac_nghiems_count ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="ghi-chu">Chưa có lần chọn đề nào.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
