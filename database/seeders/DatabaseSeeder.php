<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'password' => Hash::make('admin123'),
                'api_token' => Str::random(80),
            ]
        );

        User::firstOrCreate(
            ['username' => 'kasir1'],
            [
                'password' => Hash::make('kasir123'),
                'api_token' => Str::random(80),
            ]
        );

        $this->call([
            RoleSeeder::class,
            MenuSeeder::class,
        ]);
    }
}
