<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NhatKyGoiLLM extends Model
{
    protected $table = 'nhatkygoillm';

    protected $primaryKey = 'maNhatKyLLM';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'maNguoiDung',
        'maMauPrompt',
        'loaiTacVu',
        'tenModel',
        'soTokenVao',
        'soTokenRa',
        'thoiGianXuLyMs',
        'trangThai',
        'thongBaoLoi',
        'ngayTao',
    ];

    protected function casts(): array
    {
        return [
            'ngayTao' => 'datetime',
        ];
    }

    public function nguoiDung(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'maNguoiDung', 'maNguoiDung');
    }

    public function mauPrompt(): BelongsTo
    {
        return $this->belongsTo(MauPrompt::class, 'maMauPrompt', 'maMauPrompt');
    }

    public function cauHois(): HasMany
    {
        return $this->hasMany(CauHoi::class, 'maNhatKyLLM', 'maNhatKyLLM');
    }
}
