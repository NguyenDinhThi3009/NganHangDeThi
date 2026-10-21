<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dapantracnghiem', function (Blueprint $table) {
            $table->bigIncrements('maDapAnTracNghiem');
            $table->unsignedBigInteger('maCauHoi');
            $table->text('noiDungDapAn');
            $table->boolean('laDapAnDung');
            $table->integer('thuTu');

            $table->foreign('maCauHoi')->references('maCauHoi')->on('cauhoi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dapantracnghiem');
    }
};
