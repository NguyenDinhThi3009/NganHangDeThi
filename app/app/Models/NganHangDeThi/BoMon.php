<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoMon extends Model
{
    protected $table = 'bomon';

    protected $primaryKey = 'maBoMon';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'tenBoMon',
    ];

    public function nguoiDungs(): HasMany
    {
        return $this->hasMany(NguoiDung::class, 'maBoMon', 'maBoMon');
    }

    public function hocPhans(): HasMany
    {
        return $this->hasMany(HocPhan::class, 'maBoMon', 'maBoMon');
    }
}
