<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Topping;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderSyncController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:255'],
            'client_uuid' => ['nullable', 'uuid'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            // Nominal dari aplikasi hanya dibaca untuk kompatibilitas; harga dihitung ulang di server.
            'subtotal' => ['nullable', 'numeric', 'min:0'],
            'total' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', Rule::in(['cash', 'qris'])],
            'status' => ['required', 'string', Rule::in(['completed'])],
            'created_at' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'string'],
            'items.*.variant_id' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.toppings' => ['sometimes', 'array'],
            'items.*.toppings.*.topping_id' => ['required_with:items.*.toppings', 'string'],
        ]);

        $storeId = auth()->user()->tenantId();
        $clientUuid = $data['client_uuid'] ?? null;

        // 1. Idempotensi utama: cek berdasarkan client_uuid (per toko) jika dikirim oleh aplikasi.
        if ($clientUuid) {
            $existingByUuid = Order::withoutGlobalScopes()
                ->where('store_id', $storeId)
                ->where('client_uuid', $clientUuid)
                ->first();

            if ($existingByUuid) {
                return response()->json([
                    'success' => true,
                    'order_id' => $existingByUuid->id,
                    'invoice_number' => $existingByUuid->invoice_number,
                ], 201);
            }
        }

        // 2. Fallback kompatibilitas & cegah tabrakan nomor invoice di toko yang sama.
        $existingByInvoice = Order::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->where('invoice_number', $data['invoice_number'])
            ->first();

        if ($existingByInvoice) {
            // Jika baik request maupun order lama sama-sama tidak punya client_uuid (APK lama <= 1.6.3),
            // anggap sebagai kirim ulang agar antrean offline tidak macet.
            if (! $clientUuid && ! $existingByInvoice->client_uuid) {
                return response()->json([
                    'success' => true,
                    'order_id' => $existingByInvoice->id,
                    'invoice_number' => $existingByInvoice->invoice_number,
                ], 201);
            }

            // Jika ternyata ini transaksi berbeda yang kebetulan satu detik (tabrakan invoice),
            // tambahkan sufiks acak agar transaksi kedua tetap tersimpan dan tidak hilang.
            $suffix = strtoupper( substr(bin2hex(random_bytes(2)), 0, 2) );
            $data['invoice_number'] = $data['invoice_number'] . '-' . $suffix;
        }

        // Harga, nama, dan total diambil dari database toko ini, bukan dari aplikasi.
        $products = Product::with('variants')->whereIn('id', collect($data['items'])->pluck('product_id')->unique())->get()->keyBy('id');
        $toppings = Topping::whereIn('id', collect($data['items'])->flatMap(fn ($i) => collect($i['toppings'] ?? [])->pluck('topping_id'))->unique())->get()->keyBy('id');

        $lines = [];

        foreach ($data['items'] as $index => $item) {
            $product = $products->get($item['product_id']);

            if (! $product) {
                throw ValidationException::withMessages(["items.$index.product_id" => 'Produk tidak ditemukan.']);
            }

            $variant = null;

            if (! empty($item['variant_id'])) {
                $variant = $product->variants->firstWhere('id', $item['variant_id']);

                if (! $variant) {
                    throw ValidationException::withMessages(["items.$index.variant_id" => 'Varian tidak ditemukan.']);
                }
            }

            $lineToppings = [];

            foreach ($item['toppings'] ?? [] as $toppingIndex => $toppingData) {
                $topping = $toppings->get($toppingData['topping_id']);

                if (! $topping) {
                    throw ValidationException::withMessages(["items.$index.toppings.$toppingIndex.topping_id" => 'Topping tidak ditemukan.']);
                }

                $lineToppings[] = $topping;
            }

            $price = ($variant?->price ?? $product->base_price) + collect($lineToppings)->sum('price');

            $lines[] = [
                'product' => $product,
                'variant' => $variant,
                'toppings' => $lineToppings,
                'quantity' => (int) $item['quantity'],
                'price' => $price,
                'subtotal' => $price * (int) $item['quantity'],
            ];
        }

        $total = collect($lines)->sum('subtotal');

        // H3: Batasi rentang created_at agar tidak bisa dimanipulasi ke masa depan atau terlalu lampau (> 14 hari).
        $orderTimestamp = now();
        if (! empty($data['created_at'])) {
            $parsedTime = Carbon::parse($data['created_at']);
            $minAllowed = now()->subDays(14);
            $maxAllowed = now()->addMinutes(5);

            if ($parsedTime->between($minAllowed, $maxAllowed)) {
                $orderTimestamp = $parsedTime;
            }
        }

        $order = DB::transaction(function () use ($data, $lines, $total, $clientUuid, $orderTimestamp) {
            $order = Order::create([
                'invoice_number' => $data['invoice_number'],
                'client_uuid' => $clientUuid,
                'customer_name' => $data['customer_name'] ?? null,
                'subtotal' => $total,
                'total' => $total,
                'payment_method' => $data['payment_method'] ?? null,
                'status' => $data['status'],
                'created_at' => $orderTimestamp,
            ]);

            foreach ($lines as $line) {
                $orderItem = $order->items()->create([
                    'product_id' => $line['product']->id,
                    'variant_id' => $line['variant']?->id,
                    'product_name' => $line['product']->name,
                    'variant_name' => $line['variant']?->name,
                    'quantity' => $line['quantity'],
                    'price' => $line['price'],
                    'subtotal' => $line['subtotal'],
                ]);

                foreach ($line['toppings'] as $topping) {
                    $orderItem->toppings()->create([
                        'topping_id' => $topping->id,
                        'topping_name' => $topping->name,
                        'price' => $topping->price,
                    ]);
                }
            }

            return $order;
        });

        return response()->json([
            'success' => true,
            'order_id' => $order->id,
            'invoice_number' => $order->invoice_number,
        ], 201);
    }

    public function history(): JsonResponse
    {
        $orders = Order::with(['items.toppings'])
            ->whereDate('created_at', today())
            ->orderByDesc('created_at')
            ->orderByDesc('invoice_number')
            ->get()
            ->map(function (Order $order) {
                return [
                    'id' => $order->id,
                    'invoice_number' => $order->invoice_number,
                    'customer_name' => $order->customer_name,
                    'subtotal' => (float) $order->subtotal,
                    'total' => (float) $order->total,
                    'payment_method' => $order->payment_method,
                    'status' => $order->status,
                    'created_at' => $order->created_at ? $order->created_at->format('Y-m-d H:i:s') : '',
                    'items' => $order->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'product_name' => $item->product_name,
                            'variant_name' => $item->variant_name,
                            'quantity' => (int) $item->quantity,
                            'price' => (float) $item->price,
                            'subtotal' => (float) $item->subtotal,
                            'toppings' => $item->toppings->map(function ($topping) {
                                return [
                                    'topping_name' => $topping->topping_name,
                                    'price' => (float) $topping->price,
                                ];
                            })->values(),
                        ];
                    })->values(),
                ];
            });

        return response()->json([
            'data' => $orders,
        ]);
    }
}
