<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeThi extends Model
{
    protected $table = 'dethi';

    protected $primaryKey = 'maDeThi';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'tenDeThi',
        'maMaTranDeThi',
        'maNguoiTao',
        'ngayTao',
        'trangThai',
        'hinhThucThi',
        'thoiGianLamBai',
        'thoiGianChuanBi',
        'thoiGianTraLoi',
        'maNguoiPhanBien',
        'ngayPhanBien',
        'ketQuaPhanBien',
        'maNguoiDuyet',
        'ngayDuyet',
    ];

    protected function casts(): array
    {
        return [
            'ngayTao' => 'datetime',
            'ngayPhanBien' => 'datetime',
            'ngayDuyet' => 'datetime',
        ];
    }

    public function maTranDeThi(): BelongsTo
    {
        return $this->belongsTo(MaTranDeThi::class, 'maMaTranDeThi', 'maMaTranDeThi');
    }

    public function nguoiTao(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'maNguoiTao', 'maNguoiDung');
    }

    public function nguoiPhanBien(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'maNguoiPhanBien', 'maNguoiDung');
    }

    public function nguoiDuyet(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'maNguoiDuyet', 'maNguoiDung');
    }

    public function deThiCauHois(): HasMany
    {
        return $this->hasMany(DeThiCauHoi::class, 'maDeThi', 'maDeThi');
    }

    public function suDungDeThis(): HasMany
    {
        return $this->hasMany(SuDungDeThi::class, 'maDeThi', 'maDeThi');
    }
}
