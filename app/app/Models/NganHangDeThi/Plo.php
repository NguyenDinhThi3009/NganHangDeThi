<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plo extends Model
{
    protected $table = 'plo';

    protected $primaryKey = 'maPlo';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'moTa',
        'maChuongTrinhDaoTao',
        'kyHieu',
    ];

    public function chuongTrinhDaoTao(): BelongsTo
    {
        return $this->belongsTo(ChuongTrinhDaoTao::class, 'maChuongTrinhDaoTao', 'maChuongTrinhDaoTao');
    }

    public function hocPhanPlos(): HasMany
    {
        return $this->hasMany(HocPhanPlo::class, 'maPlo', 'maPlo');
    }

    public function cloPlos(): HasMany
    {
        return $this->hasMany(CloPlo::class, 'maPlo', 'maPlo');
    }
}
