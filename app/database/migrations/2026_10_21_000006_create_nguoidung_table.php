<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nguoidung', function (Blueprint $table) {
            $table->bigIncrements('maNguoiDung');
            $table->string('hoTen', 100);
            $table->string('email', 255)->unique();
            $table->string('matKhauMaHoa', 255);
            $table->unsignedBigInteger('maVaiTro');
            $table->unsignedBigInteger('maBoMon')->nullable();
            $table->string('trangThai', 30);
            $table->string('trinhDoHocVi', 50)->nullable();
            $table->dateTime('ngayTao');

            $table->foreign('maVaiTro')->references('maVaiTro')->on('vaitro');
            $table->foreign('maBoMon')->references('maBoMon')->on('bomon');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nguoidung');
    }
};
