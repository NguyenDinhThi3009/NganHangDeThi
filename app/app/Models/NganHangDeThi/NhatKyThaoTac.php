<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NhatKyThaoTac extends Model
{
    protected $table = 'nhatkythaotac';

    protected $primaryKey = 'maNhatKyThaoTac';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'maNguoiDung',
        'hanhDong',
        'doiTuongTacDong',
        'thoiGian',
        'chiTiet',
        'maDoiTuong',
    ];

    protected function casts(): array
    {
        return [
            'thoiGian' => 'datetime',
        ];
    }

    public function nguoiDung(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'maNguoiDung', 'maNguoiDung');
    }
}
