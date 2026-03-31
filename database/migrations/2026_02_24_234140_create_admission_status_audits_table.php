<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('naqaa')->create('admission_status_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admission_id');
            $table->string('old_status', 16);
            $table->string('new_status', 16);
            $table->unsignedBigInteger('changed_by_user_id');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('admission_id');
            $table->index('changed_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::connection('naqaa')->dropIfExists('admission_status_audits');
    }
};

