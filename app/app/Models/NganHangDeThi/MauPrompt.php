<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MauPrompt extends Model
{
    protected $table = 'mauprompt';

    protected $primaryKey = 'maMauPrompt';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'loaiTacVu',
        'phienBan',
        'noiDungPrompt',
        'dangSuDung',
        'ngayTao',
    ];

    protected function casts(): array
    {
        return [
            'dangSuDung' => 'boolean',
            'ngayTao' => 'datetime',
        ];
    }

    public function nhatKyGoiLLMs(): HasMany
    {
        return $this->hasMany(NhatKyGoiLLM::class, 'maMauPrompt', 'maMauPrompt');
    }
}
