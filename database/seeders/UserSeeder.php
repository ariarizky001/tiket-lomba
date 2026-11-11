<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD', 'password');

        User::create([
            'name' => 'Admin',
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'superadmin',
        ]);

        // Create Latber Admin user with role 'latber'
        User::firstOrCreate(
            ['email' => 'latber@example.com'],
            [
                'name' => 'Latber Admin',
                'password' => Hash::make('password'),
                'role' => 'latber',
            ]
        );

        $this->command->info("Default admin user created: {$email}");
        $this->command->info("Latber admin user created: latber@example.com");
        $this->command->warn('IMPORTANT: Change ADMIN_EMAIL and ADMIN_PASSWORD in .env before production use!');
    }
}
