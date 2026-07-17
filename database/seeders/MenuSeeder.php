<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Topping;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Kategori
        $categoryGabin = Category::create([
            'name' => 'Gabin Fla',
            'slug' => Str::slug('Gabin Fla'),
        ]);

        $categoryBanana = Category::create([
            'name' => 'Banana Roll',
            'slug' => Str::slug('Banana Roll'),
        ]);

        // 2. Produk & Varian Gabin Fla
        $gabinProduct = Product::create([
            'category_id' => $categoryGabin->id,
            'name' => 'Gabin Fla',
            'slug' => Str::slug('Gabin Fla'),
            'base_price' => 10000, // Harga dasar 10K
        ]);

        $variants = ['Original', 'Chocolate', 'Tiramisu', 'Green Tea', 'Taro'];
        foreach ($variants as $variant) {
            $gabinProduct->variants()->create([
                'name' => $variant,
                'price' => 10000, // Semua varian 10K
            ]);
        }

        // 3. Produk & Varian Banana Roll
        $bananaProduct = Product::create([
            'category_id' => $categoryBanana->id,
            'name' => 'Banana Roll',
            'slug' => Str::slug('Banana Roll'),
            'base_price' => 10000, // Harga dasar 10K
        ]);

        foreach ($variants as $variant) {
            $bananaProduct->variants()->create([
                'name' => $variant,
                'price' => 10000, // Semua varian 10K
            ]);
        }

        // 4. Toppings
        $toppings = [
            ['name' => 'Keju', 'price' => 3000],
            ['name' => 'Oreo', 'price' => 3000],
            ['name' => 'Red Velvet', 'price' => 3000],
            ['name' => 'Meses', 'price' => 2000],
        ];

        foreach ($toppings as $topping) {
            Topping::create($topping);
        }
    }
}
