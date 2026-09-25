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

    public function webhookNotif(Request $request): JsonResponse
    {
        $secret = $request->input('secret');
        if ($secret !== 'nyemilbebs123') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $title = (string) $request->input('title', '');
        $text = (string) $request->input('text', '');

        \Illuminate\Support\Facades\Log::info('InstaQRIS Webhook Received', ['title' => $title, 'text' => $text]);

        $amount = null;
        $transactionId = null;

        // Contoh: "SpeedCash - Anda telah menerima pembayaran sejumlah Rp.25,500.00,- dengan id transaksi 612215740."
        if (preg_match('/(?:sejumlah\s+)?Rp\.?\s*([0-9.,]+)/i', $text, $matches)) {
            $rawAmount = $matches[1];
            // Bersihkan .00 atau ,00 di ujung
            $clean = preg_replace('/[.,]00$/', '', $rawAmount);
            // Hilangkan semua tanda titik atau koma ribuan
            $clean = preg_replace('/[^0-9]/', '', $clean);
            if (! empty($clean)) {
                $amount = (float) $clean;
            }
        }

        if (preg_match('/id\s+transaksi\s+(\d+)/i', $text, $matches)) {
            $transactionId = $matches[1];
        }

        if ($amount === null) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekstrak nominal dari teks notifikasi.',
                'raw_text' => $text,
            ], 422);
        }

        $paymentData = [
            'id' => $transactionId ?: ('TRX_'.time()),
            'amount' => $amount,
            'title' => $title,
            'raw_text' => $text,
            'created_at' => now()->timestamp,
            'claimed' => false,
        ];

        $cacheKey = 'qris_payments_list';
        $payments = \Illuminate\Support\Facades\Cache::get($cacheKey, []);

        // Hapus pembayaran dengan ID yang sama jika ada duplikat pemicu
        $filtered = array_values(array_filter($payments, function ($p) use ($paymentData) {
            return $p['id'] !== $paymentData['id'];
        }));

        $filtered[] = $paymentData;
        \Illuminate\Support\Facades\Cache::put($cacheKey, $filtered, now()->addMinutes(15));

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi pembayaran QRIS berhasil dicatat.',
            'data' => $paymentData,
        ]);
    }

    public function checkPayment(Request $request): JsonResponse
    {
        $targetAmount = (float) $request->input('amount', 0);
        $since = (int) $request->input('since', now()->subMinutes(5)->timestamp);

        if ($targetAmount <= 0) {
            return response()->json(['paid' => false, 'message' => 'Nominal tidak valid']);
        }

        $cacheKey = 'qris_payments_list';
        $payments = \Illuminate\Support\Facades\Cache::get($cacheKey, []);

        $foundIndex = null;
        $foundPayment = null;

        foreach ($payments as $index => $payment) {
            if (! $payment['claimed'] &&
                abs($payment['amount'] - $targetAmount) < 0.01 &&
                $payment['created_at'] >= ($since - 45) // toleransi 45 detik
            ) {
                $foundIndex = $index;
                $foundPayment = $payment;
                break;
            }
        }

        if ($foundPayment) {
            $payments[$foundIndex]['claimed'] = true;
            \Illuminate\Support\Facades\Cache::put($cacheKey, $payments, now()->addMinutes(15));

            return response()->json([
                'paid' => true,
                'payment' => $foundPayment,
            ]);
        }

        return response()->json(['paid' => false]);
    }

    public function recentLogs(): JsonResponse
    {
        $cacheKey = 'qris_payments_list';
        $payments = \Illuminate\Support\Facades\Cache::get($cacheKey, []);

        return response()->json([
            'status' => 'online',
            'server_time' => now()->format('Y-m-d H:i:s'),
            'total_recorded' => count($payments),
            'payments' => array_reverse($payments),
        ]);
    }
}

