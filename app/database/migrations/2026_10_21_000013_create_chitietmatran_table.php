<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chitietmatran', function (Blueprint $table) {
            $table->bigIncrements('maChiTietMaTran');
            $table->unsignedBigInteger('maMaTranDeThi');
            $table->unsignedBigInteger('maClo');
            $table->string('mucDoBloom', 20);
            $table->integer('soLuongCau');
            $table->string('doKho', 20);

            $table->foreign('maMaTranDeThi')->references('maMaTranDeThi')->on('matrandethi');
            $table->foreign('maClo')->references('maClo')->on('clo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chitietmatran');
    }
};
