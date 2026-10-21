<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DoanTaiLieu extends Model
{
    protected $table = 'doantailieu';

    protected $primaryKey = 'maDoanTaiLieu';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'maTaiLieu',
        'noiDungDoan',
        'viTriTrang',
        'vectorId',
        'modelEmbedding',
        'ngayTao',
    ];

    protected function casts(): array
    {
        return [
            'ngayTao' => 'datetime',
        ];
    }

    public function taiLieu(): BelongsTo
    {
        return $this->belongsTo(TaiLieu::class, 'maTaiLieu', 'maTaiLieu');
    }

    public function cauHois(): HasMany
    {
        return $this->hasMany(CauHoi::class, 'maDoanTaiLieu', 'maDoanTaiLieu');
    }
}
