<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('meditop')->create('TblDoctorDrawingSessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('admission_id');
            $table->json('stroke_data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('meditop')->dropIfExists('TblDoctorDrawingSessions');
    }
};
