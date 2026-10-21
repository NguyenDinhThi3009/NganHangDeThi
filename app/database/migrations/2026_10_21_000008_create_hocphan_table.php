<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hocphan', function (Blueprint $table) {
            $table->string('maHocPhan', 20)->primary();
            $table->string('tenHocPhan', 255);
            $table->integer('soTinChi');
            $table->integer('soTinChiLT');
            $table->integer('soTinChiTH');
            $table->integer('soTinChiThucTap')->nullable();
            $table->string('loaiHocPhan', 50);
            $table->integer('hocKyDuKien');
            $table->unsignedBigInteger('maChuongTrinhDaoTao');
            $table->unsignedBigInteger('maBoMon')->nullable();

            $table->foreign('maChuongTrinhDaoTao')->references('maChuongTrinhDaoTao')->on('chuongtrinhdaotao');
            $table->foreign('maBoMon')->references('maBoMon')->on('bomon');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hocphan');
    }
};
