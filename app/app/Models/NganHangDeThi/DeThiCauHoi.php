<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeThiCauHoi extends Model
{
    protected $table = 'dethicauhoi';

    protected $primaryKey = 'maDeThiCauHoi';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'maDeThi',
        'maCauHoi',
        'thuTuCau',
        'phan',
        'diemSo',
    ];

    protected function casts(): array
    {
        return [
            'diemSo' => 'decimal:2',
        ];
    }

    public function deThi(): BelongsTo
    {
        return $this->belongsTo(DeThi::class, 'maDeThi', 'maDeThi');
    }

    public function cauHoi(): BelongsTo
    {
        return $this->belongsTo(CauHoi::class, 'maCauHoi', 'maCauHoi');
    }

    public function maDeCauHois(): HasMany
    {
        return $this->hasMany(MaDeCauHoi::class, 'maDeThiCauHoi', 'maDeThiCauHoi');
    }
}
