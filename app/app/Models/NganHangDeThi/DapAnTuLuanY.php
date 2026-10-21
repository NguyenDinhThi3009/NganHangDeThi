<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DapAnTuLuanY extends Model
{
    protected $table = 'dapantuluany';

    protected $primaryKey = 'maDapAnY';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'maCauHoi',
        'noiDungY',
        'diemY',
        'thuTu',
    ];

    protected function casts(): array
    {
        return [
            'diemY' => 'decimal:2',
        ];
    }

    public function cauHoi(): BelongsTo
    {
        return $this->belongsTo(CauHoi::class, 'maCauHoi', 'maCauHoi');
    }
}
