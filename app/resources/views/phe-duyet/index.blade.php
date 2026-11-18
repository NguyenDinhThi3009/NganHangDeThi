@extends('layouts.app')

@section('tieu_de', 'Phê duyệt đề - Ngân hàng đề thi')

@section('noi_dung')
    <h2>Phê duyệt đề thi</h2>

    @unless ($coBoMon)
        <div class="canh-bao">Tài khoản của bạn chưa gắn bộ môn nên chưa có đề nào để duyệt. Quản trị viên cần gán bộ môn cho tài khoản.</div>
    @endunless

    <h3>Đề chờ duyệt</h3>
    <table>
        <thead><tr><th>Mã</th><th>Tên đề</th><th>Học phần</th><th>Hình thức</th><th>Số câu</th><th>Biên soạn</th><th>Phản biện</th></tr></thead>
        <tbody>
            @forelse ($cho as $d)
                <tr>
                    <td><a href="{{ route('phe-duyet.show', $d->maDeThi) }}">#{{ $d->maDeThi }}</a></td>
                    <td><a href="{{ route('phe-duyet.show', $d->maDeThi) }}">{{ $d->tenDeThi }}</a></td>
                    <td>{{ $d->maTranDeThi?->maHocPhan }} - {{ $d->maTranDeThi?->hocPhan?->tenHocPhan }}</td>
                    <td>{{ $d->hinhThucThi }}</td>
                    <td>{{ $d->de_thi_cau_hois_count }}</td>
                    <td>{{ $d->nguoiTao?->hoTen }}</td>
                    <td>{{ $d->nguoiPhanBien?->hoTen }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="ghi-chu">Không có đề nào đang chờ duyệt.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Đã xử lý gần đây</h3>
    <table>
        <thead><tr><th>Mã</th><th>Tên đề</th><th>Học phần</th><th>Kết quả</th><th>Ngày</th></tr></thead>
        <tbody>
            @forelse ($daXuLy as $d)
                <tr>
                    <td><a href="{{ route('phe-duyet.show', $d->maDeThi) }}">#{{ $d->maDeThi }}</a></td>
                    <td>{{ $d->tenDeThi }}</td>
                    <td>{{ $d->maTranDeThi?->maHocPhan }}</td>
                    <td>{{ $d->trangThai }}</td>
                    <td>{{ $d->ngayDuyet?->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="ghi-chu">Chưa có.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
