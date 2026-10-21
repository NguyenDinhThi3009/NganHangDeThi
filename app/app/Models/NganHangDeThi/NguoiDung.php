<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class NguoiDung extends Authenticatable
{
    protected $table = 'nguoidung';

    protected $primaryKey = 'maNguoiDung';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'hoTen',
        'email',
        'matKhauMaHoa',
        'maVaiTro',
        'maBoMon',
        'trangThai',
        'trinhDoHocVi',
        'ngayTao',
    ];

    protected $hidden = [
        'matKhauMaHoa',
    ];

    protected function casts(): array
    {
        return [
            'ngayTao' => 'datetime',
            'matKhauMaHoa' => 'hashed',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->matKhauMaHoa;
    }

    // Bảng nguoidung không có cột remember_token
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function vaiTro(): BelongsTo
    {
        return $this->belongsTo(VaiTro::class, 'maVaiTro', 'maVaiTro');
    }

    public function boMon(): BelongsTo
    {
        return $this->belongsTo(BoMon::class, 'maBoMon', 'maBoMon');
    }

    public function nhatKyThaoTacs(): HasMany
    {
        return $this->hasMany(NhatKyThaoTac::class, 'maNguoiDung', 'maNguoiDung');
    }

    public function taiLieus(): HasMany
    {
        return $this->hasMany(TaiLieu::class, 'maNguoiTaiLen', 'maNguoiDung');
    }

    public function maTranDeThis(): HasMany
    {
        return $this->hasMany(MaTranDeThi::class, 'maNguoiTao', 'maNguoiDung');
    }

    public function cauHoiTheoNguoiDuyet(): HasMany
    {
        return $this->hasMany(CauHoi::class, 'maNguoiDuyet', 'maNguoiDung');
    }

    public function cauHoiTheoNguoiTao(): HasMany
    {
        return $this->hasMany(CauHoi::class, 'maNguoiTao', 'maNguoiDung');
    }

    public function deThiTheoNguoiTao(): HasMany
    {
        return $this->hasMany(DeThi::class, 'maNguoiTao', 'maNguoiDung');
    }

    public function deThiTheoNguoiPhanBien(): HasMany
    {
        return $this->hasMany(DeThi::class, 'maNguoiPhanBien', 'maNguoiDung');
    }

    public function deThiTheoNguoiDuyet(): HasMany
    {
        return $this->hasMany(DeThi::class, 'maNguoiDuyet', 'maNguoiDung');
    }

    public function phanCongHocPhans(): HasMany
    {
        return $this->hasMany(PhanCongHocPhan::class, 'maNguoiDung', 'maNguoiDung');
    }

    public function nhatKyGoiLLMs(): HasMany
    {
        return $this->hasMany(NhatKyGoiLLM::class, 'maNguoiDung', 'maNguoiDung');
    }
}
