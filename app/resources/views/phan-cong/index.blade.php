@extends('layouts.app')

@section('tieu_de', 'Phân công học phần - Ngân hàng đề thi')

@section('noi_dung')
    <h2>Phân công học phần</h2>

    @if ($khongCoHocPhan)
        <div class="canh-bao">Chưa có học phần nào thuộc bộ môn của bạn. Quản trị viên cần gán học phần cho bộ môn (menu Học phần) và gán bộ môn cho tài khoản của bạn.</div>
    @else
        <div class="the">
            <h3 style="margin-top:0;">Thêm phân công</h3>
            @if ($errors->any())
                <div class="loi-khung"><ul style="margin:0 0 0 18px; padding:0;">@foreach ($errors->all() as $l)<li>{{ $l }}</li>@endforeach</ul></div>
            @endif
            <form method="POST" action="{{ route('phan-cong.store') }}">
                @csrf
                <label for="ma_hoc_phan">Học phần</label>
                <select name="ma_hoc_phan" id="ma_hoc_phan" required>
                    <option value="">-- Chọn học phần --</option>
                    @foreach ($hocPhans as $h)
                        <option value="{{ $h->maHocPhan }}" @selected(old('ma_hoc_phan') === $h->maHocPhan)>{{ $h->maHocPhan }} - {{ $h->tenHocPhan }}</option>
                    @endforeach
                </select>

                <label for="ma_nguoi_dung">Giảng viên</label>
                <select name="ma_nguoi_dung" id="ma_nguoi_dung" required>
                    <option value="">-- Chọn giảng viên --</option>
                    @foreach ($nguoiDungs as $n)
                        <option value="{{ $n->maNguoiDung }}" @selected((string) old('ma_nguoi_dung') === (string) $n->maNguoiDung)>
                            {{ $n->hoTen }} ({{ $n->trinhDoHocVi ?: 'chưa khai báo học vị' }}{{ $n->boMon ? ', '.$n->boMon->tenBoMon : '' }})
                        </option>
                    @endforeach
                </select>
                <div class="ghi-chu">Người được phân công phải có trình độ thạc sĩ trở lên (BR-10). Thâm niên giảng dạy và chuyên ngành do Trưởng bộ môn tự xác nhận.</div>

                <label for="vai_tro">Vai trò trong học phần</label>
                <select name="vai_tro" id="vai_tro" required>
                    @foreach ($vaiTros as $v)
                        <option value="{{ $v }}" @selected(old('vai_tro') === $v)>{{ $v }}</option>
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

                <div class="hang-nut"><button type="submit">Phân công</button></div>
            </form>
        </div>
    @endif

    <div style="display:flex; gap:12px; align-items:center; margin:18px 0 10px;">
        <h3 style="margin:0;">Danh sách phân công</h3>
        <a href="{{ route('phan-cong.index', ['trang_thai' => 'Đang hiệu lực']) }}" @if ($locTrangThai === 'Đang hiệu lực') style="font-weight:bold;" @endif>Đang hiệu lực</a>
        <a href="{{ route('phan-cong.index', ['trang_thai' => 'Kết thúc']) }}" @if ($locTrangThai === 'Kết thúc') style="font-weight:bold;" @endif>Kết thúc</a>
    </div>
    <table>
        <thead><tr><th>Học phần</th><th>Giảng viên</th><th>Vai trò</th><th>Học kỳ</th><th>Năm học</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody>
            @forelse ($phanCongs as $p)
                <tr>
                    <td>{{ $p->maHocPhan }} - {{ $p->hocPhan?->tenHocPhan }}</td>
                    <td>{{ $p->nguoiDung?->hoTen }}</td>
                    <td>{{ $p->vaiTroPhanCong }}</td>
                    <td>{{ $p->hocKy }}</td>
                    <td>{{ $p->namHoc }}</td>
                    <td>{{ $p->trangThai }}</td>
                    <td>
                        @if ($p->trangThai === 'Đang hiệu lực')
                            <form method="POST" action="{{ route('phan-cong.ket-thuc', $p->maPhanCong) }}" onsubmit="return confirm('Kết thúc phân công này?');">
                                @csrf
                                <button type="submit" class="nut-nho">Kết thúc</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="ghi-chu">Chưa có phân công nào.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
