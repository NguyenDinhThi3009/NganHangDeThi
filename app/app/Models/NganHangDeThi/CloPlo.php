<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloPlo extends Model
{
    protected $table = 'cloplo';

    protected $primaryKey = 'maCloPlo';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'maClo',
        'maPlo',
        'chiSoPI',
    ];

    public function clo(): BelongsTo
    {
        return $this->belongsTo(Clo::class, 'maClo', 'maClo');
    }

    public function plo(): BelongsTo
    {
        return $this->belongsTo(Plo::class, 'maPlo', 'maPlo');
    }
}
