@extends('layouts.app')

@section('tieu_de', 'Nhập từ Bản mô tả CTĐT - Ngân hàng đề thi')

@section('noi_dung')
    <p><a href="{{ route('chuong-trinh.index') }}">&larr; Danh sách chương trình đào tạo</a></p>

    <div class="the" style="max-width:700px;">
        <h2>Nhập chương trình đào tạo từ Bản mô tả (PDF)</h2>
        <p class="ghi-chu" style="line-height:1.6;">
            Hệ thống chuyển các trang thành ảnh rồi nhờ AI đọc, nên dùng được cả với bản scan.
            Mặc định <strong>tự tìm</strong>: đọc từ đầu file theo từng lô 12 trang và dừng ngay khi đã có thông tin chung
            (tên chương trình, mã ngành, số tín chỉ, khoa quản lý) và danh sách PLO, nên bạn không cần chọn trang.
            Việc đọc chạy nền và mất vài phút. Bạn sẽ được xem lại và chỉnh sửa kết quả trước khi lưu.
        </p>

        @if ($errors->any())
            <div class="loi-khung">
                <ul style="margin:0; padding-left:18px;">
                    @foreach ($errors->all() as $loi)
                        <li>{{ $loi }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('ban-mo-ta.tai') }}" enctype="multipart/form-data">
            @csrf
            <label for="file">File Bản mô tả (.pdf, tối đa 50 MB)</label>
            <input type="file" id="file" name="file" accept=".pdf" required>

            <label for="trang">Chỉ đọc các trang này (không bắt buộc)</label>
            <input type="text" id="trang" name="trang" value="{{ old('trang') }}" placeholder="Để trống để hệ thống tự tìm">
            <div class="ghi-chu">Chỉ điền khi tự tìm không ra, ví dụ 2,4 hoặc 2-4 (số thứ tự trang trong file PDF, tính từ 1, tối đa 12 trang).</div>

            <div style="margin-top:20px;">
                <button type="submit">Bắt đầu đọc</button>
                <a href="{{ route('chuong-trinh.index') }}" style="margin-left:12px;">Hủy</a>
            </div>
        </form>
    </div>
@endsection
