<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('archive')->create('TblAdmissionAttachments', function (Blueprint $table) {
            $table->integer('Id')->primary();
            $table->unsignedBigInteger('DoctorId')->nullable();
            $table->unsignedBigInteger('AdmissionId');
            $table->string('Path');
            $table->string('Mime');
            $table->string('Label')->nullable();
            $table->timestamp('UploadedAt')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('archive')->dropIfExists('TblAdmissionAttachments');
    }
};
