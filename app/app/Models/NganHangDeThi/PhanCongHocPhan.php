<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhanCongHocPhan extends Model
{
    protected $table = 'phanconghocphan';

    protected $primaryKey = 'maPhanCong';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'maHocPhan',
        'maNguoiDung',
        'vaiTroPhanCong',
        'hocKy',
        'namHoc',
        'trangThai',
    ];

    public function hocPhan(): BelongsTo
    {
        return $this->belongsTo(HocPhan::class, 'maHocPhan', 'maHocPhan');
    }

    public function nguoiDung(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'maNguoiDung', 'maNguoiDung');
    }
}
