<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaTranDeThi extends Model
{
    protected $table = 'matrandethi';

    protected $primaryKey = 'maMaTranDeThi';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'tenMaTran',
        'maHocPhan',
        'maNguoiTao',
        'ngayTao',
        'trangThai',
        'nguongTrungLap',
    ];

    protected function casts(): array
    {
        return [
            'ngayTao' => 'datetime',
            'nguongTrungLap' => 'decimal:4',
        ];
    }

    public function hocPhan(): BelongsTo
    {
        return $this->belongsTo(HocPhan::class, 'maHocPhan', 'maHocPhan');
    }

    public function nguoiTao(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'maNguoiTao', 'maNguoiDung');
    }

    public function chiTietMaTrans(): HasMany
    {
        return $this->hasMany(ChiTietMaTran::class, 'maMaTranDeThi', 'maMaTranDeThi');
    }

    public function deThis(): HasMany
    {
        return $this->hasMany(DeThi::class, 'maMaTranDeThi', 'maMaTranDeThi');
    }
}
