@extends('layouts.app')

@section('tieu_de', 'Ngân hàng đề '.$hocPhan->maHocPhan.' - Ngân hàng đề thi')

@section('noi_dung')
    <p><a href="{{ route('ngan-hang.index') }}">&larr; Cơ cấu ngân hàng đề thi</a></p>

    <h2>{{ $hocPhan->maHocPhan }} - {{ $hocPhan->tenHocPhan }}</h2>

    @if ($theoHinhThuc->isEmpty())
        <div class="canh-bao">Học phần này chưa có ngân hàng đề thi (chưa có đề nào được duyệt). Hãy thông báo bộ môn bổ sung đề.</div>
    @else
        <form method="GET" action="{{ route('ngan-hang.xuat') }}" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin:12px 0;">
            <input type="hidden" name="hoc_phan" value="{{ $hocPhan->maHocPhan }}">
            <strong>Xuất ngân hàng đề:</strong>
            <select name="mau" style="width:200px;">
                <option value="ca-hai">Đề thi và đáp án</option>
                <option value="de">Chỉ đề thi</option>
                <option value="dap-an">Chỉ đáp án</option>
            </select>
            <select name="dinh_dang" style="width:130px;">
                <option value="docx">Word (.docx)</option>
                <option value="pdf">PDF</option>
            </select>
            <button type="submit">Tải về</button>
        </form>
    @endif

    @foreach ($toiThieu as $ht => $min)
        @php $ds = $theoHinhThuc->get($ht, collect()); @endphp
        <h3>{{ $ht }}: {{ $ds->count() }} / {{ $min }} đề
            @if ($ds->count() < $min)<span style="color:#b91c1c; font-weight:normal;">(thiếu {{ $min - $ds->count() }})</span>@endif
        </h3>
        @if ($ds->isNotEmpty())
            <table>
                <thead><tr><th>Mã</th><th>Tên đề</th><th>Số câu</th><th>Biên soạn</th><th>Ngày duyệt</th><th>Đã dùng</th></tr></thead>
                <tbody>
                    @foreach ($ds as $d)
                        <tr>
                            <td>#{{ $d->maDeThi }}</td>
                            <td>{{ $d->tenDeThi }}</td>
                            <td>{{ $d->de_thi_cau_hois_count }}</td>
                            <td>{{ $d->nguoiTao?->hoTen }}</td>
                            <td>{{ $d->ngayDuyet?->format('d/m/Y') }}</td>
                            <td>
                                @forelse ($d->suDungDeThis->sortByDesc('maSuDung') as $s)
                                    HK{{ $s->hocKy }} {{ $s->namHoc }}@if (! $loop->last), @endif
                                @empty
                                    Chưa dùng
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach
@endsection
