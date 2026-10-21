<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KiemTraVaiTro
{
    /**
     * Dùng: ->middleware('vaitro:Quản trị viên,Trưởng bộ môn')
     */
    public function handle(Request $request, Closure $next, string ...$vaiTros): Response
    {
        $nguoiDung = $request->user();

        if (! $nguoiDung || ! in_array($nguoiDung->vaiTro?->tenVaiTro, $vaiTros, true)) {
            abort(403, 'Bạn không có quyền truy cập chức năng này.');
        }

        return $next($request);
    }
}
