<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập - Ngân hàng đề thi</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f3f4f6; display: flex; min-height: 100vh; align-items: center; justify-content: center; margin: 0; }
        .khung { background: #fff; padding: 32px; width: 360px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,.1); }
        h1 { font-size: 20px; margin: 0 0 20px; text-align: center; }
        label { display: block; margin: 12px 0 4px; font-size: 14px; }
        input { width: 100%; padding: 9px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; margin-top: 20px; padding: 10px; background: #2563eb; color: #fff; border: 0; border-radius: 4px; font-size: 15px; cursor: pointer; }
        .loi { color: #b91c1c; font-size: 13px; margin-top: 6px; }
    </style>
</head>
<body>
    <div class="khung">
        <h1>Hệ thống ngân hàng đề thi</h1>
        <form method="POST" action="{{ route('dang-nhap') }}">
            @csrf
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email') <div class="loi">{{ $message }}</div> @enderror

            <label for="password">Mật khẩu</label>
            <input id="password" type="password" name="password" required>
            @error('password') <div class="loi">{{ $message }}</div> @enderror

            <button type="submit">Đăng nhập</button>
        </form>
    </div>
</body>
</html>
