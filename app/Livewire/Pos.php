<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\Topping;
use App\Models\Order;
use Livewire\Component;
use Illuminate\Support\Str;

class Pos extends Component
{
    public $cart = [];
    
    // Modal Product State
    public $showModal = false;
    public $selectedProduct = null;
    public $selectedVariant = null;
    public $selectedToppings = [];
    public $quantity = 1;

    // Modal Payment State
    public $showPaymentModal = false;
    public $paymentMethod = 'cash';
    public $customerName = '';
    public $amountPaid = '';

    // Modal Receipt State
    public $showReceiptModal = false;
    public $lastOrder = null;
    public $lastKembalian = 0;

    public function selectProduct($productId)
    {
        $this->selectedProduct = Product::with('variants')->find($productId);
        if ($this->selectedProduct) {
            if ($this->selectedProduct->variants->count() > 0) {
                $this->selectedVariant = $this->selectedProduct->variants->first()->id;
            } else {
                $this->selectedVariant = null;
            }
            $this->selectedToppings = [];
            $this->quantity = 1;
            $this->showModal = true;
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedProduct = null;
        $this->selectedVariant = null;
        $this->selectedToppings = [];
        $this->quantity = 1;
    }

    public function addToCart()
    {
        if (!$this->selectedProduct) return;

        $variant = null;
        if ($this->selectedVariant) {
            $variant = $this->selectedProduct->variants->where('id', $this->selectedVariant)->first();
        }

        $toppings = Topping::whereIn('id', $this->selectedToppings)->get();
        
        $basePrice = $variant ? $variant->price : $this->selectedProduct->base_price;
        $toppingsPrice = $toppings->sum('price');
        $itemPrice = $basePrice + $toppingsPrice;
        $subtotal = $itemPrice * $this->quantity;

        $cartItemId = uniqid();

        $this->cart[] = [
            'id' => $cartItemId,
            'product_id' => $this->selectedProduct->id,
            'product_name' => $this->selectedProduct->name,
            'variant_id' => $variant ? $variant->id : null,
            'variant_name' => $variant ? $variant->name : null,
            'toppings' => $toppings->map(function($t) {
                return ['id' => $t->id, 'name' => $t->name, 'price' => $t->price];
            })->toArray(),
            'quantity' => $this->quantity,
            'price' => $itemPrice,
            'subtotal' => $subtotal,
        ];

        $this->closeModal();
    }

    public function removeFromCart($cartItemId)
    {
        $this->cart = collect($this->cart)->reject(function ($item) use ($cartItemId) {
            return $item['id'] === $cartItemId;
        })->values()->toArray();
    }

    public function getTotalProperty()
    {
        return collect($this->cart)->sum('subtotal');
    }

    public function openPaymentModal()
    {
        if (empty($this->cart)) return;
        $this->showPaymentModal = true;
        $this->paymentMethod = 'cash';
        $this->amountPaid = '';
        $this->customerName = '';
    }

    public function closePaymentModal()
    {
        $this->showPaymentModal = false;
        $this->resetValidation();
    }

    public function processPayment()
    {
        if (empty($this->cart)) return;

        $total = $this->total;

        $this->validate([
            'customerName' => 'required|string|max:255',
            'paymentMethod' => 'required|in:cash,qris',
            'amountPaid' => $this->paymentMethod === 'cash' ? 'required|numeric|min:' . $total : 'nullable',
        ], [
            'customerName.required' => 'Nama pelanggan wajib diisi sebelum membayar.',
            'amountPaid.required' => 'Nominal uang harus diisi jika bayar tunai.',
            'amountPaid.min' => 'Nominal uang tidak cukup! Minimal Rp ' . number_format($total, 0, ',', '.'),
        ]);

        // Save Order
        $order = Order::create([
            'invoice_number' => 'INV-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
            'customer_name' => $this->customerName,
            'subtotal' => $total,
            'total' => $total,
            'payment_method' => $this->paymentMethod,
            'status' => 'completed',
        ]);

        // Save Items & Toppings
        foreach ($this->cart as $item) {
            $orderItem = $order->items()->create([
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'],
                'product_name' => $item['product_name'],
                'variant_name' => $item['variant_name'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'subtotal' => $item['subtotal'],
            ]);

            if (!empty($item['toppings'])) {
                foreach ($item['toppings'] as $topping) {
                    $orderItem->toppings()->create([
                        'topping_id' => $topping['id'],
                        'topping_name' => $topping['name'],
                        'price' => $topping['price'],
                    ]);
                }
            }
        }

        $kembalian = $this->paymentMethod === 'cash' ? ((int)$this->amountPaid - $total) : 0;
        
        $this->lastOrder = Order::with('items.toppings')->find($order->id);
        $this->lastKembalian = $kembalian;

        $this->cart = [];
        $this->showPaymentModal = false;
        $this->showReceiptModal = true;
    }

    public function render()
    {
        return view('livewire.pos', [
            'products' => Product::with(['category', 'variants'])->get(),
            'toppings' => Topping::all(),
        ])->layout('layouts.app');
    }
}
