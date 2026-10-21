<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dapantuluany', function (Blueprint $table) {
            $table->bigIncrements('maDapAnY');
            $table->unsignedBigInteger('maCauHoi');
            $table->text('noiDungY');
            $table->decimal('diemY', 4, 2);
            $table->integer('thuTu');

            $table->foreign('maCauHoi')->references('maCauHoi')->on('cauhoi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dapantuluany');
    }
};
