<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('meditop')->table('TblPatients', function (Blueprint $table) {
            $table->boolean('Smoker')->default(false);
            $table->boolean('Alcoholic')->default(false);
            $table->text('MedicalHistory')->nullable();
            $table->text('SurgicalHistory')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('meditop')->table('TblPatients', function (Blueprint $table) {
            $table->dropColumn(['Smoker', 'Alcoholic', 'MedicalHistory', 'SurgicalHistory']);
        });
    }
};
