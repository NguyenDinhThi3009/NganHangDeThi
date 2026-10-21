<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cauhoi', function (Blueprint $table) {
            $table->bigIncrements('maCauHoi');
            $table->string('maHocPhan', 20);
            $table->text('noiDungCauHoi');
            $table->string('loaiCauHoi', 20);
            $table->string('mucDoBloom', 20);
            $table->string('doKho', 20);
            $table->decimal('doPhanHoa', 3, 2)->nullable();
            $table->string('nguonGoc', 20);
            $table->string('trangThaiDuyet', 20);
            $table->unsignedBigInteger('maClo');
            $table->unsignedBigInteger('maDoanTaiLieu')->nullable();
            $table->unsignedBigInteger('maNguoiDuyet')->nullable();
            $table->unsignedBigInteger('maNguoiTao');
            $table->dateTime('ngayTao');
            $table->dateTime('ngayDuyet')->nullable();
            $table->char('vectorId', 36)->nullable();
            $table->string('modelEmbedding', 100)->nullable();
            $table->unsignedBigInteger('maNhatKyLLM')->nullable();

            $table->foreign('maHocPhan')->references('maHocPhan')->on('hocphan');
            $table->foreign('maClo')->references('maClo')->on('clo');
            $table->foreign('maDoanTaiLieu')->references('maDoanTaiLieu')->on('doantailieu');
            $table->foreign('maNguoiDuyet')->references('maNguoiDung')->on('nguoidung');
            $table->foreign('maNguoiTao')->references('maNguoiDung')->on('nguoidung');
            $table->foreign('maNhatKyLLM')->references('maNhatKyLLM')->on('nhatkygoillm');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cauhoi');
    }
};
