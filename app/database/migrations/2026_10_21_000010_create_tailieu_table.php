<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tailieu', function (Blueprint $table) {
            $table->bigIncrements('maTaiLieu');
            $table->string('tenTaiLieu', 255);
            $table->string('duongDanFile', 500);
            $table->string('dinhDang', 10);
            $table->string('loaiTaiLieu', 30);
            $table->string('trangThaiXuLy', 30);
            $table->string('maHocPhan', 20)->nullable();
            $table->unsignedBigInteger('maNguoiTaiLen');
            $table->dateTime('ngayTaiLen');

            $table->foreign('maHocPhan')->references('maHocPhan')->on('hocphan');
            $table->foreign('maNguoiTaiLen')->references('maNguoiDung')->on('nguoidung');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tailieu');
    }
};
