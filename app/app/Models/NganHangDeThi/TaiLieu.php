<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaiLieu extends Model
{
    protected $table = 'tailieu';

    protected $primaryKey = 'maTaiLieu';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'tenTaiLieu',
        'duongDanFile',
        'dinhDang',
        'loaiTaiLieu',
        'trangThaiXuLy',
        'maHocPhan',
        'maNguoiTaiLen',
        'ngayTaiLen',
    ];

    protected function casts(): array
    {
        return [
            'ngayTaiLen' => 'datetime',
        ];
    }

    public function hocPhan(): BelongsTo
    {
        return $this->belongsTo(HocPhan::class, 'maHocPhan', 'maHocPhan');
    }

    public function nguoiTaiLen(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'maNguoiTaiLen', 'maNguoiDung');
    }

    public function doanTaiLieus(): HasMany
    {
        return $this->hasMany(DoanTaiLieu::class, 'maTaiLieu', 'maTaiLieu');
    }
}
