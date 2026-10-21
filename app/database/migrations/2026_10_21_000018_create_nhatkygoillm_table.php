<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nhatkygoillm', function (Blueprint $table) {
            $table->bigIncrements('maNhatKyLLM');
            $table->unsignedBigInteger('maNguoiDung')->nullable();
            $table->unsignedBigInteger('maMauPrompt')->nullable();
            $table->string('loaiTacVu', 30);
            $table->string('tenModel', 100);
            $table->integer('soTokenVao');
            $table->integer('soTokenRa');
            $table->integer('thoiGianXuLyMs');
            $table->string('trangThai', 30);
            $table->text('thongBaoLoi')->nullable();
            $table->dateTime('ngayTao');

            $table->foreign('maNguoiDung')->references('maNguoiDung')->on('nguoidung');
            $table->foreign('maMauPrompt')->references('maMauPrompt')->on('mauprompt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nhatkygoillm');
    }
};
