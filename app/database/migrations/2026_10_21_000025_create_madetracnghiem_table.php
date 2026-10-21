<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('madetracnghiem', function (Blueprint $table) {
            $table->bigIncrements('maMaDe');
            $table->unsignedBigInteger('maSuDung');
            $table->string('kyHieuMa', 10);

            $table->foreign('maSuDung')->references('maSuDung')->on('sudungdethi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('madetracnghiem');
    }
};
