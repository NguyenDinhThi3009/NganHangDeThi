<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChiTietMaTran extends Model
{
    protected $table = 'chitietmatran';

    protected $primaryKey = 'maChiTietMaTran';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'maMaTranDeThi',
        'maClo',
        'mucDoBloom',
        'soLuongCau',
        'doKho',
    ];

    public function maTranDeThi(): BelongsTo
    {
        return $this->belongsTo(MaTranDeThi::class, 'maMaTranDeThi', 'maMaTranDeThi');
    }

    public function clo(): BelongsTo
    {
        return $this->belongsTo(Clo::class, 'maClo', 'maClo');
    }
}
