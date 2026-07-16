<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'jdavid.lozano1404@gmail.com'],
            [
                'name' => 'David Lozano',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'Pilo$Admin2026#Lz')),
                'role' => 'super_admin',
            ]
        );

        $this->call(BusinessDemoSeeder::class);
    }
}
