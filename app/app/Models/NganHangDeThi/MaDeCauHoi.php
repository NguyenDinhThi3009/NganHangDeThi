<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaDeCauHoi extends Model
{
    protected $table = 'madecauhoi';

    protected $primaryKey = 'maMaDeCauHoi';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'maMaDe',
        'maDeThiCauHoi',
        'thuTuCauMoi',
        'thuTuDapAn',
    ];

    protected function casts(): array
    {
        return [
            'thuTuDapAn' => 'array',
        ];
    }

    public function maDe(): BelongsTo
    {
        return $this->belongsTo(MaDeTracNghiem::class, 'maMaDe', 'maMaDe');
    }

    public function deThiCauHoi(): BelongsTo
    {
        return $this->belongsTo(DeThiCauHoi::class, 'maDeThiCauHoi', 'maDeThiCauHoi');
    }
}
