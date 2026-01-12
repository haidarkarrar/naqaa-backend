<?php

namespace Database\Seeders;

use Database\Seeders\LegacyDataSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(LegacyDataSeeder::class);
    }
}
