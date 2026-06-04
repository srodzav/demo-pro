<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
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
        // Create demo admin user
        $adminRole = Role::where('name', 'admin')->first();

        User::create([
            'name' => 'Administrador Demo',
            'email' => 'admin@demo.com',
            'password' => bcrypt('password123'),
            'role_id' => $adminRole?->id,
            'is_active' => true,
        ]);
    }
}
