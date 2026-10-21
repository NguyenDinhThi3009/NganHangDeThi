<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KiemTraTrungLap extends Model
{
    protected $table = 'kiemtratrunglap';

    protected $primaryKey = 'maKiemTraTrungLap';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'maCauHoi',
        'maCauHoiTrung',
        'doTuongDong',
        'ngayKiemTra',
        'trangThaiCanhBao',
        'nguongApDung',
    ];

    protected function casts(): array
    {
        return [
            'doTuongDong' => 'decimal:4',
            'ngayKiemTra' => 'datetime',
            'nguongApDung' => 'decimal:4',
        ];
    }

    public function cauHoi(): BelongsTo
    {
        return $this->belongsTo(CauHoi::class, 'maCauHoi', 'maCauHoi');
    }

    public function cauHoiTrung(): BelongsTo
    {
        return $this->belongsTo(CauHoi::class, 'maCauHoiTrung', 'maCauHoi');
    }
}
