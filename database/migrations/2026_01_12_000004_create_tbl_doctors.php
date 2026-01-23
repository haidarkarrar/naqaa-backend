<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('meditop')->create('TblDoctors', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('FirstName', 100)->nullable();
            $table->string('MiddleName', 100)->nullable();
            $table->string('LastName', 100)->nullable();
            $table->string('Tel', 50)->nullable();
            $table->string('Mobile', 50)->nullable();
            $table->string('Address', 250)->nullable();
            $table->unsignedBigInteger('SpecialtyId')->nullable();
            $table->boolean('Radiologist')->default(false);
            $table->string('FullName', 200)->nullable();
            $table->boolean('Adm')->default(false);
            $table->string('Email', 100)->nullable();
            $table->string('Spec', 100)->nullable();
            $table->unsignedBigInteger('MedRepId')->nullable();
            $table->boolean('Approved')->default(false);
            $table->boolean('Commission')->default(false);
            $table->boolean('PatientDiscount')->default(false);
            $table->boolean('Internal')->default(false);
            $table->unsignedBigInteger('AccountId')->nullable();
            $table->string('Username', 100)->nullable();
            $table->string('Password', 100)->nullable();
            $table->timestamps(precision: 0);
        });
    }

    public function down(): void
    {
        Schema::connection('meditop')->dropIfExists('TblDoctors');
    }
};
