@extends('layouts.app')

@section('tieu_de', 'Ngân hàng chưa đủ câu - Ngân hàng đề thi')

@section('noi_dung')
    <p><a href="{{ route('de-thi.create', ['ma_tran' => $maTran->maMaTranDeThi]) }}">&larr; Quay lại tạo đề</a></p>

    <div class="the">
        <h2>Ngân hàng chưa đủ câu hỏi cho ma trận</h2>
        <p>
            Ma trận <strong>#{{ $maTran->maMaTranDeThi }} - {{ $maTran->tenMaTran }}</strong>
            ({{ $maTran->maHocPhan }} - {{ $maTran->hocPhan?->tenHocPhan }}), hình thức thi <strong>{{ $hinhThuc }}</strong>.
            Còn thiếu <strong style="color:#b91c1c;">{{ $tongThieu }}</strong> câu hỏi <strong>đã duyệt, loại {{ $hinhThuc }}</strong>
            ở các dòng sau:
        </p>

        <table>
            <thead>
                <tr>
                    <th>CLO</th>
                    <th>Mức Bloom</th>
                    <th>Độ khó</th>
                    <th>Cần</th>
                    <th>Có</th>
                    <th>Thiếu</th>
                    <th>Gợi ý</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($thieu as $t)
                    <tr style="background:#fef2f2;">
                        <td>{{ $t['clo'] }}</td>
                        <td>{{ $t['bloom'] }}</td>
                        <td>{{ $t['do_kho'] }}</td>
                        <td>{{ $t['can'] }}</td>
                        <td>{{ $t['co'] }}</td>
                        <td><strong style="color:#b91c1c;">{{ $t['thieu'] }}</strong></td>
                        <td class="ghi-chu">
                            @if ($t['nhap'] > 0) Có {{ $t['nhap'] }} câu còn nháp, hãy duyệt. @endif
                            @if ($t['loai_khac'] > 0) Có {{ $t['loai_khac'] }} câu đã duyệt thuộc loại khác. @endif
                            @if ($t['khong_hop_le'] > 0) {{ $t['khong_hop_le'] }} câu bị loại vì đáp án không hợp lệ (sửa lại đáp án). @endif
                            @if ($t['nhap'] === 0 && $t['loai_khac'] === 0 && $t['khong_hop_le'] === 0) Cần sinh thêm câu hỏi. @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="hang-nut">
            <a class="nut" href="{{ route('cau-hoi.index') }}">Duyệt câu hỏi nháp</a>
            <a class="nut nut-xanh" href="{{ route('cau-hoi.sinh', ['hoc_phan' => $maTran->maHocPhan]) }}">Sinh thêm câu hỏi bằng AI</a>
            <a class="nut nut-xam" href="{{ route('ma-tran.show', $maTran->maMaTranDeThi) }}">Xem ma trận</a>
        </div>
        @if ($hinhThuc === 'Vấn đáp')
            <div class="ghi-chu" style="margin-top:10px;">Chức năng sinh câu hỏi hiện chỉ tạo câu Trắc nghiệm và Tự luận; câu Vấn đáp cần nhập tay.</div>
        @endif
    </div>
@endsection
