<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Ensure a few demo users exist for the prototype UI
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => 'password', 'admin' => true]
        );

        User::updateOrCreate(
            ['email' => 'ronald@example.com'],
            ['name' => 'Ronald', 'password' => 'password', 'admin' => false]
        );

        User::updateOrCreate(
            ['email' => 'terrence@example.com'],
            ['name' => 'Terrence', 'password' => 'password', 'admin' => false]
        );
    }
}
