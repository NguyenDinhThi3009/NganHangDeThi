<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bomon', function (Blueprint $table) {
            $table->bigIncrements('maBoMon');
            $table->string('tenBoMon', 255);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bomon');
    }
};
