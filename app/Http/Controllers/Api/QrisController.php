<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QrisService;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class QrisController extends Controller
{
    public function dynamic(Request $request, QrisService $qris): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $staticPayload = $qris->getActivePayload();

        if (! $staticPayload) {
            return response()->json(['message' => 'QRIS statis belum dikonfigurasi.'], 500);
        }

        try {
            $payload = $qris->generateDynamicPayload($staticPayload, (float) $validated['amount']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $qrCode = new QrCode(data: $payload, size: 300, margin: 10);
        $result = (new PngWriter())->write($qrCode);

        return response()->json([
            'amount'    => (float) $validated['amount'],
            'payload'   => $payload,
            'qr_base64' => base64_encode($result->getString()),
        ]);
    }

    /**
     * Mengembalikan gambar QR dari payload QRIS STATIS asli (tag 01 = 11, tidak dimodifikasi).
     * Aman dipakai oleh semua bank & e-wallet karena tidak mengubah struktur QRIS.
     * Nominal ditampilkan di UI aplikasi, bukan disisipkan ke dalam payload QR.
     */
    public function staticImage(Request $request, QrisService $qris): JsonResponse
    {
        $payload = $qris->getActivePayload();

        if (! $payload) {
            return response()->json(['message' => 'QRIS belum dikonfigurasi.'], 500);
        }

        $qrCode = new QrCode(data: $payload, size: 400, margin: 10);
        $result = (new PngWriter())->write($qrCode);

        return response()->json([
            'payload'   => $payload,
            'qr_base64' => base64_encode($result->getString()),
        ]);
    }
}
