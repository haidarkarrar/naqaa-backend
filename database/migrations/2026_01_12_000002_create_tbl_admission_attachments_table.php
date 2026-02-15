<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('naqaa')->create('TblAdmissionAttachments', function (Blueprint $table) {
            $table->increments('Id');
            $table->unsignedBigInteger('DoctorId');
            $table->unsignedBigInteger('AdmissionId');
            $table->string('Path');
            $table->string('Mime', 64)->nullable();
            $table->string('Label')->nullable();
            $table->dateTime('UploadedAt')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('naqaa')->dropIfExists('TblAdmissionAttachments');
    }
};
