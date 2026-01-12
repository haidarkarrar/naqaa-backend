<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('meditop')->create('TblGuarantors', function (Blueprint $table) {
            $table->integer('Id')->primary();
            $table->integer('GroupId')->nullable();
            $table->string('Name', 150)->nullable();
            $table->integer('SerialId')->nullable();
            $table->integer('AccountId')->nullable();
            $table->boolean('ShowOnReport')->default(false);
            $table->decimal('Rate', 18, 2)->default(0);
            $table->boolean('HalfMRI')->default(false);
            $table->decimal('Discount', 18, 2)->default(0);
            $table->integer('CurrencyId')->nullable();
            $table->string('Username', 100)->nullable();
            $table->string('Password', 100)->nullable();
            $table->boolean('New')->default(false);
            $table->boolean('ph')->default(false);
            $table->boolean('PayCard')->default(false);
            $table->boolean('Active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::connection('meditop')->dropIfExists('TblGuarantors');
    }
};
