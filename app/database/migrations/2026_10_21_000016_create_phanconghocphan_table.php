<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phanconghocphan', function (Blueprint $table) {
            $table->bigIncrements('maPhanCong');
            $table->string('maHocPhan', 20);
            $table->unsignedBigInteger('maNguoiDung');
            $table->string('vaiTroPhanCong', 20);
            $table->tinyInteger('hocKy');
            $table->string('namHoc', 9);
            $table->string('trangThai', 30);

            $table->foreign('maHocPhan')->references('maHocPhan')->on('hocphan');
            $table->foreign('maNguoiDung')->references('maNguoiDung')->on('nguoidung');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phanconghocphan');
    }
};
