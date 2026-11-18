@extends('layouts.app')

@section('tieu_de', 'Tạo đề thi - Ngân hàng đề thi')

@section('noi_dung')
    <p><a href="{{ route('de-thi.index') }}">&larr; Danh sách đề thi</a></p>

    <div class="the">
        <h2>Tạo bộ đề thi</h2>

        @if ($errors->any())
            <div class="loi-khung">
                <strong>Chưa tạo được đề thi:</strong>
                <ul style="margin:6px 0 0 18px; padding:0;">
                    @foreach ($errors->all() as $loi)
                        <li>{{ $loi }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($maTrans->isEmpty())
            <div class="canh-bao">
                Chưa có ma trận nào ở trạng thái <strong>Đã chốt</strong>. Hãy vào <a href="{{ route('ma-tran.index') }}">Ma trận đề</a>, tạo ma trận và bấm Chốt.
            </div>
        @else
            <form method="POST" action="{{ route('de-thi.tao-nhap') }}">
                @csrf

                <label for="ma_tran">Ma trận đề (đã chốt)</label>
                <select name="ma_tran" id="ma_tran" required>
                    <option value="">-- Chọn ma trận --</option>
                    @foreach ($maTrans as $mt)
                        <option value="{{ $mt->maMaTranDeThi }}" @selected((string) old('ma_tran', $maTranChon) === (string) $mt->maMaTranDeThi)>
                            #{{ $mt->maMaTranDeThi }} - {{ $mt->tenMaTran }} ({{ $mt->maHocPhan }} - {{ $mt->hocPhan?->tenHocPhan }}, {{ (int) $mt->tong_so_cau }} câu)
                        </option>
                    @endforeach
                </select>
                <div class="ghi-chu">Học phần được lấy theo ma trận. Hệ thống chỉ chọn câu hỏi đã duyệt của học phần đó.</div>

                <label for="hinh_thuc">Hình thức thi</label>
                <select name="hinh_thuc" id="hinh_thuc" required>
                    @foreach ($hinhThucs as $ht)
                        <option value="{{ $ht }}" @selected(old('hinh_thuc', 'Trắc nghiệm') === $ht)>{{ $ht }}</option>
                    @endforeach
                </select>

                <label for="ten">Tên đề thi</label>
                <input type="text" name="ten" id="ten" value="{{ old('ten') }}" maxlength="255" required placeholder="Ví dụ: Đề trắc nghiệm Cấu trúc máy tính - đề 01">

                <div id="khung-lam-bai">
                    <label for="lam_bai">Thời gian làm bài (phút)</label>
                    <input type="text" name="lam_bai" id="lam_bai" value="{{ old('lam_bai') }}" inputmode="numeric" style="width:140px;">
                    <div class="ghi-chu" id="goi-y-lam-bai"></div>
                </div>

                <div id="khung-van-dap" style="display:none;">
                    <label for="chuan_bi">Thời gian chuẩn bị (phút, tối đa 15)</label>
                    <input type="text" name="chuan_bi" id="chuan_bi" value="{{ old('chuan_bi') }}" inputmode="numeric" style="width:140px;">
                    <label for="tra_loi">Thời gian trình bày (phút, tối đa 7)</label>
                    <input type="text" name="tra_loi" id="tra_loi" value="{{ old('tra_loi') }}" inputmode="numeric" style="width:140px;">
                    <label for="so_bat_buoc">Số câu hỏi bắt buộc (1 đến 2)</label>
                    <input type="text" name="so_bat_buoc" id="so_bat_buoc" value="{{ old('so_bat_buoc', 2) }}" inputmode="numeric" style="width:140px;">
                    <label for="diem_bat_buoc">Điểm của phần câu hỏi bắt buộc (còn lại dành cho câu hỏi thêm)</label>
                    <input type="text" name="diem_bat_buoc" id="diem_bat_buoc" value="{{ old('diem_bat_buoc', 7) }}" inputmode="decimal" style="width:140px;">
                    <div class="ghi-chu">Câu bắt buộc là các câu có mức Bloom thấp nhất trong ma trận, các câu còn lại là câu hỏi thêm. Chỉ chọn được câu loại Vấn đáp trong ngân hàng.</div>
                </div>

                <div class="hang-nut" style="margin-top:20px;">
                    <button type="submit">Tạo bộ đề thi</button>
                </div>
            </form>

            <script>
                (function () {
                    var hinhThuc = document.getElementById('hinh_thuc');
                    var goiY = {
                        'Trắc nghiệm': 'Đề trắc nghiệm: 30 đến 60 câu, thời gian 30 đến 60 phút.',
                        'Tự luận': 'Đề tự luận: ít nhất 2 câu, thời gian 30 đến 75 phút, mỗi ý đáp án tối đa 0,5 điểm.'
                    };
                    function capNhat() {
                        var vanDap = hinhThuc.value === 'Vấn đáp';
                        document.getElementById('khung-lam-bai').style.display = vanDap ? 'none' : '';
                        document.getElementById('khung-van-dap').style.display = vanDap ? '' : 'none';
                        document.getElementById('goi-y-lam-bai').textContent = goiY[hinhThuc.value] || '';
                    }
                    hinhThuc.addEventListener('change', capNhat);
                    capNhat();
                })();
            </script>
        @endif
    </div>
@endsection
