<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doantailieu', function (Blueprint $table) {
            $table->bigIncrements('maDoanTaiLieu');
            $table->unsignedBigInteger('maTaiLieu');
            $table->text('noiDungDoan');
            $table->integer('viTriTrang');
            $table->char('vectorId', 36)->nullable();
            $table->string('modelEmbedding', 100)->nullable();
            $table->dateTime('ngayTao');

            $table->foreign('maTaiLieu')->references('maTaiLieu')->on('tailieu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doantailieu');
    }
};
