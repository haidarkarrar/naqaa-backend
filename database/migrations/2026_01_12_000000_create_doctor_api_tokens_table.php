<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('naqaa')->create('doctor_api_tokens', function (Blueprint $table) {
            $table->increments('Id');
            $table->unsignedBigInteger('DoctorId');
            $table->string('Name')->default('mobile');
            $table->string('Token', 64)->unique();
            $table->text('Abilities')->nullable();
            $table->timestamp('LastUsedAt')->nullable();
            $table->timestamp('ExpiresAt')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('naqaa')->dropIfExists('doctor_api_tokens');
    }
};
