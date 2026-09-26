<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReceiptSetting;
use Illuminate\Http\JsonResponse;

class ReceiptSettingController extends Controller
{
    /**
     * Pengaturan struk (header, footer, sisa kertas, potong otomatis) untuk aplikasi mobile,
     * sama dengan yang dipakai cetak thermal di web.
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => ReceiptSetting::current()->printOptions(),
        ]);
    }
}
