{{--
    Isi struk thermal.
    $order     : Order dengan relasi items.toppings
    $kasir     : username kasir, null = baris Kasir disembunyikan
    $kembalian : kembalian tunai, null = baris Tunai/Kembali disembunyikan
    $reprint   : true = tandai struk sebagai cetak ulang
--}}
@php
    $kasir = $kasir ?? null;
    $kembalian = $kembalian ?? null;
    $reprint = $reprint ?? false;
    $setting = \App\Models\ReceiptSetting::current();
@endphp

<!-- Header Struk -->
<div class="text-center mb-4">
    <h4 class="fw-bold mb-1">{{ $setting->store_name }}</h4>
    @foreach($setting->headerLines() as $line)
    <p class="mb-0 text-muted" style="font-size: 12px;">{{ $line }}</p>
    @endforeach
</div>

<div class="mb-3 border-bottom border-dashed pb-2" style="font-size: 13px;">
    <div class="d-flex justify-content-between">
        <strong>No: {{ $order->invoice_number }}</strong>
    </div>
    <div class="d-flex justify-content-between">
        <strong>Tgl: {{ $order->created_at->format('d/m/Y H:i') }}</strong>
    </div>
    @if($kasir)
    <div class="d-flex justify-content-between">
        <strong>Kasir: {{ $kasir }}</strong>
    </div>
    @endif
    <div class="d-flex justify-content-between">
        <strong>Pelanggan: {{ $order->customer_name }}</strong>
    </div>
</div>

<!-- Isi Pesanan -->
<div class="mb-3 border-bottom border-dashed pb-2" style="font-size: 13px;">
    @foreach($order->items as $item)
    <div class="mb-2">
        <div class="fw-bold">
            {{ $item->product_name }} {{ $item->variant_name ? '- '.$item->variant_name : '' }}
        </div>
        <div class="d-flex justify-content-between">
            <span>{{ $item->quantity }} x {{ number_format($item->price, 0, ',', '.') }}</span>
            <span>{{ number_format($item->subtotal, 0, ',', '.') }}</span>
        </div>
        @if($item->toppings->count() > 0)
        <div class="ms-2" style="font-size: 12px;">
            @foreach($item->toppings as $topping)
                <div>+ {{ $topping->topping_name }} ({{ number_format($topping->price, 0, ',', '.') }})</div>
            @endforeach
        </div>
        @endif
    </div>
    @endforeach
</div>

<!-- Total & Pembayaran -->
<div class="mb-4" style="font-size: 14px;">
    <div class="d-flex justify-content-between fw-bold">
        <span>TOTAL</span>
        <span>Rp {{ number_format($order->total, 0, ',', '.') }}</span>
    </div>
    <div class="d-flex justify-content-between mt-1">
        <span>Metode</span>
        <span class="text-uppercase">{{ $order->payment_method }}</span>
    </div>
    @if($order->payment_method === 'cash' && $kembalian !== null)
    <div class="d-flex justify-content-between">
        <span>Tunai</span>
        <span>Rp {{ number_format($order->total + $kembalian, 0, ',', '.') }}</span>
    </div>
    <div class="d-flex justify-content-between">
        <span>Kembali</span>
        <span>Rp {{ number_format($kembalian, 0, ',', '.') }}</span>
    </div>
    @endif
</div>

<!-- Footer Struk -->
<div class="text-center" style="font-size: 12px;">
    @if($reprint)
    <p class="mb-1">** CETAK ULANG **</p>
    @endif
    @foreach($setting->footerLines() as $line)
    <p class="{{ $loop->last ? 'mb-0' : 'mb-1' }}">{{ $line }}</p>
    @endforeach
</div>
