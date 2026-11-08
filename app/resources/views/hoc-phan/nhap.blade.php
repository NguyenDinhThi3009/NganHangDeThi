@extends('layouts.app')

@section('tieu_de', 'Nhập học phần từ đề cương - Ngân hàng đề thi')

@section('noi_dung')
    <p><a href="{{ route('hoc-phan.index') }}">&larr; Danh sách học phần</a></p>

    <div class="the" style="max-width:680px;">
        <h2>Nhập học phần từ đề cương chi tiết</h2>
        <p class="ghi-chu" style="line-height:1.5;">
            Hệ thống đọc file đề cương (.docx) để lấy thông tin học phần, các chuẩn đầu ra (CLO), chỉ số PI và ma trận CLO - PLO.
            Bạn sẽ được xem lại và chỉnh sửa trước khi lưu. Nếu mã học phần đã có thì học phần và các CLO cùng ký hiệu sẽ được cập nhật.
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

        <form method="POST" action="{{ route('hoc-phan.doc-de-cuong') }}" enctype="multipart/form-data">
            @csrf
            <label for="file">File đề cương (.docx, tối đa 20 MB)</label>
            <input type="file" id="file" name="file" accept=".docx" required>

            <div style="margin-top:20px;">
                <button type="submit">Đọc đề cương</button>
                <a href="{{ route('hoc-phan.index') }}" style="margin-left:12px;">Hủy</a>
            </div>
        </form>
    </div>
@endsection
