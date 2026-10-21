<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trang chủ - Ngân hàng đề thi</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f3f4f6; }
        header { background: #1e3a8a; color: #fff; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; }
        main { padding: 24px; }
        button { padding: 6px 14px; cursor: pointer; }
    </style>
</head>
<body>
    <header>
        <strong>Ngân hàng đề thi</strong>
        <form method="POST" action="{{ route('dang-xuat') }}">
            @csrf
            <button type="submit">Đăng xuất</button>
        </form>
    </header>
    <main>
        <h2>Xin chào, {{ auth()->user()->hoTen }}</h2>
        <p>Vai trò: <strong>{{ auth()->user()->vaiTro->tenVaiTro }}</strong></p>

        @if (auth()->user()->vaiTro->tenVaiTro === 'Quản trị viên')
            <p><a href="{{ route('quan-tri') }}">Khu vực Quản trị viên (thử phân quyền)</a></p>
        @endif
    </main>
</body>
</html>
