<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>{{ $tieuDe }}</title>
    <style>
        @page { size: A4; margin: 2cm 2cm 2cm 2.5cm; }
        body { font-family: "DejaVu Serif", "Times New Roman", serif; font-size: 11pt; line-height: 1.45; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }
        .giua { text-align: center; }
        .phai { text-align: right; }
        .deu { text-align: justify; }
        .nho { font-size: 10pt; }
        .dau-trang td { padding: 0 6px; }
        .tieu-de { font-weight: bold; font-size: 12pt; }
        .gach { border-bottom: 1px solid #000; padding-bottom: 6px; margin-bottom: 8px; text-align: center; font-style: italic; font-size: 10pt; }
        .cau { margin-top: 9pt; page-break-inside: avoid; }
        .pa { margin: 1pt 0 1pt 22pt; }
        .luoi th, .luoi td { border: 1px solid #000; padding: 3px 6px; }
        .luoi th { background: #e5e7eb; }
        .ngat { page-break-before: always; }
        .ky td { padding-top: 6px; }
        .thanh-cong { background: #eef2ff; padding: 10px 14px; margin-bottom: 16px; font-family: Arial, sans-serif; font-size: 13px; }
        @media print { .thanh-cong { display: none; } }
        @media screen { body { max-width: 760px; margin: 20px auto; padding: 0 16px; } }
    </style>
</head>
<body>
@if ($inTrucTiep)
    <div class="thanh-cong">
        Máy chủ chưa cài thư viện tạo PDF (dompdf), nên đây là bản in của đề.
        Nhấn <strong>Ctrl + P</strong>, chọn máy in <strong>Lưu dưới dạng PDF</strong>.
        <button onclick="window.print()">In / Lưu PDF</button>
    </div>
@endif

@php $dau = true; @endphp
@foreach ($cacDe as $de)
    @foreach (($mau === 'ca-hai' ? ['de', 'dap-an'] : [$mau]) as $phan)
        <div @class(['ngat' => ! $dau])>
            @php $dau = false; @endphp

            <table class="dau-trang">
                <tr>
                    <td class="giua nho" style="width:40%;">
                        <strong>{{ mb_strtoupper($de['truong']) }}</strong><br>
                        @if ($de['khoa'] !== '')<strong>{{ mb_strtoupper($de['khoa']) }}</strong><br>@endif
                        @if ($de['bo_mon'] !== '')<strong>BỘ MÔN {{ mb_strtoupper($de['bo_mon']) }}</strong>@endif
                    </td>
                    <td class="giua nho">
                        <span class="tieu-de">{{ $phan === 'de' ? 'ĐỀ THI KẾT THÚC HỌC PHẦN' : 'ĐÁP ÁN VÀ THANG ĐIỂM' }}</span><br>
                        Học phần: <strong>{{ $de['hoc_phan'] }} ({{ $de['ma_hoc_phan'] }})</strong><br>
                        Hình thức thi: {{ $de['hinh_thuc'] }}<br>
                        {{ $de['hinh_thuc'] === 'Vấn đáp' ? 'Thời gian' : 'Thời gian làm bài' }}: {{ $de['thoi_gian'] }}
                    </td>
                </tr>
            </table>
            <div class="gach" style="margin-top:8px;">{{ $de['dong_de'] }}</div>
            @if ($de['la_ban_nhap'])
                <div class="giua nho"><em>(Bản nháp, chưa được phê duyệt - trạng thái: {{ $de['trang_thai'] }})</em></div>
            @endif

            @if ($phan === 'de')
                <p style="margin:10pt 0;"><em>
                    @if ($de['hinh_thuc'] === 'Trắc nghiệm') Chọn một phương án đúng nhất cho mỗi câu. @endif
                    Đề gồm {{ $de['so_cau'] }} câu, thang điểm {{ $de['tong_diem'] }}.
                </em></p>

                @php $phanHienTai = null; @endphp
                @foreach ($de['cau'] as $c)
                    @if ($de['hinh_thuc'] === 'Vấn đáp' && $phanHienTai !== $c['phan'])
                        @php $phanHienTai = $c['phan']; @endphp
                        <p style="margin:14pt 0 2pt;"><strong>{{ $c['phan'] === 1 ? 'Phần 1 - Câu hỏi bắt buộc' : 'Phần 2 - Câu hỏi thêm' }}</strong></p>
                    @endif
                    <div class="cau deu">
                        <strong>Câu {{ $c['stt'] }}</strong> <em>({{ $c['diem'] }} điểm).</em> {!! nl2br(e($c['noi_dung'])) !!}
                        @foreach ($c['phuong_an'] as $pa)
                            <div class="pa">{{ $pa['nhan'] }}. {{ $pa['noi_dung'] }}</div>
                        @endforeach
                    </div>
                @endforeach

                <p class="giua" style="margin-top:18pt;"><strong>--- HẾT ---</strong></p>
                <table class="ky">
                    <tr>
                        <td class="giua" style="width:50%;"><strong>Người ra đề</strong><br><br><br><br>{{ $de['nguoi_bien_soan'] }}</td>
                        <td class="giua"><strong>Trưởng bộ môn</strong></td>
                    </tr>
                </table>
            @else
                <p class="giua nho"><em>Tài liệu lưu hành nội bộ, chỉ dùng cho chấm thi và phúc khảo.</em></p>

                @if ($de['hinh_thuc'] === 'Trắc nghiệm')
                    @php $nua = (int) ceil(count($de['cau']) / 2); @endphp
                    <table class="luoi giua">
                        <thead><tr><th>Câu</th><th>Đáp án</th><th>Điểm</th><th>Câu</th><th>Đáp án</th><th>Điểm</th></tr></thead>
                        <tbody>
                            @for ($i = 0; $i < $nua; $i++)
                                @php $a = $de['cau'][$i] ?? null; $b = $de['cau'][$i + $nua] ?? null; @endphp
                                <tr>
                                    <td>{{ $a['stt'] ?? '' }}</td><td><strong>{{ $a['dap_an_dung'] ?? '' }}</strong></td><td>{{ $a['diem'] ?? '' }}</td>
                                    <td>{{ $b['stt'] ?? '' }}</td><td><strong>{{ $b['dap_an_dung'] ?? '' }}</strong></td><td>{{ $b['diem'] ?? '' }}</td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                @else
                    @php $phanHienTai = null; @endphp
                    @foreach ($de['cau'] as $c)
                        @if ($de['hinh_thuc'] === 'Vấn đáp' && $phanHienTai !== $c['phan'])
                            @php $phanHienTai = $c['phan']; @endphp
                            <p style="margin:14pt 0 2pt;"><strong>{{ $c['phan'] === 1 ? 'Phần 1 - Câu hỏi bắt buộc' : 'Phần 2 - Câu hỏi thêm' }}</strong></p>
                        @endif
                        <div class="cau deu"><strong>Câu {{ $c['stt'] }}</strong> <em>({{ $c['diem'] }} điểm).</em> {!! nl2br(e($c['noi_dung'])) !!}</div>
                        <table class="luoi" style="margin-top:4pt;">
                            <thead><tr><th style="width:36px;">Ý</th><th>Nội dung đáp án</th><th style="width:60px;">Điểm</th></tr></thead>
                            <tbody>
                                @foreach ($c['cac_y'] as $i => $y)
                                    <tr><td class="giua">{{ $i + 1 }}</td><td>{{ $y['noi_dung'] }}</td><td class="giua">{{ $y['diem'] }}</td></tr>
                                @endforeach
                                <tr><td></td><td class="phai"><strong>Cộng</strong></td><td class="giua"><strong>{{ $c['diem'] }}</strong></td></tr>
                            </tbody>
                        </table>
                    @endforeach
                @endif
                <p style="margin-top:12pt;"><strong>Tổng điểm: {{ $de['tong_diem'] }}</strong></p>
            @endif
        </div>
    @endforeach
@endforeach
</body>
</html>
