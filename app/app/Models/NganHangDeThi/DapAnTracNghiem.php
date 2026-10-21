<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DapAnTracNghiem extends Model
{
    protected $table = 'dapantracnghiem';

    protected $primaryKey = 'maDapAnTracNghiem';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'maCauHoi',
        'noiDungDapAn',
        'laDapAnDung',
        'thuTu',
    ];

    protected function casts(): array
    {
        return [
            'laDapAnDung' => 'boolean',
        ];
    }

    public function cauHoi(): BelongsTo
    {
        return $this->belongsTo(CauHoi::class, 'maCauHoi', 'maCauHoi');
    }
}
