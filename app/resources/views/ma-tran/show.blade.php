@extends('layouts.app')

@section('tieu_de', 'Ma trận #'.$maTran->maMaTranDeThi.' - Ngân hàng đề thi')

@section('noi_dung')
    <p><a href="{{ route('ma-tran.index') }}">&larr; Danh sách ma trận</a></p>

    <div class="the">
        <h2>{{ $maTran->tenMaTran }}</h2>

        <table style="margin:12px 0;">
            <tr><th style="width:200px;">Học phần</th><td>{{ $maTran->maHocPhan }} - {{ $maTran->hocPhan?->tenHocPhan }}</td></tr>
            <tr><th>Trạng thái</th><td><strong>{{ $maTran->trangThai }}</strong></td></tr>
            <tr><th>Ngưỡng cảnh báo trùng lặp</th><td>{{ number_format((float) $maTran->nguongTrungLap * 100, 0) }}%</td></tr>
            <tr><th>Người tạo</th><td>{{ $maTran->nguoiTao?->hoTen }} - {{ $maTran->ngayTao?->format('d/m/Y H:i') }}</td></tr>
            <tr><th>Tổng số câu theo ma trận</th><td><strong>{{ $tongCau }}</strong> câu</td></tr>
        </table>

        @if ($duocThaoTac)
            <div class="hang-nut">
                @if ($maTran->trangThai === 'Nháp')
                    <form method="POST" action="{{ route('ma-tran.chot', $maTran->maMaTranDeThi) }}">
                        @csrf
                        <button type="submit" class="nut-xanh">Chốt ma trận</button>
                    </form>
                    <a class="nut" href="{{ route('ma-tran.edit', $maTran->maMaTranDeThi) }}">Sửa</a>
                @elseif (! $dangDuocDung)
                    <form method="POST" action="{{ route('ma-tran.bo-chot', $maTran->maMaTranDeThi) }}">
                        @csrf
                        <button type="submit" class="nut-xam">Mở lại để sửa</button>
                    </form>
                @endif

                @if (! $dangDuocDung)
                    <form method="POST" action="{{ route('ma-tran.destroy', $maTran->maMaTranDeThi) }}"
                          onsubmit="return confirm('Xóa ma trận này? Thao tác không hoàn tác được.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="nut-do">Xóa</button>
                    </form>
                @endif
            </div>
            @if ($dangDuocDung)
                <div class="ghi-chu">Ma trận đang được dùng cho đề thi nên không thể mở lại hoặc xóa.</div>
            @endif
        @endif

        <h3>Chi tiết và số câu hỏi hiện có trong ngân hàng</h3>
        <table>
            <thead>
                <tr>
                    <th>CLO</th>
                    <th>Mức Bloom</th>
                    <th>Độ khó</th>
                    <th>Số câu cần</th>
                    <th>Đã duyệt (TN / TL)</th>
                    <th>Còn nháp</th>
                    <th>Thiếu</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($hang as $h)
                    <tr @if ($h['thieu'] > 0) style="background:#fef2f2;" @endif>
                        <td>{{ $h['dong']->clo?->kyHieu }}</td>
                        <td>{{ $h['nhan_bloom'] }}</td>
                        <td>{{ $h['dong']->doKho }}</td>
                        <td>{{ $h['dong']->soLuongCau }}</td>
                        <td>{{ $h['duyet'] }} ({{ $h['tn'] }} / {{ $h['tl'] }})</td>
                        <td>{{ $h['nhap'] }}</td>
                        <td>@if ($h['thieu'] > 0)<strong style="color:#b91c1c;">{{ $h['thieu'] }}</strong>@else 0 @endif</td>
                    </tr>
                @endforeach
                <tr>
                    <th colspan="3">Tổng</th>
                    <th>{{ $tongCau }}</th>
                    <th colspan="2"></th>
                    <th>{{ $tongThieu }}</th>
                </tr>
            </tbody>
        </table>

        @if ($tongThieu > 0)
            <div class="canh-bao" style="margin-top:12px;">
                Ngân hàng còn thiếu <strong>{{ $tongThieu }}</strong> câu đã duyệt để đáp ứng ma trận này. Hãy duyệt các câu còn nháp
                hoặc <a href="{{ route('cau-hoi.sinh', ['hoc_phan' => $maTran->maHocPhan]) }}">sinh thêm câu hỏi bằng AI</a>.
                Số liệu "Đã duyệt" tính chung cả trắc nghiệm và tự luận; việc chọn hình thức thi diễn ra khi tạo đề.
            </div>
        @else
            <div class="thanh-cong" style="margin-top:12px;">Ngân hàng đã đủ số câu đã duyệt cho từng dòng của ma trận.</div>
        @endif

        @if (! empty($cloChuaCo))
            <div class="ghi-chu" style="margin-top:8px;">CLO chưa có dòng nào trong ma trận: {{ implode(', ', $cloChuaCo) }}.</div>
        @endif

        <h3>Phân bố của ma trận</h3>
        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:18px;">
            @foreach ($phanBo as $tieuDe => $muc)
                <div>
                    <strong>{{ $tieuDe }}</strong>
                    <table style="margin-top:6px;">
                        @foreach ($muc as $m)
                            <tr><td>{{ $m['ten'] }}</td><td>{{ $m['so'] }} câu</td><td>{{ number_format($m['ty_le'], 1, ',', '') }}%</td></tr>
                        @endforeach
                    </table>
                </div>
            @endforeach
        </div>
    </div>
@endsection
