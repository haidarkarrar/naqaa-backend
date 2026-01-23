<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('archive')->create('TblDocuments', function (Blueprint $table) {
            $table->increments('Id');
            $table->unsignedInteger('MRN')->nullable();
            $table->dateTime('Date')->nullable();
            $table->binary('Tump')->nullable();
            $table->binary('Document')->nullable();
            $table->unsignedInteger('AdmissionTypeId')->nullable();
            $table->unsignedInteger('AdmNb')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('archive')->dropIfExists('TblDocuments');
    }
};
