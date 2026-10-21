<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sudungdethi', function (Blueprint $table) {
            $table->bigIncrements('maSuDung');
            $table->unsignedBigInteger('maDeThi');
            $table->tinyInteger('hocKy');
            $table->string('namHoc', 9);
            $table->date('ngaySuDung');

            $table->foreign('maDeThi')->references('maDeThi')->on('dethi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sudungdethi');
    }
};
