<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloplo', function (Blueprint $table) {
            $table->bigIncrements('maCloPlo');
            $table->unsignedBigInteger('maClo');
            $table->unsignedBigInteger('maPlo');
            $table->string('chiSoPI', 50)->nullable();

            $table->foreign('maClo')->references('maClo')->on('clo');
            $table->foreign('maPlo')->references('maPlo')->on('plo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloplo');
    }
};
