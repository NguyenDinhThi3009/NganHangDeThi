<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HocPhan extends Model
{
    protected $table = 'hocphan';

    protected $primaryKey = 'maHocPhan';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'maHocPhan',
        'tenHocPhan',
        'soTinChi',
        'soTinChiLT',
        'soTinChiTH',
        'soTinChiThucTap',
        'loaiHocPhan',
        'hocKyDuKien',
        'maChuongTrinhDaoTao',
        'maBoMon',
    ];

    public function chuongTrinhDaoTao(): BelongsTo
    {
        return $this->belongsTo(ChuongTrinhDaoTao::class, 'maChuongTrinhDaoTao', 'maChuongTrinhDaoTao');
    }

    public function boMon(): BelongsTo
    {
        return $this->belongsTo(BoMon::class, 'maBoMon', 'maBoMon');
    }

    public function clos(): HasMany
    {
        return $this->hasMany(Clo::class, 'maHocPhan', 'maHocPhan');
    }

    public function taiLieus(): HasMany
    {
        return $this->hasMany(TaiLieu::class, 'maHocPhan', 'maHocPhan');
    }

    public function maTranDeThis(): HasMany
    {
        return $this->hasMany(MaTranDeThi::class, 'maHocPhan', 'maHocPhan');
    }

    public function cauHois(): HasMany
    {
        return $this->hasMany(CauHoi::class, 'maHocPhan', 'maHocPhan');
    }

    public function hocPhanPlos(): HasMany
    {
        return $this->hasMany(HocPhanPlo::class, 'maHocPhan', 'maHocPhan');
    }

    public function phanCongHocPhans(): HasMany
    {
        return $this->hasMany(PhanCongHocPhan::class, 'maHocPhan', 'maHocPhan');
    }
}
