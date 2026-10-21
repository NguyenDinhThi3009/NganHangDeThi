<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HocPhanPlo extends Model
{
    protected $table = 'hocphanplo';

    protected $primaryKey = 'maHocPhanPlo';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'maHocPhan',
        'maPlo',
        'mucDo',
    ];

    public function hocPhan(): BelongsTo
    {
        return $this->belongsTo(HocPhan::class, 'maHocPhan', 'maHocPhan');
    }

    public function plo(): BelongsTo
    {
        return $this->belongsTo(Plo::class, 'maPlo', 'maPlo');
    }
}
