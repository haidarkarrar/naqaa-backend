<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('meditop')->create('TblPatients', function (Blueprint $table) {
            $table->integer('Id')->primary();
            $table->string('First', 100)->nullable();
            $table->string('Middle', 100)->nullable();
            $table->string('Last', 100)->nullable();
            $table->string('Mother', 100)->nullable();
            $table->unsignedInteger('GenderId')->nullable();
            $table->unsignedInteger('Weight')->nullable();
            $table->dateTime('DOB')->nullable();
            $table->string('POB', 100)->nullable();
            $table->string('IDNum', 50)->nullable();
            $table->unsignedInteger('NationalityId')->nullable();
            $table->unsignedInteger('BloodGroupId')->nullable();
            $table->string('ArabicName', 100)->nullable();
            $table->string('Phone', 50)->nullable();
            $table->string('Email', 50)->nullable();
            $table->string('City', 50)->nullable();
            $table->string('Street', 100)->nullable();
            $table->string('HomeTel', 50)->nullable();
            $table->string('Address', 150)->nullable();
            $table->string('JobTel', 50)->nullable();
            $table->unsignedInteger('GuarantorId')->nullable();
            $table->unsignedInteger('MaritalStatusId')->nullable();
            $table->dateTime('OFD')->nullable();
            $table->unsignedInteger('MainDoctorId')->nullable();
            $table->boolean('Allergies')->default(false);
            $table->boolean('Diabetic')->default(false);
            $table->boolean('Pregnancy')->default(false);
            $table->boolean('RenalFailure')->default(false);
            $table->boolean('CardiacFailure')->default(false);
            $table->boolean('OtherDisease')->default(false);
            $table->unsignedBigInteger('AttachmentId')->nullable();
            $table->string('EnglishName', 150)->nullable();
            $table->string('Password', 100)->nullable();
            $table->string('barecode', 100)->nullable();
            $table->string('Natio', 100)->nullable();
            $table->dateTime('ExpiryDate')->nullable();
            $table->binary('picture')->nullable();
            $table->boolean('isOnGuarantor')->default(false);
            $table->string('SpouseName', 100)->nullable();
            $table->bigInteger('newid')->nullable();
            $table->string('ArFN', 100)->nullable();
            $table->string('ARMN', 100)->nullable();
            $table->string('ARLN', 100)->nullable();
            $table->boolean('isBlackList')->default(false);
        });
    }

    public function down(): void
    {
        Schema::connection('meditop')->dropIfExists('TblPatients');
    }
};
