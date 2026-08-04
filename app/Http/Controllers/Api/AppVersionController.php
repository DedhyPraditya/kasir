<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AppVersionController extends Controller
{
    public function check(): JsonResponse
    {
        return response()->json([
            'latest_version' => config('app.mobile_version', '1.0.0'),
            'build_number' => (int) config('app.mobile_build_number', 1),
            'download_url' => url('/download-apk'),
            'changelog' => config('app.mobile_changelog', 'Peningkatan performa dan pembaruan fitur terbaru.'),
            'force_update' => false,
        ]);
    }

    public function download()
    {
        $path = storage_path('app/public/mobile-pos-latest.apk');
        if (file_exists($path)) {
            return response()->download(
                $path,
                'nyemilbebs-pos-latest.apk',
                ['Content-Type' => 'application/vnd.android.package-archive']
            );
        }
        return response()->json([
            'message' => 'File APK belum tersedia di server.'
        ], 404);
    }
}
