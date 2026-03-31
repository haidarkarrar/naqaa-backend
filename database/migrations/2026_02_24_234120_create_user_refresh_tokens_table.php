<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('naqaa')->create('user_refresh_tokens', function (Blueprint $table) {
            $table->increments('Id');
            $table->unsignedBigInteger('UserId');
            $table->string('DeviceId', 128);
            $table->string('TokenHash', 64)->unique();
            $table->timestamp('ExpiresAt')->nullable();
            $table->timestamp('RevokedAt')->nullable();
            $table->text('UserAgent')->nullable();
            $table->string('IpAddress', 45)->nullable();
            $table->timestamp('LastUsedAt')->nullable();
            $table->timestamp('CreatedAt')->nullable();
            $table->timestamp('UpdatedAt')->nullable();

            $table->index('DeviceId');
            $table->index('UserId');
        });
    }

    public function down(): void
    {
        Schema::connection('naqaa')->dropIfExists('user_refresh_tokens');
    }
};

