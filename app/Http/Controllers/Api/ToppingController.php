<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Topping;
use Illuminate\Http\JsonResponse;

class ToppingController extends Controller
{
    public function index(): JsonResponse
    {
        $toppings = Topping::where('is_active', true)
            ->get()
            ->map(function (Topping $topping) {
                return [
                    'id' => $topping->id,
                    'name' => $topping->name,
                    'price' => (float) $topping->price,
                ];
            });

        return response()->json(['data' => $toppings]);
    }
}
