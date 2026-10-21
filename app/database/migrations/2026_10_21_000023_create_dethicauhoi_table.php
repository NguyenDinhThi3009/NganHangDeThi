<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dethicauhoi', function (Blueprint $table) {
            $table->bigIncrements('maDeThiCauHoi');
            $table->unsignedBigInteger('maDeThi');
            $table->unsignedBigInteger('maCauHoi');
            $table->integer('thuTuCau');
            $table->tinyInteger('phan')->nullable();
            $table->decimal('diemSo', 4, 2);

            $table->foreign('maDeThi')->references('maDeThi')->on('dethi');
            $table->foreign('maCauHoi')->references('maCauHoi')->on('cauhoi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dethicauhoi');
    }
};
