@extends('layouts.app')

@section('tieu_de', 'Phản biện đề - Ngân hàng đề thi')

@section('noi_dung')
    <h2>Phản biện đề thi</h2>

    @unless ($coPhanCong)
        <div class="canh-bao">Bạn chưa được phân công phản biện học phần nào. Hãy nhờ Trưởng bộ môn phân công (BR-10, UC-20).</div>
    @endunless

    <h3>Đề đang chờ phản biện</h3>
    <table>
        <thead><tr><th>Mã</th><th>Tên đề</th><th>Học phần</th><th>Hình thức</th><th>Số câu</th><th>Người biên soạn</th></tr></thead>
        <tbody>
            @forelse ($cho as $d)
                <tr>
                    <td><a href="{{ route('phan-bien.show', $d->maDeThi) }}">#{{ $d->maDeThi }}</a></td>
                    <td><a href="{{ route('phan-bien.show', $d->maDeThi) }}">{{ $d->tenDeThi }}</a></td>
                    <td>{{ $d->maTranDeThi?->maHocPhan }} - {{ $d->maTranDeThi?->hocPhan?->tenHocPhan }}</td>
                    <td>{{ $d->hinhThucThi }}</td>
                    <td>{{ $d->de_thi_cau_hois_count }}</td>
                    <td>{{ $d->nguoiTao?->hoTen }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="ghi-chu">Không có đề nào đang chờ bạn phản biện.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Đề tôi đã phản biện</h3>
    <table>
        <thead><tr><th>Mã</th><th>Tên đề</th><th>Học phần</th><th>Kết quả</th><th>Ngày</th><th>Trạng thái hiện tại</th></tr></thead>
        <tbody>
            @forelse ($daLam as $d)
                <tr>
                    <td><a href="{{ route('phan-bien.show', $d->maDeThi) }}">#{{ $d->maDeThi }}</a></td>
                    <td>{{ $d->tenDeThi }}</td>
                    <td>{{ $d->maTranDeThi?->maHocPhan }}</td>
                    <td>{{ $d->ketQuaPhanBien }}</td>
                    <td>{{ $d->ngayPhanBien?->format('d/m/Y') }}</td>
                    <td>{{ $d->trangThai }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="ghi-chu">Chưa có.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
