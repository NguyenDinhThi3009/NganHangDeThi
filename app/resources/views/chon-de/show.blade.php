@extends('layouts.app')

@section('tieu_de', 'Ca thi #'.$suDung->maSuDung.' - Ngân hàng đề thi')

@section('noi_dung')
    <p><a href="{{ route('chon-de.index') }}">&larr; Chọn đề thi</a></p>

    <div class="the">
        <h2>Đề thi đã chọn: {{ $deThi->tenDeThi }}</h2>
        <table style="margin:12px 0;">
            <tr><th style="width:200px;">Học phần</th><td>{{ $deThi->maTranDeThi?->maHocPhan }} - {{ $deThi->maTranDeThi?->hocPhan?->tenHocPhan }}</td></tr>
            <tr><th>Hình thức thi</th><td>{{ $deThi->hinhThucThi }} ({{ $soCau }} câu)</td></tr>
            <tr><th>Học kỳ, năm học</th><td>{{ $suDung->hocKy }} / {{ $suDung->namHoc }}</td></tr>
            <tr><th>Ngày thi</th><td>{{ $suDung->ngaySuDung?->format('d/m/Y') }}</td></tr>
            <tr><th>Duyệt đề</th><td>{{ $deThi->nguoiDuyet?->hoTen }} - {{ $deThi->ngayDuyet?->format('d/m/Y') }}</td></tr>
            <tr><th>Người biên soạn</th><td>{{ $deThi->nguoiTao?->hoTen }}</td></tr>
        </table>

        <div class="hang-nut">
            <form method="GET" action="{{ route('chon-de.xuat', $suDung->maSuDung) }}" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <strong>Xuất file:</strong>
                @if ($cacMa)
                    <select name="ma" style="width:170px;">
                        <option value="tat-ca">Tất cả mã đề</option>
                        @foreach ($cacMa as $m)
                            <option value="{{ $m['ma']->maMaDe }}">Mã đề {{ $m['ma']->kyHieuMa }}</option>
                        @endforeach
                    </select>
                @endif
                <select name="mau" style="width:200px;">
                    <option value="ca-hai">Đề thi và đáp án</option>
                    <option value="de">Chỉ đề thi (để in)</option>
                    <option value="dap-an">Chỉ đáp án</option>
                </select>
                <select name="dinh_dang" style="width:130px;">
                    <option value="docx">Word (.docx)</option>
                    <option value="pdf">PDF</option>
                </select>
                <button type="submit" class="nut-xanh">Tải về</button>
            </form>
            <form method="POST" action="{{ route('chon-de.huy', $suDung->maSuDung) }}" style="display:flex; gap:8px; align-items:center;"
                  onsubmit="return confirm('Hủy lần chọn này? Các mã đề đã tạo sẽ bị xóa và đề được trả lại danh sách có thể chọn.');">
                @csrf
                <input type="text" name="ly_do" maxlength="300" placeholder="Lý do hủy (không bắt buộc)" style="width:240px;">
                <button type="submit" class="nut-do">Hủy lần chọn</button>
            </form>
        </div>

        @if ($cacMa)
            <h3>Mã đề và đáp án đúng</h3>
            <div class="ghi-chu">Mỗi mã đề có thứ tự câu và thứ tự phương án riêng. Ô hiển thị "số câu: đáp án" theo thứ tự trong mã đề đó.</div>
            @foreach ($cacMa as $m)
                <div style="margin:14px 0 6px;"><strong>Mã đề {{ $m['ma']->kyHieuMa }}</strong></div>
                <div style="display:flex; flex-wrap:wrap; gap:4px;">
                    @foreach ($m['du_lieu']['cau'] as $c)
                        <span style="border:1px solid #d1d5db; border-radius:4px; padding:2px 8px; min-width:52px; text-align:center;">{{ $c['stt'] }}: <strong>{{ $c['dap_an_dung'] }}</strong></span>
                    @endforeach
                </div>
            @endforeach
        @elseif ($deThi->hinhThucThi === 'Trắc nghiệm')
            <div class="canh-bao">Lần chọn này chưa có mã đề.</div>
        @else
            <div class="ghi-chu">Đề {{ mb_strtolower($deThi->hinhThucThi) }} không có mã đề trắc nghiệm.</div>
        @endif
    </div>
@endsection
