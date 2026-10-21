<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('madecauhoi', function (Blueprint $table) {
            $table->bigIncrements('maMaDeCauHoi');
            $table->unsignedBigInteger('maMaDe');
            $table->unsignedBigInteger('maDeThiCauHoi');
            $table->integer('thuTuCauMoi');
            $table->json('thuTuDapAn')->nullable();

            $table->foreign('maMaDe')->references('maMaDe')->on('madetracnghiem');
            $table->foreign('maDeThiCauHoi')->references('maDeThiCauHoi')->on('dethicauhoi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('madecauhoi');
    }
};
