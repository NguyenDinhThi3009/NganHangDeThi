<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NguoiDung extends Model
{
    protected $table = 'nguoidung';

    protected $primaryKey = 'maNguoiDung';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'maNguoiDung',
        'hoTen',
        'email',
        'matKhauMaHoa',
        'maVaiTro',
        'maBoMon',
        'trangThai',
        'trinhDoHocVi',
        'ngayTao',
    ];

    protected $hidden = [
        'matKhauMaHoa',
    ];

    protected function casts(): array
    {
        return [
            'ngayTao' => 'datetime',
        ];
    }

    public function vaiTro(): BelongsTo
    {
        return $this->belongsTo(
            VaiTro::class,
            'maVaiTro',
            'maVaiTro'
        );
    }
}
