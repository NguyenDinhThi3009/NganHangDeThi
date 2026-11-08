@extends('layouts.app')

@section('tieu_de', 'Đọc Bản mô tả CTĐT - Ngân hàng đề thi')

@if (($duLieu['trang_thai'] ?? null) === 'Đang đọc')
    @push('head')
        {{-- Đang đọc nền: tự tải lại trang mỗi 10 giây --}}
        <meta http-equiv="refresh" content="10">
    @endpush
@endif

@section('noi_dung')
    <p><a href="{{ route('ban-mo-ta.nhap') }}">&larr; Tải Bản mô tả khác</a></p>

    @if ($duLieu['trang_thai'] === 'Đang đọc')
        <div class="the">
            <h2>Đang đọc Bản mô tả...</h2>
            <p>{{ $duLieu['thong_bao'] }}</p>
            <p class="ghi-chu">
                File: {{ $duLieu['ten_file'] }}, {{ $duLieu['trang'] !== '' ? 'trang '.$duLieu['trang'] : 'tự tìm trang cần đọc' }}. Trang tự cập nhật mỗi 10 giây.
                Nếu chờ quá 10 phút, hãy kiểm tra terminal <code>queue:work</code> và RAG service đang chạy.
            </p>
        </div>
    @elseif ($duLieu['trang_thai'] === 'Lỗi')
        <div class="the">
            <h2>Không đọc được Bản mô tả</h2>
            <div class="loi-khung">{{ $duLieu['thong_bao'] }}</div>
            <a class="nut" href="{{ route('ban-mo-ta.nhap') }}">Thử lại</a>
        </div>
    @else
        <div class="the">
            <h2>Xem trước nội dung đọc được từ Bản mô tả</h2>
            <p class="ghi-chu">
                File: {{ $duLieu['ten_file'] }}, đã đọc trang {{ $ketQua['trang_da_doc'] ?? '?' }}. Đọc bằng {{ $ketQua['ten_model'] ?? '?' }}
                ({{ $ketQua['so_token_vao'] ?? '?' }} token vào, {{ $ketQua['so_token_ra'] ?? '?' }} token ra).
                AI có thể đọc sai chữ, hãy đối chiếu với bản gốc trước khi lưu.
            </p>

            @if (! empty($ketQua['canh_bao']))
                <div class="canh-bao">
                    <strong>Lưu ý:</strong>
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

            <form method="POST" action="{{ route('ban-mo-ta.xac-nhan', $token) }}">
                @csrf

                <h3>Lưu vào</h3>
                <select name="ctdt_dich">
                    <option value="moi" @selected((string) old('ctdt_dich', $ctdtMacDinh ?? 'moi') === 'moi')>Tạo chương trình đào tạo mới</option>
                    @foreach ($ctdts as $ct)
                        <option value="{{ $ct->maChuongTrinhDaoTao }}" @selected((string) old('ctdt_dich', $ctdtMacDinh ?? 'moi') === (string) $ct->maChuongTrinhDaoTao)>Cập nhật: {{ $ct->tenChuongTrinhDaoTao }}</option>
                    @endforeach
                </select>
                <div class="ghi-chu" style="margin-top:4px;">Nếu chọn cập nhật, thông tin chương trình được ghi đè; PLO cùng ký hiệu được ghi đè nội dung, PLO chưa có thì thêm mới, không PLO nào bị xóa.</div>

                <h3>Thông tin chung</h3>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0 18px;">
                    <div>
                        <label for="ten_chuong_trinh">Tên chương trình đào tạo</label>
                        <input type="text" id="ten_chuong_trinh" name="ten_chuong_trinh" value="{{ old('ten_chuong_trinh', $tenMacDinh) }}" maxlength="255" required>
                    </div>
                    <div>
                        <label for="ten_tieng_anh">Tên tiếng Anh</label>
                        <input type="text" id="ten_tieng_anh" name="ten_tieng_anh" value="{{ old('ten_tieng_anh', $ketQua['ten_tieng_anh'] ?? '') }}" maxlength="255">
                    </div>
                </div>
                <div class="ghi-chu" style="margin-top:4px;">Tên có kèm mã ngành trong ngoặc (ví dụ "Công nghệ thông tin (7480201)") để hệ thống tự chọn đúng chương trình khi nhập đề cương.</div>

                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:0 18px;">
                    <div>
                        <label for="trinh_do">Trình độ đào tạo</label>
                        <input type="text" id="trinh_do" name="trinh_do" value="{{ old('trinh_do', $ketQua['trinh_do'] ?? '') }}" maxlength="50" required>
                    </div>
                    <div>
                        <label for="tong_so_tin_chi">Tổng số tín chỉ</label>
                        <input type="text" id="tong_so_tin_chi" name="tong_so_tin_chi" value="{{ old('tong_so_tin_chi', $ketQua['tong_so_tin_chi'] ?? '') }}" required>
                    </div>
                    <div>
                        <label for="khoa_quan_ly">Khoa quản lý</label>
                        <input type="text" id="khoa_quan_ly" name="khoa_quan_ly" value="{{ old('khoa_quan_ly', $ketQua['khoa_quan_ly'] ?? '') }}" maxlength="100" required>
                    </div>
                    <div>
                        <label for="khoa_hoc">Khóa học áp dụng</label>
                        <input type="text" id="khoa_hoc" name="khoa_hoc" value="{{ old('khoa_hoc', $khoaHocMacDinh) }}" maxlength="20" placeholder="nhập tay" required>
                    </div>
                </div>
                @if ($khoaHocTuNam)
                    <div class="ghi-chu" style="margin-top:4px;">
                        Bản mô tả không ghi khóa học. Gợi ý <strong>{{ $khoaHocMacDinh }}</strong> được suy ra từ năm ban hành {{ $ketQua['nam_ban_hanh'] }} ghi trên bìa
                        (khóa tuyển sinh {{ $ketQua['nam_ban_hanh'] }}). Hãy sửa nếu quy ước của khoa khác.
                    </div>
                @endif

                <h3>Chuẩn đầu ra chương trình (PLO)</h3>
                <p class="ghi-chu">Sửa trực tiếp nếu AI đọc sai chữ. Dòng để trống cả ký hiệu và nội dung sẽ bị bỏ qua.</p>
                <table>
                    <thead><tr><th style="width:100px;">Ký hiệu</th><th>Nội dung PLO</th></tr></thead>
                    <tbody>
                        @for ($i = 0; $i < $soDong; $i++)
                            @php
                                $plo = $ketQua['plo'][$i] ?? null;
                            @endphp
                            <tr>
                                <td><input type="text" name="plo_ky_hieu[{{ $i }}]" value="{{ old('plo_ky_hieu.'.$i, $plo['ky_hieu'] ?? '') }}" maxlength="10" placeholder="PLO{{ $i + 1 }}"></td>
                                <td><textarea name="plo_mo_ta[{{ $i }}]" rows="2">{{ old('plo_mo_ta.'.$i, $plo['mo_ta'] ?? '') }}</textarea></td>
                            </tr>
                        @endfor
                    </tbody>
                </table>

                <div style="margin-top:22px;">
                    <button type="submit" class="nut-xanh">Xác nhận và lưu</button>
                    <a href="{{ route('ban-mo-ta.nhap') }}" style="margin-left:12px;">Hủy</a>
                </div>
            </form>
        </div>
    @endif
@endsection
