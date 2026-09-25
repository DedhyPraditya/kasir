<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'invoice_number',
        'customer_name',
        'subtotal',
        'total',
        'payment_method',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Data struk untuk cetak langsung ke printer thermal (public/js/thermal-printer.js).
     * $kasir / $kembalian null = baris tersebut tidak dicetak.
     */
    public function receiptData(?string $kasir = null, int|float|null $kembalian = null, bool $reprint = false): array
    {
        $this->loadMissing('items.toppings');
        $setting = ReceiptSetting::current();

        return [
            'store'     => $setting->store_name,
            'header'    => $setting->headerLines(),
            'footer'    => $setting->footerLines(),
            'invoice'   => $this->invoice_number,
            'date'      => $this->created_at?->format('d/m/Y H:i'),
            'kasir'     => $kasir,
            'customer'  => $this->customer_name,
            'items'     => $this->items->map(fn (OrderItem $item) => [
                'name'     => trim($item->product_name.($item->variant_name ? ' - '.$item->variant_name : '')),
                'qty'      => (int) $item->quantity,
                'price'    => (float) $item->price,
                'subtotal' => (float) $item->subtotal,
                'toppings' => $item->toppings->map(fn ($topping) => [
                    'name'  => $topping->topping_name,
                    'price' => (float) $topping->price,
                ])->values()->all(),
            ])->values()->all(),
            'total'     => (float) $this->total,
            'method'    => $this->payment_method,
            'paid'      => $this->payment_method === 'cash' && $kembalian !== null ? (float) $this->total + $kembalian : null,
            'change'    => $this->payment_method === 'cash' && $kembalian !== null ? (float) $kembalian : null,
            'reprint'   => $reprint,
        ];
    }
}
