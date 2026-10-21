<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VaiTro extends Model
{
    protected $table = 'vaitro';

    protected $primaryKey = 'maVaiTro';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'maVaiTro',
        'tenVaiTro',
        'moTa',
    ];

    public function nguoiDungs(): HasMany
    {
        return $this->hasMany(
            NguoiDung::class,
            'maVaiTro',
            'maVaiTro'
        );
    }
}
