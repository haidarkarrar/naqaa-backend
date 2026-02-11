<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('naqaa')->create('TblDigitalAdmissionForms', function (Blueprint $table) {
            $table->increments('Id');
            $table->unsignedBigInteger('DoctorId');
            $table->unsignedBigInteger('AdmissionId');
            $table->json('Payload')->nullable();
            $table->json('Strokes')->nullable();
            $table->string('FormVersion')->nullable();
            $table->string('Status', 32)->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('naqaa')->dropIfExists('TblDigitalAdmissionForms');
    }
};
