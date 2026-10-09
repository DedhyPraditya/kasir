<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Topping;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TitikKomaSeeder extends Seeder
{
    public function run(): void
    {
        // Cari toko Titik Koma / Titi Koma
        $store = Store::where('name', 'like', '%titik%koma%')
            ->orWhere('name', 'like', '%titi%koma%')
            ->orWhere('slug', 'like', '%titik-koma%')
            ->orWhere('slug', 'like', '%titi-koma%')
            ->first();

        // Jika belum ada, otomatis buat toko Titik Koma
        if (! $store) {
            $store = Store::create([
                'name'    => 'Titik Koma Coffee',
                'slug'    => 'titik-koma',
                'address' => 'Jl. Titik Koma Coffee',
                'phone'   => '08123456789',
            ]);
            $this->command->info("Toko baru dibuat: Titik Koma Coffee ({$store->id})");
        } else {
            $this->command->info("Menggunakan toko yang ada: {$store->name} ({$store->id})");
        }

        // Hapus produk, kategori, dan topping lama pada toko ini agar tidak duplikat
        Product::where('store_id', $store->id)->delete();
        Category::where('store_id', $store->id)->delete();
        Topping::where('store_id', $store->id)->delete();

        // 1. Tambah Topping
        Topping::create([
            'store_id'  => $store->id,
            'name'      => 'Whip Cream',
            'price'     => 2000,
            'is_active' => true,
        ]);

        // 2. Daftar Menu Coffee & Non Coffee
        $menus = [
            'Menu Coffee' => [
                ['name' => 'Es Kopi Gula Aren',               'price' => 13000],
                ['name' => 'Es Kopi Gula Aren Crumble Foam',  'price' => 15000],
                ['name' => 'Es Kopi Pandan',                  'price' => 13000],
                ['name' => 'Es Kopi Pandan Crumble Foam',     'price' => 15000],
                ['name' => 'Es Kopi Creamy',                  'price' => 13000],
                ['name' => 'Es Kopi Creamy Crumble Foam',     'price' => 15000],
                ['name' => 'Butterscotch Latte',              'price' => 13000],
                ['name' => 'Butterscotch Latte Crumble Foam', 'price' => 15000],
                ['name' => 'Ice Americano',                   'price' => 13000],
                ['name' => 'Americano Peach',                 'price' => 15000],
            ],
            'Menu Non Coffee' => [
                ['name' => 'Matcha Latte',               'price' => 13000],
                ['name' => 'Matcha Latte Sea Salt Foam', 'price' => 15000],
                ['name' => 'Matcha Latte Crumble Foam',  'price' => 15000],
                ['name' => 'Matcha Strawberry Latte',    'price' => 15000],
                ['name' => 'Strawberry Milk',            'price' => 10000],
                ['name' => 'Chocolate Milk',             'price' => 10000],
                ['name' => 'Greentea Milk',              'price' => 10000],
            ],
        ];

        $totalKategori = 0;
        $totalProduk   = 0;

        foreach ($menus as $categoryName => $products) {
            $category = Category::create([
                'store_id'      => $store->id,
                'name'          => $categoryName,
                'slug'          => Str::slug($categoryName).'-'.Str::random(4),
                'allow_topping' => true,
            ]);
            $totalKategori++;

            foreach ($products as $item) {
                Product::create([
                    'store_id'    => $store->id,
                    'category_id' => $category->id,
                    'name'        => $item['name'],
                    'slug'        => Str::slug($item['name']).'-'.Str::random(4),
                    'base_price'  => $item['price'],
                    'is_active'   => true,
                ]);
                $totalProduk++;
            }
        }

        $this->command->info("✅ Berhasil: {$totalKategori} kategori, {$totalProduk} produk, dan 1 topping (Whip Cream) dimasukkan untuk toko Titik Koma.");
    }
}