<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dethi', function (Blueprint $table) {
            $table->bigIncrements('maDeThi');
            $table->string('tenDeThi', 255);
            $table->unsignedBigInteger('maMaTranDeThi');
            $table->unsignedBigInteger('maNguoiTao');
            $table->dateTime('ngayTao');
            $table->string('trangThai', 30);
            $table->string('hinhThucThi', 20);
            $table->integer('thoiGianLamBai');
            $table->integer('thoiGianChuanBi')->nullable();
            $table->integer('thoiGianTraLoi')->nullable();
            $table->unsignedBigInteger('maNguoiPhanBien')->nullable();
            $table->dateTime('ngayPhanBien')->nullable();
            $table->string('ketQuaPhanBien', 30)->nullable();
            $table->unsignedBigInteger('maNguoiDuyet')->nullable();
            $table->dateTime('ngayDuyet')->nullable();

            $table->foreign('maMaTranDeThi')->references('maMaTranDeThi')->on('matrandethi');
            $table->foreign('maNguoiTao')->references('maNguoiDung')->on('nguoidung');
            $table->foreign('maNguoiPhanBien')->references('maNguoiDung')->on('nguoidung');
            $table->foreign('maNguoiDuyet')->references('maNguoiDung')->on('nguoidung');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dethi');
    }
};
