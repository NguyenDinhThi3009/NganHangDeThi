<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VaiTro extends Model
{
    protected $table = 'vaitro';

    protected $primaryKey = 'maVaiTro';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'tenVaiTro',
        'moTa',
    ];

    public function nguoiDungs(): HasMany
    {
        return $this->hasMany(NguoiDung::class, 'maVaiTro', 'maVaiTro');
    }
}
