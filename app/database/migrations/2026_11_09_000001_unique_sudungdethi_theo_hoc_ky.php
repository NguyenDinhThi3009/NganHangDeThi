<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** BR-08: mỗi đề thi chỉ được sử dụng một lần trong một học kỳ (CSDL chặn trùng đề, học kỳ, năm học). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sudungdethi', function (Blueprint $table) {
            $table->unique(['maDeThi', 'hocKy', 'namHoc'], 'sudungdethi_dethi_hocky_namhoc_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sudungdethi', function (Blueprint $table) {
            $table->dropUnique('sudungdethi_dethi_hocky_namhoc_unique');
        });
    }
};
