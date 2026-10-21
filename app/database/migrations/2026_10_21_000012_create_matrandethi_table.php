<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matrandethi', function (Blueprint $table) {
            $table->bigIncrements('maMaTranDeThi');
            $table->string('tenMaTran', 255);
            $table->string('maHocPhan', 20);
            $table->unsignedBigInteger('maNguoiTao');
            $table->dateTime('ngayTao');
            $table->string('trangThai', 30);
            $table->decimal('nguongTrungLap', 5, 4)->default(0.85);

            $table->foreign('maHocPhan')->references('maHocPhan')->on('hocphan');
            $table->foreign('maNguoiTao')->references('maNguoiDung')->on('nguoidung');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matrandethi');
    }
};
