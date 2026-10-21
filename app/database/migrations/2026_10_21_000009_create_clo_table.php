<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clo', function (Blueprint $table) {
            $table->bigIncrements('maClo');
            $table->text('moTa');
            $table->string('maHocPhan', 20);
            $table->string('kyHieu', 20);

            $table->foreign('maHocPhan')->references('maHocPhan')->on('hocphan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clo');
    }
};
