@extends('layouts.app')

@php
    $dangSua = $maTran->exists;
@endphp

@section('tieu_de', ($dangSua ? 'Sửa' : 'Tạo').' ma trận đề thi - Ngân hàng đề thi')

@section('noi_dung')
    <p><a href="{{ $dangSua ? route('ma-tran.show', $maTran->maMaTranDeThi) : route('ma-tran.index') }}">&larr; Quay lại</a></p>

    <div class="the">
        <h2>{{ $dangSua ? 'Sửa ma trận đề thi #'.$maTran->maMaTranDeThi : 'Tạo ma trận đề thi' }}</h2>

        @if (! $dangSua)
            {{-- Bước 1: chọn học phần (tải lại trang để lấy CLO của học phần đó) --}}
            <form method="GET" action="{{ route('ma-tran.create') }}">
                <label for="hoc_phan_chon">Học phần</label>
                <select id="hoc_phan_chon" name="hoc_phan" onchange="this.form.submit()">
                    <option value="">-- Chọn học phần --</option>
                    @foreach ($hocPhans as $hp)
                        <option value="{{ $hp->maHocPhan }}" @selected($hocPhan?->maHocPhan === $hp->maHocPhan)>{{ $hp->maHocPhan }} - {{ $hp->tenHocPhan }}</option>
                    @endforeach
                </select>
            </form>
        @else
            <p>Học phần: <strong>{{ $hocPhan?->maHocPhan }} - {{ $hocPhan?->tenHocPhan }}</strong></p>
        @endif

        @if ($hocPhan)
            @if ($errors->any())
                <div class="loi-khung" style="margin-top:16px;">
                    <ul style="margin:0; padding-left:18px;">
                        @foreach ($errors->all() as $loi)
                            <li>{{ $loi }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($clos->isEmpty())
                <p class="loi">Học phần này chưa có CLO nào. Hãy nhập đề cương của học phần (menu Học phần) trước.</p>
            @else
                <form method="POST" action="{{ $dangSua ? route('ma-tran.update', $maTran->maMaTranDeThi) : route('ma-tran.store') }}">
                    @csrf
                    @if ($dangSua)
                        @method('PUT')
                    @else
                        <input type="hidden" name="ma_hoc_phan" value="{{ $hocPhan->maHocPhan }}">
                    @endif

                    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:0 18px;">
                        <div>
                            <label for="ten_ma_tran">Tên ma trận</label>
                            <input type="text" id="ten_ma_tran" name="ten_ma_tran" value="{{ old('ten_ma_tran', $maTran->tenMaTran) }}" maxlength="255" placeholder="Ví dụ: Ma trận thi cuối kỳ học kỳ 1" required>
                        </div>
                        <div>
                            <label for="nguong">Ngưỡng cảnh báo trùng lặp (%)</label>
                            <input type="text" id="nguong" name="nguong" value="{{ old('nguong', $nguong) }}" required>
                        </div>
                    </div>

                    <h3>Các dòng của ma trận</h3>
                    <p class="ghi-chu">
                        Mỗi dòng: chọn CLO, mức Bloom, độ khó và số câu. Dòng không chọn CLO và không nhập số câu sẽ bị bỏ qua.
                        Không được có hai dòng cùng CLO, mức Bloom và độ khó (hãy gộp số câu vào một dòng).
                    </p>
                    <table>
                        <thead>
                            <tr><th style="width:36px;">#</th><th>CLO</th><th style="width:150px;">Mức Bloom</th><th style="width:150px;">Độ khó</th><th style="width:90px;">Số câu</th></tr>
                        </thead>
                        <tbody>
                            @for ($i = 0; $i < $soDong; $i++)
                                @php
                                    $h = $hang[$i] ?? null;
                                @endphp
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <select name="dong[{{ $i }}][clo]">
                                            <option value="">--</option>
                                            @foreach ($clos as $clo)
                                                <option value="{{ $clo->maClo }}" @selected((string) old('dong.'.$i.'.clo', $h['clo'] ?? '') === (string) $clo->maClo)>{{ $clo->kyHieu }} - {{ \Illuminate\Support\Str::limit($clo->moTa, 70) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="dong[{{ $i }}][bloom]">
                                            @foreach ($bloom as $b)
                                                <option value="{{ $b }}" @selected(old('dong.'.$i.'.bloom', $h['bloom'] ?? 'Hiểu') === $b)>{{ $b }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="dong[{{ $i }}][do_kho]">
                                            @foreach ($doKho as $d)
                                                <option value="{{ $d }}" @selected(old('dong.'.$i.'.do_kho', $h['do_kho'] ?? 'Trung bình') === $d)>{{ $d }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="text" name="dong[{{ $i }}][so_cau]" value="{{ old('dong.'.$i.'.so_cau', $h['so_cau'] ?? '') }}"></td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>

                    <div style="margin-top:22px;">
                        <button type="submit" class="nut-xanh">Lưu ma trận</button>
                        <a href="{{ $dangSua ? route('ma-tran.show', $maTran->maMaTranDeThi) : route('ma-tran.index') }}" style="margin-left:12px;">Hủy</a>
                    </div>
                </form>
            @endif
        @endif
    </div>
@endsection
