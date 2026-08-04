<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderSyncController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:255', 'unique:orders,invoice_number'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', Rule::in(['cash', 'qris'])],
            'status' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'string'],
            'items.*.variant_id' => ['nullable', 'string'],
            'items.*.product_name' => ['required', 'string'],
            'items.*.variant_name' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.subtotal' => ['required', 'numeric', 'min:0'],
            'items.*.toppings' => ['sometimes', 'array'],
            'items.*.toppings.*.topping_id' => ['required_with:items.*.toppings', 'string'],
            'items.*.toppings.*.topping_name' => ['required_with:items.*.toppings', 'string'],
            'items.*.toppings.*.price' => ['required_with:items.*.toppings', 'numeric', 'min:0'],
        ]);

        $order = Order::create([
            'invoice_number' => $data['invoice_number'],
            'customer_name' => $data['customer_name'] ?? null,
            'subtotal' => $data['subtotal'],
            'total' => $data['total'],
            'payment_method' => $data['payment_method'] ?? null,
            'status' => $data['status'],
        ]);

        foreach ($data['items'] as $item) {
            $orderItem = $order->items()->create([
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'] ?? null,
                'product_name' => $item['product_name'],
                'variant_name' => $item['variant_name'] ?? null,
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'subtotal' => $item['subtotal'],
            ]);

            if (! empty($item['toppings'])) {
                foreach ($item['toppings'] as $topping) {
                    $orderItem->toppings()->create([
                        'topping_id' => $topping['topping_id'],
                        'topping_name' => $topping['topping_name'],
                        'price' => $topping['price'],
                    ]);
                }
            }
        }

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
            ->latest()
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
