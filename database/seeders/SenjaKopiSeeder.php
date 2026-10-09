<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SenjaKopiSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::where('slug', 'senja-kopi')
            ->orWhere('name', 'like', '%senja%')
            ->first();

        if (! $store) {
            $store = Store::first();
        }

        if (! $store) {
            $this->command->error('Toko tidak ditemukan. Silakan buat toko terlebih dahulu melalui web.');
            return;
        }

        $this->command->info("Menggunakan toko: {$store->name} ({$store->id})");

        // Hapus produk & kategori lama agar tidak duplikat
        Product::where('store_id', $store->id)->delete();
        Category::where('store_id', $store->id)->delete();

        $menus = [
            'Coffee' => [
                ['name' => 'Kosenja',             'price' => 15000],
                ['name' => 'Kopi Aren',           'price' => 15000],
                ['name' => 'Butterscotch Latte',  'price' => 18000],
                ['name' => 'Kopi Pandan',         'price' => 18000],
                ['name' => 'Kopi Vanilla',        'price' => 15000],
                ['name' => 'Kopi Hazelnut',       'price' => 15000],
                ['name' => 'Kopi Caramel',        'price' => 15000],
                ['name' => 'Lotus Biscoff Latte', 'price' => 22000],
                ['name' => 'Kopi Regal',          'price' => 18000],
            ],
            'Black Series' => [
                ['name' => 'Clasic Black', 'price' => 13000],
                ['name' => 'Peach',        'price' => 18000],
                ['name' => 'Rubyberry',    'price' => 15000],
            ],
            'Non Coffee' => [
                ['name' => 'Coklat Klasik',   'price' => 15000],
                ['name' => 'Coklat Hazelnut', 'price' => 18000],
                ['name' => 'Regal Coklat',    'price' => 18000],
                ['name' => 'Regal Milky',     'price' => 15000],
                ['name' => 'Red Velvet',      'price' => 15000],
                ['name' => 'Oreo Milky',      'price' => 15000],
                ['name' => 'Taro',            'price' => 13000],
                ['name' => 'Orange Squash',   'price' => 13000],
                ['name' => 'Melon Squash',    'price' => 13000],
                ['name' => 'Brown Sugar',     'price' => 15000],
            ],
            'Tea' => [
                ['name' => 'Green Tea',      'price' => 15000],
                ['name' => 'Thai Tea',       'price' => 15000],
                ['name' => 'Lemon Tea',      'price' => 15000],
                ['name' => 'Strawberry Tea', 'price' => 15000],
            ],
            'Sweetness' => [
                ['name' => 'Orange Yakult',     'price' => 15000],
                ['name' => 'Strawberry Yakult', 'price' => 15000],
                ['name' => 'Melon Yakult',      'price' => 15000],
                ['name' => 'Mango Yakult',      'price' => 15000],
            ],
            'Hot Baverage' => [
                ['name' => 'Kosenja Hot', 'price' => 13000],
                ['name' => 'Koren Hot',   'price' => 15000],
                ['name' => 'Coklat Hot',  'price' => 15000],
            ],
            'Makanan' => [
                ['name' => 'Pisgep Senja',   'price' => 15000],
                ['name' => 'Pisang Roll',    'price' => 15000],
                ['name' => 'Pisang Nugget',  'price' => 15000],
                ['name' => 'Kentang Goreng', 'price' => 15000],
                ['name' => 'Platter Senja',  'price' => 25000],
                ['name' => 'Otak-Otak',      'price' => 15000],
                ['name' => 'Basreng',        'price' => 15000],
            ],
        ];

        $totalKategori = 0;
        $totalProduk   = 0;

        foreach ($menus as $categoryName => $products) {
            $category = Category::create([
                'store_id'      => $store->id,
                'name'          => $categoryName,
                'slug'          => Str::slug($categoryName).'-'.Str::random(4),
                'allow_topping' => false,
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

        $this->command->info("✅ {$totalKategori} kategori dan {$totalProduk} produk berhasil dimasukkan untuk toko SENJA KOPI.");
    }
}
