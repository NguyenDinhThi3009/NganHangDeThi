@extends('layouts.app')

@section('tieu_de', 'Sửa câu hỏi #'.$cauHoi->maCauHoi.' - Ngân hàng đề thi')

@section('noi_dung')
    <p><a href="{{ route('cau-hoi.show', $cauHoi->maCauHoi) }}">&larr; Quay lại câu hỏi #{{ $cauHoi->maCauHoi }}</a></p>

    <div class="the" style="max-width:860px;">
        <h2>Sửa câu hỏi #{{ $cauHoi->maCauHoi }} ({{ $cauHoi->loaiCauHoi }})</h2>

        @if ($errors->any())
            <div class="loi-khung">
                <ul style="margin:0; padding-left:18px;">
                    @foreach ($errors->all() as $loi)
                        <li>{{ $loi }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('cau-hoi.cap-nhat', $cauHoi->maCauHoi) }}">
            @csrf
            @method('PUT')

            <label for="noi_dung">Nội dung câu hỏi</label>
            <textarea id="noi_dung" name="noi_dung" rows="4" required>{{ old('noi_dung', $cauHoi->noiDungCauHoi) }}</textarea>

            <label for="ma_clo">Chuẩn đầu ra (CLO)</label>
            <select id="ma_clo" name="ma_clo" required>
                @foreach ($clos as $clo)
                    <option value="{{ $clo->maClo }}" @selected((string) old('ma_clo', $cauHoi->maClo) === (string) $clo->maClo)>{{ $clo->kyHieu }} - {{ \Illuminate\Support\Str::limit($clo->moTa, 110) }}</option>
                @endforeach
            </select>

            <label for="muc_do_bloom">Mức độ Bloom</label>
            <select id="muc_do_bloom" name="muc_do_bloom" required>
                @foreach ($bloom as $b)
                    <option value="{{ $b }}" @selected(old('muc_do_bloom', $nhanBloomHienTai) === $b)>{{ $b }}</option>
                @endforeach
            </select>

            <label for="do_kho">Độ khó</label>
            <select id="do_kho" name="do_kho" required>
                @foreach ($doKho as $d)
                    <option value="{{ $d }}" @selected(old('do_kho', $cauHoi->doKho) === $d)>{{ $d }}</option>
                @endforeach
            </select>

            @if ($cauHoi->loaiCauHoi === 'Trắc nghiệm')
                <label>Phương án (ít nhất 3, chọn đúng 1 đáp án đúng; để trống dòng không dùng)</label>
                @for ($i = 0; $i < $soDongPhuongAn; $i++)
                    <div style="display:flex; align-items:center; gap:10px; margin:6px 0;">
                        <input type="radio" name="dap_an_dung" value="{{ $i }}" style="width:auto;"
                               @checked((string) old('dap_an_dung', $viTriDung) === (string) $i)>
                        <strong style="width:20px;">{{ chr(65 + $i) }}</strong>
                        <input type="text" name="phuong_an[{{ $i }}]" value="{{ old('phuong_an.'.$i, $phuongAn[$i]->noiDungDapAn ?? '') }}" maxlength="1000">
                    </div>
                @endfor
                <div class="ghi-chu">Nút tròn bên trái đánh dấu phương án là đáp án đúng.</div>
            @else
                <label>Đáp án theo ý (mỗi ý tối đa 0,5 điểm; để trống dòng không dùng)</label>
                @for ($i = 0; $i < $soDongY; $i++)
                    @php
                        $y = $cacY[$i] ?? null;
                        $diemMacDinh = $y ? rtrim(rtrim(number_format((float) $y->diemY, 2, ',', ''), '0'), ',') : '';
                    @endphp
                    <div style="display:flex; align-items:center; gap:10px; margin:6px 0;">
                        <strong style="width:24px;">{{ $i + 1 }}</strong>
                        <input type="text" name="y_noi_dung[{{ $i }}]" value="{{ old('y_noi_dung.'.$i, $y->noiDungY ?? '') }}" maxlength="1000">
                        <input type="text" name="y_diem[{{ $i }}]" value="{{ old('y_diem.'.$i, $diemMacDinh) }}" placeholder="điểm" style="width:80px;">
                    </div>
                @endfor
                <div class="ghi-chu">Điểm dùng dấu phẩy hoặc dấu chấm đều được (ví dụ 0,5 hoặc 0.25).</div>
            @endif

            <div style="margin-top:22px;">
                <button type="submit">Lưu thay đổi</button>
                <a href="{{ route('cau-hoi.show', $cauHoi->maCauHoi) }}" style="margin-left:12px;">Hủy</a>
            </div>
        </form>
    </div>
@endsection
