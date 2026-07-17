<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buat Roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $kasirRole = Role::firstOrCreate(['name' => 'kasir']);

        // Set role untuk admin existing
        $admin = User::where('username', 'admin')->first();
        if ($admin) {
            $admin->assignRole($adminRole);
        }

        // Buat user kasir1
        $kasir = User::firstOrCreate(
            ['username' => 'kasir1'],
            ['password' => Hash::make('kasir123')]
        );
        $kasir->assignRole($kasirRole);
    }
}
