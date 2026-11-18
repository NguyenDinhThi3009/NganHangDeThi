@extends('layouts.app')

@section('tieu_de', 'Đề thi - Ngân hàng đề thi')

@section('noi_dung')
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
        <h2 style="margin:0;">Đề thi</h2>
        <a class="nut" href="{{ route('de-thi.create') }}">+ Tạo đề thi</a>
    </div>

    <form method="GET" action="{{ route('de-thi.index') }}" style="display:flex; gap:10px; align-items:center; margin-bottom:14px;">
        <select name="trang_thai" style="width:200px;" onchange="this.form.submit()">
            <option value="">Mọi trạng thái</option>
            @foreach ($trangThais as $tt)
                <option value="{{ $tt }}" @selected($locTrangThai === $tt)>{{ $tt }}</option>
            @endforeach
        </select>
        <select name="hinh_thuc" style="width:180px;" onchange="this.form.submit()">
            <option value="">Mọi hình thức</option>
            @foreach ($hinhThucs as $ht)
                <option value="{{ $ht }}" @selected($locHinhThuc === $ht)>{{ $ht }}</option>
            @endforeach
        </select>
        @if ($locTrangThai || $locHinhThuc)
            <a href="{{ route('de-thi.index') }}">Bỏ lọc</a>
        @endif
    </form>

    @if ($hocPhanXuat->isNotEmpty())
        <form method="GET" action="{{ route('de-thi.xuat-ngan-hang') }}" class="the" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:14px;">
            <strong>Xuất ngân hàng đề thi (các đề đã duyệt):</strong>
            <select name="hoc_phan" style="width:260px;">
                @foreach ($hocPhanXuat as $h)
                    <option value="{{ $h->maHocPhan }}">{{ $h->maHocPhan }} - {{ $h->tenHocPhan }}</option>
                @endforeach
            </select>
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

    <table>
        <thead>
            <tr>
                <th>Mã</th>
                <th>Tên đề</th>
                <th>Học phần</th>
                <th>Hình thức</th>
                <th>Số câu</th>
                <th>Trạng thái</th>
                <th>Người tạo</th>
                <th>Ngày tạo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($deThis as $d)
                <tr>
                    <td><a href="{{ route('de-thi.show', $d->maDeThi) }}">#{{ $d->maDeThi }}</a></td>
                    <td><a href="{{ route('de-thi.show', $d->maDeThi) }}">{{ $d->tenDeThi }}</a></td>
                    <td>{{ $d->maTranDeThi?->maHocPhan }} - {{ $d->maTranDeThi?->hocPhan?->tenHocPhan }}</td>
                    <td>{{ $d->hinhThucThi }}</td>
                    <td>{{ $d->de_thi_cau_hois_count }}</td>
                    <td><strong>{{ $d->trangThai }}</strong></td>
                    <td>{{ $d->nguoiTao?->hoTen }}</td>
                    <td>{{ $d->ngayTao?->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="ghi-chu">Chưa có đề thi nào.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
