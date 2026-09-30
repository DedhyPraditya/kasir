<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\Store;
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

        // Toko default: tempat admin & kasir bawaan bernaung
        $store = Store::firstOrCreate(['slug' => 'nyemil-bebs'], ['name' => 'Nyemil Bebs']);

        // Set role untuk admin existing
        $admin = User::where('username', 'admin')->first();
        if ($admin) {
            $admin->assignRole($adminRole);
            if (! $admin->store_id) {
                $admin->store_id = $store->id;
                $admin->save();
            }
        }

        // Buat user kasir1
        $kasir = User::firstOrCreate(
            ['username' => 'kasir1'],
            ['password' => Hash::make('kasir123')]
        );
        if (! $kasir->store_id) {
            $kasir->store_id = $store->id;
            $kasir->save();
        }
        $kasir->assignRole($kasirRole);

        // Buat user developer: akses penuh admin + boleh hapus transaksi uji coba
        $developerRole = Role::firstOrCreate(['name' => 'developer']);
        $developer = User::firstOrCreate(
            ['username' => 'developer'],
            ['password' => Hash::make(env('DEVELOPER_PASSWORD', 'developer123'))]
        );
        $developer->syncRoles([$adminRole, $developerRole]);
    }
}
