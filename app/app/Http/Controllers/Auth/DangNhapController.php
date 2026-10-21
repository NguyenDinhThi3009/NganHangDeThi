<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DangNhapController extends Controller
{
    public function hienThiForm(): View
    {
        return view('auth.dang-nhap');
    }

    public function dangNhap(Request $request): RedirectResponse
    {
        $thongTin = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        // Chỉ tài khoản đang "Hoạt động" mới được đăng nhập
        $thanhCong = Auth::attempt([
            'email' => $thongTin['email'],
            'password' => $thongTin['password'],
            'trangThai' => 'Hoạt động',
        ]);

        if (! $thanhCong) {
            return back()
                ->withErrors(['email' => 'Email hoặc mật khẩu không đúng, hoặc tài khoản đã bị khóa.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('trang-chu'));
    }

    public function dangXuat(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
