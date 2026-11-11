<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            EventSeeder::class,
            EventSessionSeeder::class,
            InvoiceSeeder::class,
            LatberSeeder::class,
            LatberSessionSeeder::class,
        ]);
    }
}
