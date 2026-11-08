<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('tieu_de', 'Ngân hàng đề thi')</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f3f4f6; color: #111827; }
        header { background: #1e3a8a; color: #fff; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #fff; text-decoration: none; margin-right: 18px; }
        header strong a { margin-right: 28px; }
        main { padding: 24px; max-width: 1100px; margin: 0 auto; }
        h2 { margin-top: 0; }
        .the { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        label { display: block; margin: 14px 0 4px; font-size: 14px; font-weight: bold; }
        input[type=text], select, input[type=file], textarea { width: 100%; padding: 9px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; background: #fff; }
        button, .nut { display: inline-block; padding: 9px 16px; background: #2563eb; color: #fff; border: 0; border-radius: 4px; font-size: 14px; cursor: pointer; text-decoration: none; }
        header button { background: #fff; color: #1e3a8a; padding: 6px 14px; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { padding: 9px 10px; border-bottom: 1px solid #e5e7eb; text-align: left; font-size: 14px; vertical-align: top; }
        th { background: #f9fafb; }
        .loi { color: #b91c1c; font-size: 13px; margin-top: 4px; }
        .thanh-cong { background: #dcfce7; color: #166534; padding: 10px 14px; border-radius: 4px; margin-bottom: 16px; }
        .ghi-chu { color: #6b7280; font-size: 13px; }
        .canh-bao { background: #fef9c3; color: #854d0e; padding: 10px 14px; border-radius: 4px; margin-bottom: 16px; }
        .loi-khung { background: #fee2e2; color: #991b1b; padding: 10px 14px; border-radius: 4px; margin-bottom: 16px; }
        .dap-an-dung { color: #166534; font-weight: bold; }
        .nut-do { background: #b91c1c; }
        .nut-xanh { background: #15803d; }
        .nut-xam { background: #4b5563; }
        .hang-nut { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-start; margin: 14px 0 4px; }
        .hang-nut form { margin: 0; }
        .nut-nho { padding: 4px 10px; font-size: 12px; background: #b45309; }
    </style>
    @stack('head')
</head>
<body>
    <header>
        <div>
            <strong><a href="{{ route('trang-chu') }}">Ngân hàng đề thi</a></strong>
            <a href="{{ route('trang-chu') }}">Trang chủ</a>
            @if (in_array(auth()->user()->vaiTro->tenVaiTro, ['Giảng viên', 'Quản trị viên', 'Phòng ĐT&ĐBCLGD'], true))
                <a href="{{ route('tai-lieu.index') }}">Tài liệu</a>
            @endif
            @if (in_array(auth()->user()->vaiTro->tenVaiTro, ['Giảng viên', 'Quản trị viên'], true))
                <a href="{{ route('cau-hoi.index') }}">Câu hỏi</a>
                <a href="{{ route('ma-tran.index') }}">Ma trận đề</a>
            @endif
            @if (auth()->user()->vaiTro->tenVaiTro === 'Quản trị viên')
                <a href="{{ route('chuong-trinh.index') }}">Chương trình đào tạo</a>
                <a href="{{ route('hoc-phan.index') }}">Học phần</a>
            @endif
        </div>
        <form method="POST" action="{{ route('dang-xuat') }}">
            @csrf
            <button type="submit">Đăng xuất</button>
        </form>
    </header>
    <main>
        @if (session('thanh_cong'))
            <div class="thanh-cong">{{ session('thanh_cong') }}</div>
        @endif
        @if (session('canh_bao'))
            <div class="canh-bao">{{ session('canh_bao') }}</div>
        @endif
        @if (session('loi'))
            <div class="loi-khung">{{ session('loi') }}</div>
        @endif
        @yield('noi_dung')
    </main>
</body>
</html>
