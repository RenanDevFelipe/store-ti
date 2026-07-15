<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate([
            'email' => env('ADMIN_EMAIL', 'admin@store-ti.local'),
        ], [
            'name' => env('ADMIN_NAME', 'Administrador Store TI'),
            'password' => env('ADMIN_PASSWORD', 'password'),
            'role' => 'superadmin',
            'active' => true,
        ]);
    }
}
