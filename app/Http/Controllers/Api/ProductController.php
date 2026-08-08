<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::with(['variants', 'category'])
            ->where('is_active', true)
            ->get()
            ->map(function (Product $product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'base_price' => (float) $product->base_price,
                    'image_url' => $product->image ? asset('storage/' . $product->image) : null,
                    'allow_topping' => $product->category->allow_topping ?? true,
                    'variants' => $product->variants->map(function ($variant) {
                        return [
                            'id' => $variant->id,
                            'name' => $variant->name,
                            'price' => (float) $variant->price,
                        ];
                    })->values(),
                ];
            });

        return response()->json(['data' => $products]);
    }
}
