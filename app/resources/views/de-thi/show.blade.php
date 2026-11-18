@extends('layouts.app')

@section('tieu_de', 'Đề thi #'.$deThi->maDeThi.' - Ngân hàng đề thi')

@section('noi_dung')
    <p><a href="{{ route('de-thi.index') }}">&larr; Danh sách đề thi</a></p>

    <div class="the">
        <h2>{{ $deThi->tenDeThi }}</h2>

        <table style="margin:12px 0;">
            <tr><th style="width:200px;">Học phần</th><td>{{ $deThi->maTranDeThi?->maHocPhan }} - {{ $deThi->maTranDeThi?->hocPhan?->tenHocPhan }}</td></tr>
            <tr><th>Ma trận</th><td>@if ($deThi->maTranDeThi)<a href="{{ route('ma-tran.show', $deThi->maTranDeThi->maMaTranDeThi) }}">#{{ $deThi->maTranDeThi->maMaTranDeThi }} - {{ $deThi->maTranDeThi->tenMaTran }}</a>@endif</td></tr>
            <tr><th>Hình thức thi</th><td>{{ $deThi->hinhThucThi }}</td></tr>
            @if ($deThi->hinhThucThi === 'Vấn đáp')
                <tr><th>Thời gian</th><td>Chuẩn bị {{ $deThi->thoiGianChuanBi }} phút, trình bày {{ $deThi->thoiGianTraLoi }} phút</td></tr>
            @else
                <tr><th>Thời gian làm bài</th><td>{{ $deThi->thoiGianLamBai }} phút</td></tr>
            @endif
            <tr><th>Trạng thái</th><td><strong>{{ $deThi->trangThai }}</strong></td></tr>
            <tr><th>Người tạo</th><td>{{ $deThi->nguoiTao?->hoTen }} - {{ $deThi->ngayTao?->format('d/m/Y H:i') }}</td></tr>
            @if ($deThi->nguoiPhanBien)
                <tr><th>Phản biện</th><td>{{ $deThi->nguoiPhanBien->hoTen }} - {{ $deThi->ngayPhanBien?->format('d/m/Y') }} - {{ $deThi->ketQuaPhanBien }}</td></tr>
            @endif
            @if ($deThi->nguoiDuyet)
                <tr><th>Phê duyệt</th><td>{{ $deThi->nguoiDuyet->hoTen }} - {{ $deThi->ngayDuyet?->format('d/m/Y') }}</td></tr>
            @endif
        </table>

        @if ($deThi->trangThai === 'Nháp' && $deThi->ketQuaPhanBien === 'Cần chỉnh sửa' && $nhanXet)
            <div class="canh-bao"><strong>Đề bị trả về sau phản biện.</strong> {{ $nhanXet }}<br>Hãy xóa đề này, tạo lại hoặc chỉnh ma trận/câu hỏi rồi tạo đề mới.</div>
        @endif
        @if ($lyDoTuChoi)
            <div class="loi-khung"><strong>Đề bị từ chối.</strong> {{ $lyDoTuChoi }}</div>
        @endif

        @foreach ($loiCauTruc as $loi)
            <div class="loi-khung">{{ $loi }}</div>
        @endforeach
        @foreach ($canhBao as $cb)
            <div class="canh-bao">{{ $cb }}</div>
        @endforeach

        @if ($duocThaoTac && $deThi->trangThai === 'Nháp')
            <div class="hang-nut">
                <form method="POST" action="{{ route('de-thi.gui-phan-bien', $deThi->maDeThi) }}"
                      onsubmit="return confirm('Gửi đề đi phản biện? Sau khi gửi, các câu hỏi trong đề bị khóa sửa.');">
                    @csrf
                    <button type="submit" class="nut-xanh">Gửi phản biện</button>
                </form>
                <form method="POST" action="{{ route('de-thi.destroy', $deThi->maDeThi) }}"
                      onsubmit="return confirm('Xóa đề thi này? Thao tác không hoàn tác được.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="nut-do">Xóa đề</button>
                </form>
            </div>
        @endif

        @if ($duocThaoTac)
            <form method="GET" action="{{ route('de-thi.xuat', $deThi->maDeThi) }}" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin:12px 0;">
                <strong>Xuất file:</strong>
                <select name="mau" style="width:200px;">
                    <option value="ca-hai">Đề thi và đáp án</option>
                    <option value="de">Chỉ đề thi</option>
                    <option value="dap-an">Chỉ đáp án và thang điểm</option>
                </select>
                <select name="dinh_dang" style="width:130px;">
                    <option value="docx">Word (.docx)</option>
                    <option value="pdf">PDF</option>
                </select>
                <button type="submit" class="nut-xanh">Tải về</button>
            </form>
        @endif

        <h3>Nội dung đề và đáp án</h3>
        @include('de-thi._noi-dung', ['muc' => $muc, 'hinhThuc' => $deThi->hinhThucThi, 'tongDiem' => $tongDiem, 'hienDapAn' => true])
    </div>
@endsection
