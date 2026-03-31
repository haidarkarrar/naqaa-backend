<?php

namespace Database\Seeders;

use Database\Seeders\AccessControlSeeder;
use Database\Seeders\LegacyDataSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AccessControlSeeder::class,
            LegacyDataSeeder::class,
        ]);
    }
}
