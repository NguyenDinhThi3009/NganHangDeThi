<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CauHoi extends Model
{
    protected $table = 'cauhoi';

    protected $primaryKey = 'maCauHoi';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'maHocPhan',
        'noiDungCauHoi',
        'loaiCauHoi',
        'mucDoBloom',
        'doKho',
        'doPhanHoa',
        'nguonGoc',
        'trangThaiDuyet',
        'maClo',
        'maDoanTaiLieu',
        'maNguoiDuyet',
        'maNguoiTao',
        'ngayTao',
        'ngayDuyet',
        'vectorId',
        'modelEmbedding',
        'maNhatKyLLM',
    ];

    protected function casts(): array
    {
        return [
            'doPhanHoa' => 'decimal:2',
            'ngayTao' => 'datetime',
            'ngayDuyet' => 'datetime',
        ];
    }

    public function hocPhan(): BelongsTo
    {
        return $this->belongsTo(HocPhan::class, 'maHocPhan', 'maHocPhan');
    }

    public function clo(): BelongsTo
    {
        return $this->belongsTo(Clo::class, 'maClo', 'maClo');
    }

    public function doanTaiLieu(): BelongsTo
    {
        return $this->belongsTo(DoanTaiLieu::class, 'maDoanTaiLieu', 'maDoanTaiLieu');
    }

    public function nguoiDuyet(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'maNguoiDuyet', 'maNguoiDung');
    }

    public function nguoiTao(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'maNguoiTao', 'maNguoiDung');
    }

    public function nhatKyLLM(): BelongsTo
    {
        return $this->belongsTo(NhatKyGoiLLM::class, 'maNhatKyLLM', 'maNhatKyLLM');
    }

    public function dapAnTracNghiems(): HasMany
    {
        return $this->hasMany(DapAnTracNghiem::class, 'maCauHoi', 'maCauHoi');
    }

    public function kiemTraTrungLapTheoCauHoi(): HasMany
    {
        return $this->hasMany(KiemTraTrungLap::class, 'maCauHoi', 'maCauHoi');
    }

    public function kiemTraTrungLapTheoCauHoiTrung(): HasMany
    {
        return $this->hasMany(KiemTraTrungLap::class, 'maCauHoiTrung', 'maCauHoi');
    }

    public function deThiCauHois(): HasMany
    {
        return $this->hasMany(DeThiCauHoi::class, 'maCauHoi', 'maCauHoi');
    }

    public function dapAnTuLuanYs(): HasMany
    {
        return $this->hasMany(DapAnTuLuanY::class, 'maCauHoi', 'maCauHoi');
    }
}
