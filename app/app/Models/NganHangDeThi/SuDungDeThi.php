<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SuDungDeThi extends Model
{
    protected $table = 'sudungdethi';

    protected $primaryKey = 'maSuDung';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'maDeThi',
        'hocKy',
        'namHoc',
        'ngaySuDung',
    ];

    protected function casts(): array
    {
        return [
            'ngaySuDung' => 'date',
        ];
    }

    public function deThi(): BelongsTo
    {
        return $this->belongsTo(DeThi::class, 'maDeThi', 'maDeThi');
    }

    public function maDeTracNghiems(): HasMany
    {
        return $this->hasMany(MaDeTracNghiem::class, 'maSuDung', 'maSuDung');
    }
}
