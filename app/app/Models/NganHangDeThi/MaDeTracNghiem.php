<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaDeTracNghiem extends Model
{
    protected $table = 'madetracnghiem';

    protected $primaryKey = 'maMaDe';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'maSuDung',
        'kyHieuMa',
    ];

    public function suDung(): BelongsTo
    {
        return $this->belongsTo(SuDungDeThi::class, 'maSuDung', 'maSuDung');
    }

    public function maDeCauHois(): HasMany
    {
        return $this->hasMany(MaDeCauHoi::class, 'maMaDe', 'maMaDe');
    }
}
