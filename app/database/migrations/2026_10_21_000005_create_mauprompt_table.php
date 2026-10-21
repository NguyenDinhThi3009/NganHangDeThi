<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mauprompt', function (Blueprint $table) {
            $table->bigIncrements('maMauPrompt');
            $table->string('loaiTacVu', 30);
            $table->integer('phienBan');
            $table->text('noiDungPrompt');
            $table->boolean('dangSuDung');
            $table->dateTime('ngayTao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mauprompt');
    }
};
