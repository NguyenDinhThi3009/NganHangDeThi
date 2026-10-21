<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chuongtrinhdaotao', function (Blueprint $table) {
            $table->bigIncrements('maChuongTrinhDaoTao');
            $table->string('tenChuongTrinhDaoTao', 255);
            $table->string('tenTiengAnh', 255)->nullable();
            $table->string('trinhDo', 50);
            $table->integer('tongSoTinChi');
            $table->string('khoaQuanLy', 100);
            $table->string('khoaHoc', 20);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chuongtrinhdaotao');
    }
};
