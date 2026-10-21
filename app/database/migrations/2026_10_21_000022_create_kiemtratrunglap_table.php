<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiemtratrunglap', function (Blueprint $table) {
            $table->bigIncrements('maKiemTraTrungLap');
            $table->unsignedBigInteger('maCauHoi');
            $table->unsignedBigInteger('maCauHoiTrung');
            $table->decimal('doTuongDong', 5, 4);
            $table->dateTime('ngayKiemTra');
            $table->string('trangThaiCanhBao', 30);
            $table->decimal('nguongApDung', 5, 4);

            $table->foreign('maCauHoi')->references('maCauHoi')->on('cauhoi');
            $table->foreign('maCauHoiTrung')->references('maCauHoi')->on('cauhoi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiemtratrunglap');
    }
};
