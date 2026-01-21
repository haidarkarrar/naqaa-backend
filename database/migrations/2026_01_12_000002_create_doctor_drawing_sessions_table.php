<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('meditop')->create('TblDoctorDrawingSessions', function (Blueprint $table) {
            $table->integer('Id')->primary();
            $table->unsignedBigInteger('DoctorId');
            $table->unsignedBigInteger('AdmissionId');
            $table->json('StrokeData');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('meditop')->dropIfExists('TblDoctorDrawingSessions');
    }
};
