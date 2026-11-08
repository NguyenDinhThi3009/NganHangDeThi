@extends('layouts.app')

@section('tieu_de', 'Xem trước đề cương - Ngân hàng đề thi')

@section('noi_dung')
    <p><a href="{{ route('hoc-phan.nhap') }}">&larr; Tải đề cương khác</a></p>

    <div class="the">
        <h2>Xem trước nội dung đọc được từ đề cương</h2>
        <p class="ghi-chu">File: {{ $tenFile }}. Kiểm tra và chỉnh sửa nếu cần, rồi bấm "Xác nhận và lưu" ở cuối trang.</p>

        @if ($hocPhanDaCo)
            <div class="canh-bao">
                Học phần <strong>{{ $hocPhanDaCo->maHocPhan }}</strong> đã có trong hệ thống (hiện có {{ $hocPhanDaCo->clos->count() }} CLO).
                Xác nhận sẽ <strong>cập nhật</strong> thông tin học phần, các CLO cùng ký hiệu và ma trận PLO.
                @if (! empty($closDaCoKhongTrongDeCuong))
                    CLO {{ implode(', ', $closDaCoKhongTrongDeCuong) }} không có trong đề cương này và sẽ được giữ nguyên.
                @endif
            </div>
        @endif

        @if (! empty($ketQua['canh_bao']))
            <div class="canh-bao">
                <strong>Lưu ý khi đọc đề cương:</strong>
                <ul style="margin:6px 0 0; padding-left:18px;">
                    @foreach ($ketQua['canh_bao'] as $cb)
                        <li>{{ $cb }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($errors->any())
            <div class="loi-khung">
                <ul style="margin:0; padding-left:18px;">
                    @foreach ($errors->all() as $loi)
                        <li>{{ $loi }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('hoc-phan.xac-nhan', $token) }}">
            @csrf

            <h3>Thông tin học phần</h3>
            <div style="display:grid; grid-template-columns: 1fr 2fr; gap:0 18px;">
                <div>
                    <label for="ma_hoc_phan">Mã học phần</label>
                    <input type="text" id="ma_hoc_phan" name="ma_hoc_phan" value="{{ old('ma_hoc_phan', $ketQua['ma_hoc_phan']) }}" maxlength="20" required>
                </div>
                <div>
                    <label for="ten_hoc_phan">Tên học phần</label>
                    <input type="text" id="ten_hoc_phan" name="ten_hoc_phan" value="{{ old('ten_hoc_phan', $ketQua['ten_hoc_phan']) }}" maxlength="255" required>
                </div>
            </div>
            @if (! empty($ketQua['ten_tieng_anh']))
                <div class="ghi-chu" style="margin-top:4px;">Tên tiếng Anh trong đề cương: {{ $ketQua['ten_tieng_anh'] }}</div>
            @endif

            <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:0 18px;">
                <div>
                    <label for="so_tin_chi">Tổng số tín chỉ</label>
                    <input type="text" id="so_tin_chi" name="so_tin_chi" value="{{ old('so_tin_chi', $ketQua['so_tin_chi']) }}" required>
                </div>
                <div>
                    <label for="so_tin_chi_lt">Tín chỉ lý thuyết</label>
                    <input type="text" id="so_tin_chi_lt" name="so_tin_chi_lt" value="{{ old('so_tin_chi_lt', $tinChiLt) }}" required>
                </div>
                <div>
                    <label for="so_tin_chi_th">Tín chỉ thực hành</label>
                    <input type="text" id="so_tin_chi_th" name="so_tin_chi_th" value="{{ old('so_tin_chi_th', $tinChiTh) }}" required>
                </div>
                <div>
                    <label for="so_tin_chi_thuc_tap">Tín chỉ thực tập</label>
                    <input type="text" id="so_tin_chi_thuc_tap" name="so_tin_chi_thuc_tap" value="{{ old('so_tin_chi_thuc_tap', 0) }}">
                </div>
            </div>
            <div class="ghi-chu" style="margin-top:4px;">
                Đề cương ghi số tiết: lý thuyết {{ $ketQua['gio_ly_thuyet'] ?? '?' }}, bài tập {{ $ketQua['gio_bai_tap'] ?? '?' }}, thực hành {{ $ketQua['gio_thuc_hanh'] ?? '?' }}, tự học {{ $ketQua['gio_tu_hoc'] ?? '?' }}.
                Tín chỉ lý thuyết/thực hành là gợi ý chia theo tỷ lệ số tiết, hãy sửa nếu khác quy định của trường.
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:0 18px;">
                <div>
                    <label for="loai_hoc_phan">Loại học phần (khối kiến thức)</label>
                    <input type="text" id="loai_hoc_phan" name="loai_hoc_phan" value="{{ old('loai_hoc_phan', $hocPhanDaCo->loaiHocPhan ?? $ketQua['khoi_kien_thuc']) }}" maxlength="50" required>
                </div>
                <div>
                    <label for="hoc_ky_du_kien">Học kỳ dự kiến (đề cương không có)</label>
                    <input type="text" id="hoc_ky_du_kien" name="hoc_ky_du_kien" value="{{ old('hoc_ky_du_kien', $hocKyMacDinh) }}" placeholder="1 - 12" required>
                </div>
                <div>
                    <label for="bo_mon">Bộ môn phụ trách</label>
                    <input type="text" id="bo_mon" name="bo_mon" value="{{ old('bo_mon', $ketQua['bo_mon']) }}" maxlength="255" required>
                </div>
            </div>

            <label for="ma_chuong_trinh_dao_tao">Chương trình đào tạo</label>
            <select id="ma_chuong_trinh_dao_tao" name="ma_chuong_trinh_dao_tao" required>
                @foreach ($ctdts as $ct)
                    <option value="{{ $ct->maChuongTrinhDaoTao }}" @selected((string) old('ma_chuong_trinh_dao_tao', $ctdtMacDinh) === (string) $ct->maChuongTrinhDaoTao)>{{ $ct->tenChuongTrinhDaoTao }}</option>
                @endforeach
            </select>
            <div class="ghi-chu" style="margin-top:4px;">Đề cương thuộc ngành {{ $ketQua['nganh'] ?? '?' }}, mã ngành {{ $ketQua['ma_nganh'] ?? '?' }}. PLO được tra theo chương trình đào tạo này.</div>

            <h3>Chuẩn đầu ra học phần (CLO) và ánh xạ PLO</h3>
            @if (empty($ketQua['clos']))
                <p class="loi">Không đọc được CLO nào từ đề cương.</p>
            @else
                <table>
                    <thead>
                        <tr><th style="width:70px;">CLO</th><th>Mô tả (có thể sửa)</th><th style="width:90px;">Chỉ số PI</th><th style="width:150px;">Ánh xạ PLO (mức)</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($ketQua['clos'] as $clo)
                            <tr>
                                <td><strong>{{ $clo['ky_hieu'] }}</strong></td>
                                <td><textarea name="clo_mo_ta[{{ $clo['ky_hieu'] }}]" rows="3" required>{{ old('clo_mo_ta.'.$clo['ky_hieu'], $clo['mo_ta']) }}</textarea></td>
                                <td>{{ $clo['pi'] ? 'PI'.implode(', PI', $clo['pi']) : '-' }}</td>
                                <td>
                                    @forelse ($clo['plo'] as $p)
                                        {{ $p['ky_hieu'] }} ({{ $p['muc_do'] }})@if (! $loop->last), @endif
                                    @empty
                                        <span class="loi">chưa có</span>
                                    @endforelse
                                    @if ($clo['suy_ra_tu_pi'] ?? false)
                                        <div class="ghi-chu">(suy ra từ chỉ số PI)</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <h3>Ma trận học phần - PLO (dòng Tổng hợp)</h3>
            <p>
                @forelse ($ketQua['tong_hop'] as $p)
                    <strong>{{ $p['ky_hieu'] }}</strong> ({{ $p['muc_do'] }})@if (! $loop->last), @endif
                @empty
                    <span class="loi">Không đọc được ma trận PLO.</span>
                @endforelse
            </p>
            <div class="ghi-chu">Mức: I (Giới thiệu), R (Củng cố), M (Thành thạo). Ánh xạ CLO - PLO lấy từ đề cương; muốn đổi hãy sửa file đề cương rồi tải lại.</div>

            <div style="margin-top:22px;">
                <button type="submit" class="nut-xanh">Xác nhận và lưu</button>
                <a href="{{ route('hoc-phan.nhap') }}" style="margin-left:12px;">Hủy</a>
            </div>
        </form>
    </div>
@endsection
