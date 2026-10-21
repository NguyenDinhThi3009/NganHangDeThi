<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nhatkythaotac', function (Blueprint $table) {
            $table->bigIncrements('maNhatKyThaoTac');
            $table->unsignedBigInteger('maNguoiDung');
            $table->string('hanhDong', 100);
            $table->string('doiTuongTacDong', 100);
            $table->dateTime('thoiGian');
            $table->text('chiTiet')->nullable();
            $table->unsignedBigInteger('maDoiTuong')->nullable();

            $table->foreign('maNguoiDung')->references('maNguoiDung')->on('nguoidung');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nhatkythaotac');
    }
};
