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

        $this->command->info("Default admin user created: {$email}");
        $this->command->warn('IMPORTANT: Change ADMIN_EMAIL and ADMIN_PASSWORD in .env before production use!');
    }
}
