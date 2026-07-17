<div>
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            #print-area, #print-area * {
                visibility: visible;
            }
            #print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 10px !important;
                margin: 0 !important;
                color: black !important;
            }
            .modal {
                position: absolute;
                left: 0;
                top: 0;
                margin: 0;
                padding: 0;
                overflow: visible !important;
            }
            .d-print-none {
                display: none !important;
            }
            /* Hilangkan border dan shadow saat print struk */
            .modal-content {
                border: none !important;
                box-shadow: none !important;
            }
        }
        
        .struk-font {
            font-family: 'Courier New', Courier, monospace;
        }
    </style>

    <div class="container-fluid py-4 d-print-none">
        @if (session()->has('success'))
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="row">
            <!-- Left Side: Products -->
            <div class="col-md-8">
                <div class="card shadow-sm mb-4 border-0">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                        <h4 class="mb-0 fw-bold text-success">Daftar Menu</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach($products as $product)
                            <div class="col-md-4 col-sm-6">
                                <div class="card h-100 shadow-sm border-0 cursor-pointer" wire:click="selectProduct('{{ $product->id }}')" style="cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.03)'" onmouseout="this.style.transform='scale(1)'">
                                    <div class="card-body text-center d-flex flex-column justify-content-center">
                                        <h6 class="card-title fw-bold">{{ $product->name }}</h6>
                                        <p class="text-success fw-bold mb-0">Rp {{ number_format($product->base_price, 0, ',', '.') }}</p>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Cart -->
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100 d-flex flex-column">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                        <h4 class="mb-0 fw-bold">Pesanan</h4>
                    </div>
                    <div class="card-body p-0 flex-grow-1" style="overflow-y: auto; max-height: 60vh;">
                        <ul class="list-group list-group-flush">
                            @forelse($cart as $item)
                                <li class="list-group-item py-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-0 fw-bold">{{ $item['product_name'] }}</h6>
                                            <small class="text-muted d-block">
                                                @if($item['variant_name'])
                                                    Varian: {{ $item['variant_name'] }} <br>
                                                @endif
                                                @if(count($item['toppings']) > 0)
                                                    Topping: {{ implode(', ', array_column($item['toppings'], 'name')) }} <br>
                                                @endif
                                            </small>
                                            <div class="mt-2">
                                                <span class="badge bg-light text-dark border">{{ $item['quantity'] }} x Rp {{ number_format($item['price'], 0, ',', '.') }}</span>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold mb-2">Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</div>
                                            <button class="btn btn-sm btn-outline-danger" wire:click="removeFromCart('{{ $item['id'] }}')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </li>
                            @empty
                                <li class="list-group-item text-center text-muted py-5 border-0">
                                    <i class="bi bi-cart-x fs-1 d-block mb-3 text-light"></i>
                                    Belum ada pesanan
                                </li>
                            @endforelse
                        </ul>
                    </div>
                    <div class="card-footer bg-white border-top p-4 mt-auto">
                        <div class="d-flex justify-content-between mb-3 fs-5">
                            <span class="fw-bold">Total:</span>
                            <span class="fw-bold text-success">Rp {{ number_format($this->total, 0, ',', '.') }}</span>
                        </div>
                        <button class="btn btn-success w-100 py-3 fw-bold fs-6 rounded-3" wire:click="openPaymentModal" @if(empty($cart)) disabled @endif>
                            Proses Pembayaran
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Modal Overlay Product -->
    @if($showModal && $selectedProduct)
    <div class="modal-backdrop fade show" style="z-index: 1040;"></div>
    <div class="modal fade show d-block" tabindex="-1" style="z-index: 1050;" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0">
                    <h5 class="modal-title fw-bold">{{ $selectedProduct->name }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModal"></button>
                </div>
                <div class="modal-body">
                    
                    @if($selectedProduct->variants->count() > 0)
                    <div class="mb-4">
                        <label class="form-label fw-bold">Pilih Varian</label>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($selectedProduct->variants as $variant)
                                <input type="radio" class="btn-check" name="variant" id="variant_{{ $variant->id }}" value="{{ $variant->id }}" wire:model="selectedVariant">
                                <label class="btn btn-outline-success rounded-pill" for="variant_{{ $variant->id }}">
                                    {{ $variant->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <div class="mb-4">
                        <label class="form-label fw-bold">Pilih Topping (Opsional)</label>
                        <div class="d-flex flex-column gap-2">
                            @foreach($toppings as $topping)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="{{ $topping->id }}" id="topping_{{ $topping->id }}" wire:model="selectedToppings">
                                    <label class="form-check-label d-flex justify-content-between" for="topping_{{ $topping->id }}">
                                        <span>{{ $topping->name }}</span>
                                        <span class="text-muted">+Rp {{ number_format($topping->price, 0, ',', '.') }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Jumlah</label>
                        <div class="input-group w-50" x-data="{ qty: @entangle('quantity') }">
                            <button class="btn btn-outline-secondary fw-bold" type="button" x-on:click="if(qty > 1) qty--">-</button>
                            <input type="text" class="form-control text-center" x-model="qty" readonly>
                            <button class="btn btn-outline-secondary fw-bold" type="button" x-on:click="qty++">+</button>
                        </div>
                    </div>

                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light" wire:click="closeModal">Batal</button>
                    <button type="button" class="btn btn-success px-4" wire:click="addToCart">Tambahkan ke Pesanan</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Custom Modal Overlay Payment -->
    @if($showPaymentModal)
    <div class="modal-backdrop fade show" style="z-index: 1040;"></div>
    <div class="modal fade show d-block" tabindex="-1" style="z-index: 1050;" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold">Selesaikan Pembayaran</h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closePaymentModal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <p class="text-muted mb-1">Total Tagihan</p>
                        <h2 class="fw-bold text-success">Rp {{ number_format($this->total, 0, ',', '.') }}</h2>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Pelanggan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('customerName') is-invalid @enderror" wire:model.live="customerName" placeholder="Wajib isi nama pelanggan">
                        @error('customerName') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-4" style="{{ empty(trim($customerName)) ? 'opacity: 0.5; pointer-events: none;' : '' }}">
                        <label class="form-label fw-bold">Metode Pembayaran</label>
                        <div class="d-flex gap-3">
                            <label class="form-check flex-fill border rounded p-3 text-center mb-0 {{ $paymentMethod === 'cash' ? 'border-success bg-success bg-opacity-10' : '' }}" style="cursor: pointer;" for="pay_cash">
                                <input class="form-check-input float-none mx-auto d-block mb-2" type="radio" name="paymentMethod" id="pay_cash" value="cash" wire:model.live="paymentMethod" @if(empty(trim($customerName))) disabled @endif>
                                <i class="bi bi-cash-stack fs-3 d-block text-success"></i>
                                Tunai (Cash)
                            </label>

                            <label class="form-check flex-fill border rounded p-3 text-center mb-0 {{ $paymentMethod === 'qris' ? 'border-primary bg-primary bg-opacity-10' : '' }}" style="cursor: pointer;" for="pay_qris">
                                <input class="form-check-input float-none mx-auto d-block mb-2" type="radio" name="paymentMethod" id="pay_qris" value="qris" wire:model.live="paymentMethod" @if(empty(trim($customerName))) disabled @endif>
                                <i class="bi bi-qr-code-scan fs-3 d-block text-primary"></i>
                                QRIS
                            </label>
                        </div>
                    </div>

                    @if(!empty(trim($customerName)))
                        @if($paymentMethod === 'cash')
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nominal Uang (Tunai)</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control form-control-lg @error('amountPaid') is-invalid @enderror" wire:model.live.debounce.300ms="amountPaid" placeholder="0">
                                </div>
                                @error('amountPaid') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>
                            
                            @if((int)$amountPaid > 0 && (int)$amountPaid >= $this->total)
                                <div class="alert alert-info py-2 mb-0">
                                    <div class="d-flex justify-content-between mb-0 align-items-center">
                                        <span>Kembalian:</span>
                                        <span class="fw-bold fs-5">Rp {{ number_format((int)$amountPaid - $this->total, 0, ',', '.') }}</span>
                                    </div>
                                </div>
                            @endif
                        @endif

                        @if($paymentMethod === 'qris')
                            <div class="text-center p-4 border rounded bg-light mb-3 d-flex flex-column align-items-center justify-content-center">
                                <img src="{{ asset('qris.png') }}" alt="QRIS" class="img-fluid mb-3 shadow-sm" style="max-height: 250px; border-radius: 12px; display: block; margin: 0 auto;">
                                <p class="mb-0 fw-bold text-center">Silakan arahkan pelanggan untuk scan QRIS di atas.</p>
                            </div>
                        @endif
                    @endif

                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light" wire:click="closePaymentModal">Batal</button>
                    <button type="button" class="btn btn-success px-4" wire:click="processPayment">
                        <i class="bi bi-check-circle me-1"></i> Selesaikan Transaksi
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Custom Modal Overlay Receipt (Struk) -->
    @if($showReceiptModal && $lastOrder)
    <div class="modal-backdrop fade show" style="z-index: 1060;"></div>
    <div class="modal fade show d-block" tabindex="-1" style="z-index: 1070;" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow">
                <div class="modal-body p-4 struk-font" id="print-area">
                    
                    <!-- Header Struk -->
                    <div class="text-center mb-4">
                        <h4 class="fw-bold mb-1">NYEMIL BEBS</h4>
                        <p class="mb-0 text-muted" style="font-size: 12px;">Purnama Town House Blok H/1</p>
                        <p class="mb-0 text-muted" style="font-size: 12px;">Telp: +62 823-9943-0312</p>
                    </div>
                    
                    <div class="mb-3 border-bottom border-dashed pb-2" style="font-size: 13px;">
                        <div class="d-flex justify-content-between">
                            <strong>No: {{ $lastOrder->invoice_number }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <strong>Tgl: {{ $lastOrder->created_at->format('d/m/Y H:i') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <strong>Kasir: {{ auth()->user()->username }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <strong>Pelanggan: {{ $lastOrder->customer_name }}</strong>
                        </div>
                    </div>

                    <!-- Isi Pesanan -->
                    <div class="mb-3 border-bottom border-dashed pb-2" style="font-size: 13px;">
                        @foreach($lastOrder->items as $item)
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
                            <span>Rp {{ number_format($lastOrder->total, 0, ',', '.') }}</span>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <span>Metode</span>
                            <span class="text-uppercase">{{ $lastOrder->payment_method }}</span>
                        </div>
                        @if($lastOrder->payment_method === 'cash')
                        <div class="d-flex justify-content-between">
                            <span>Tunai</span>
                            <span>Rp {{ number_format($lastOrder->total + $lastKembalian, 0, ',', '.') }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Kembali</span>
                            <span>Rp {{ number_format($lastKembalian, 0, ',', '.') }}</span>
                        </div>
                        @endif
                    </div>

                    <!-- Footer Struk -->
                    <div class="text-center" style="font-size: 12px;">
                        <p class="mb-1">Terima Kasih atas Kunjungan Anda!</p>
                        <p class="mb-0">~ Nyemil Bebs ~</p>
                    </div>

                </div>
                
                <div class="modal-footer border-top-0 pt-0 d-print-none justify-content-between">
                    <button type="button" class="btn btn-light" wire:click="$set('showReceiptModal', false)">Tutup</button>
                    <button type="button" class="btn btn-primary px-4" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Cetak Struk
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>