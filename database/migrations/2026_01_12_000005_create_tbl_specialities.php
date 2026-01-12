<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('meditop')->create('TblSpecialities', function (Blueprint $table) {
            $table->integer('Id')->primary();
            $table->string('Name', 100)->nullable();
            $table->timestamps(0);
        });
    }

    public function down(): void
    {
        Schema::connection('meditop')->dropIfExists('TblSpecialities');
    }
};
