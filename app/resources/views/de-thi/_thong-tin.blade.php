<table style="margin:12px 0;">
    <tr><th style="width:200px;">Học phần</th><td>{{ $deThi->maTranDeThi?->maHocPhan }} - {{ $deThi->maTranDeThi?->hocPhan?->tenHocPhan }}</td></tr>
    <tr><th>Hình thức thi</th><td>{{ $deThi->hinhThucThi }}</td></tr>
    @if ($deThi->hinhThucThi === 'Vấn đáp')
        <tr><th>Thời gian</th><td>Chuẩn bị {{ $deThi->thoiGianChuanBi }} phút, trình bày {{ $deThi->thoiGianTraLoi }} phút</td></tr>
    @else
        <tr><th>Thời gian làm bài</th><td>{{ $deThi->thoiGianLamBai }} phút</td></tr>
    @endif
    <tr><th>Trạng thái</th><td><strong>{{ $deThi->trangThai }}</strong></td></tr>
    <tr><th>Người biên soạn</th><td>{{ $deThi->nguoiTao?->hoTen }} - {{ $deThi->ngayTao?->format('d/m/Y H:i') }}</td></tr>
    @if ($deThi->nguoiPhanBien)
        <tr><th>Phản biện</th><td>{{ $deThi->nguoiPhanBien->hoTen }} - {{ $deThi->ngayPhanBien?->format('d/m/Y') }} - <strong>{{ $deThi->ketQuaPhanBien }}</strong></td></tr>
    @endif
    @if ($deThi->nguoiDuyet)
        <tr><th>Phê duyệt</th><td>{{ $deThi->nguoiDuyet->hoTen }} - {{ $deThi->ngayDuyet?->format('d/m/Y') }}</td></tr>
    @endif
</table>
@foreach ($loiCauTruc as $loi)
    <div class="loi-khung">{{ $loi }}</div>
@endforeach
@foreach ($canhBao as $cb)
    <div class="canh-bao">{{ $cb }}</div>
@endforeach
