<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hocphanplo', function (Blueprint $table) {
            $table->bigIncrements('maHocPhanPlo');
            $table->string('maHocPhan', 20);
            $table->unsignedBigInteger('maPlo');
            $table->char('mucDo', 1);

            $table->foreign('maHocPhan')->references('maHocPhan')->on('hocphan');
            $table->foreign('maPlo')->references('maPlo')->on('plo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hocphanplo');
    }
};
