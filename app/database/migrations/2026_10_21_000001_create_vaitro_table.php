<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaitro', function (Blueprint $table) {
            $table->bigIncrements('maVaiTro');
            $table->string('tenVaiTro', 50);
            $table->text('moTa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaitro');
    }
};
