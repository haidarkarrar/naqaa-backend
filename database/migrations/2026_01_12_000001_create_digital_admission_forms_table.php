<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('meditop')->create('TblDigitalAdmissionForms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('admission_id');
            $table->json('payload')->nullable();
            $table->json('strokes')->nullable();
            $table->string('form_version')->nullable();
            $table->string('status', 32)->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('meditop')->dropIfExists('TblDigitalAdmissionForms');
    }
};
