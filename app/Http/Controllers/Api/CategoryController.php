<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::select('id', 'name', 'slug', 'allow_topping')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $categories]);
    }
}
