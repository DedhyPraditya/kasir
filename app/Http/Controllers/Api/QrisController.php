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

        $qrCode = new QrCode(data: $payload, size: 400, margin: 10);
        $result = (new PngWriter())->write($qrCode);

        return response()->json([
            'amount' => (float) $validated['amount'],
            'qr_base64' => base64_encode($result->getString()),
        ]);
    }
}
