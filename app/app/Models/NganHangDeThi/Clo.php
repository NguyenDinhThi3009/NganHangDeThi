<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clo extends Model
{
    protected $table = 'clo';

    protected $primaryKey = 'maClo';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'moTa',
        'maHocPhan',
        'kyHieu',
    ];

    public function hocPhan(): BelongsTo
    {
        return $this->belongsTo(HocPhan::class, 'maHocPhan', 'maHocPhan');
    }

    public function chiTietMaTrans(): HasMany
    {
        return $this->hasMany(ChiTietMaTran::class, 'maClo', 'maClo');
    }

    public function cauHois(): HasMany
    {
        return $this->hasMany(CauHoi::class, 'maClo', 'maClo');
    }

    public function cloPlos(): HasMany
    {
        return $this->hasMany(CloPlo::class, 'maClo', 'maClo');
    }
}
