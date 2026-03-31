<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('naqaa')->create('user_api_tokens', function (Blueprint $table) {
            $table->increments('Id');
            $table->unsignedBigInteger('UserId');
            $table->string('Name')->default('mobile');
            $table->string('Token', 64)->unique();
            $table->text('Abilities')->nullable();
            $table->timestamp('LastUsedAt')->nullable();
            $table->timestamp('ExpiresAt')->nullable();
            $table->timestamps();
            $table->index('UserId');
        });
    }

    public function down(): void
    {
        Schema::connection('naqaa')->dropIfExists('user_api_tokens');
    }
};

