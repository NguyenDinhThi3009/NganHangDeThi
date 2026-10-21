<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plo', function (Blueprint $table) {
            $table->bigIncrements('maPlo');
            $table->text('moTa');
            $table->unsignedBigInteger('maChuongTrinhDaoTao');
            $table->string('kyHieu', 20);

            $table->foreign('maChuongTrinhDaoTao')->references('maChuongTrinhDaoTao')->on('chuongtrinhdaotao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plo');
    }
};
