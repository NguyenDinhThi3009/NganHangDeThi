<?php

namespace App\Models\NganHangDeThi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChuongTrinhDaoTao extends Model
{
    protected $table = 'chuongtrinhdaotao';

    protected $primaryKey = 'maChuongTrinhDaoTao';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'tenChuongTrinhDaoTao',
        'tenTiengAnh',
        'trinhDo',
        'tongSoTinChi',
        'khoaQuanLy',
        'khoaHoc',
    ];

    public function plos(): HasMany
    {
        return $this->hasMany(Plo::class, 'maChuongTrinhDaoTao', 'maChuongTrinhDaoTao');
    }

    public function hocPhans(): HasMany
    {
        return $this->hasMany(HocPhan::class, 'maChuongTrinhDaoTao', 'maChuongTrinhDaoTao');
    }
}
