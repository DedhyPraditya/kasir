<div>
    @include('partials.struk-style')

    <style>
        .pos-product-card {
            border-radius: 14px;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            cursor: pointer;
            border: 1px solid rgba(0, 0, 0, 0.06) !important;
            background: #ffffff;
        }
        .pos-product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08) !important;
        }
        .pos-product-card:active {
            transform: scale(0.96) !important;
        }
        .pos-product-img-box {
            height: 90px;
            background-color: rgba(25, 135, 84, 0.08);
            border-top-left-radius: 14px;
            border-top-right-radius: 14px;
        }
        .pos-product-img {
            max-height: 75px;
            max-width: 90%;
            object-fit: contain;
        }
        .pos-product-title {
            font-size: 0.82rem;
            line-height: 1.25;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 2.05rem;
        }
        .pos-product-price {
            font-size: 0.8rem;
        }
        @media (min-width: 768px) {
            .pos-product-card {
                border-radius: 16px;
            }
            .pos-product-img-box {
                height: 125px;
                border-top-left-radius: 16px;
                border-top-right-radius: 16px;
            }
            .pos-product-img {
                max-height: 110px;
            }
            .pos-product-title {
                font-size: 0.92rem;
                min-height: 2.3rem;
            }
            .pos-product-price {
                font-size: 0.88rem;
            }
        }
    </style>

    <div class="container-fluid py-3 px-2 px-md-3 d-print-none {{ count($cart) > 0 ? 'pb-5 mb-4' : '' }}">
        @if (session()->has('success'))
            <div class="position-fixed top-0 start-50 translate-middle-x p-3 d-print-none" style="z-index: 9999; width: 90%; max-width: 400px;">
                <div class="alert alert-success alert-dismissible fade show shadow border-0" role="alert" style="background-color: #198754; color: white;">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                        <div>{{ session('success') }}</div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        @endif

        <div class="row g-3">
            <!-- Left Side: Products -->
            <div class="col-md-7 col-lg-8">
                <div class="card shadow-sm mb-4 border-0" style="border-radius: 16px;">
                    <div class="card-header bg-white border-bottom-0 pt-3 pt-md-4 pb-0">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <h5 class="mb-0 fw-bold text-success">
                                <i class="bi bi-grid-fill me-2"></i>Daftar Menu
                            </h5>
                            <div class="position-relative" style="min-width: 220px; max-width: 320px; flex-grow: 1;">
                                <div class="input-group input-group-sm shadow-sm" style="border-radius: 10px; overflow: hidden;">
                                    <span class="input-group-text bg-light border-end-0 text-muted">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control bg-light border-start-0 ps-0" 
                                           placeholder="Cari menu..." 
                                           wire:model.live.debounce.300ms="search">
                                    @if(!empty($search))
                                        <button class="btn btn-light border-start-0 text-muted" 
                                                type="button" 
                                                wire:click="resetSearch"
                                                title="Hapus pencarian">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Category Filter Pills -->
                        @if($categories->count() > 0)
                        <div class="d-flex gap-2 overflow-auto pb-3 pt-1" style="white-space: nowrap; scrollbar-width: thin;">
                            <button type="button" 
                                    class="btn btn-sm rounded-pill px-3 {{ empty($selectedCategory) ? 'btn-success text-white fw-bold shadow-sm' : 'btn-outline-secondary' }}" 
                                    wire:click="filterCategory('')">
                                Semua ({{ $products->count() }})
                            </button>
                            @foreach($categories as $cat)
                                <button type="button" 
                                        class="btn btn-sm rounded-pill px-3 {{ $selectedCategory === $cat->id ? 'btn-success text-white fw-bold shadow-sm' : 'btn-outline-secondary' }}" 
                                        wire:click="filterCategory('{{ $cat->id }}')">
                                    {{ $cat->name }}
                                </button>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    <div class="card-body p-2 p-md-3">
                        <div class="row g-2 g-md-3">
                            @forelse($products as $product)
                            <div class="col-6 col-sm-6 col-md-4 col-xl-3">
                                <div class="card h-100 shadow-sm border-0 pos-product-card overflow-hidden" 
                                     wire:click="selectProduct('{{ $product->id }}')">
                                    @if($product->image)
                                        <div class="d-flex align-items-center justify-content-center p-2 pos-product-img-box">
                                            <img src="{{ asset('storage/' . $product->image) }}" 
                                                 alt="{{ $product->name }}" 
                                                 class="pos-product-img rounded">
                                        </div>
                                    @else
                                        <div class="d-flex align-items-center justify-content-center pos-product-img-box">
                                            <i class="bi bi-cup-hot-fill text-success opacity-50" style="font-size: 1.8rem;"></i>
                                        </div>
                                    @endif
                                    <div class="card-body p-2 p-md-3 d-flex flex-column justify-content-between">
                                        <div class="pos-product-title fw-bold text-dark mb-1 text-start">
                                            {{ $product->name }}
                                        </div>
                                        <div class="pos-product-price text-success fw-bold text-start">
                                            Rp {{ number_format($product->base_price, 0, ',', '.') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @empty
                            <div class="col-12 text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-search fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                    <h5 class="fw-bold text-dark">Menu tidak ditemukan</h5>
                                    <p class="small text-muted mb-3">
                                        @if(!empty($search))
                                            Tidak ada menu dengan kata kunci "<strong>{{ $search }}</strong>".
                                        @else
                                            Tidak ada menu di kategori ini.
                                        @endif
                                    </p>
                                    @if(!empty($search) || !empty($selectedCategory))
                                        <button class="btn btn-sm btn-outline-success rounded-pill px-3" wire:click="$set('search', ''); $set('selectedCategory', '')">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Filter
                                        </button>
                                    @endif
                                </div>
                            </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Cart -->
            <div class="col-md-5 col-lg-4" id="cart-section">
                <div class="card shadow-sm border-0 h-100 d-flex flex-column" style="border-radius: 16px;">
                    <div class="card-header bg-white border-bottom-0 pt-3 pt-md-4 pb-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold">
                                <i class="bi bi-cart3 me-1 text-success"></i>Pesanan
                            </h5>
                            @if(count($cart) > 0)
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2">
                                    {{ count($cart) }} item
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="card-body p-0 flex-grow-1" style="overflow-y: auto; max-height: 60vh;">
                        <ul class="list-group list-group-flush">
                            @forelse($cart as $item)
                                <li class="list-group-item py-2 py-md-3 px-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div style="max-width: 65%;">
                                            <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.9rem;">{{ $item['product_name'] }}</h6>
                                            <small class="text-muted d-block" style="font-size: 0.78rem;">
                                                @if($item['variant_name'])
                                                    Varian: {{ $item['variant_name'] }} <br>
                                                @endif
                                                @if(count($item['toppings']) > 0)
                                                    Topping: {{ implode(', ', array_column($item['toppings'], 'name')) }} <br>
                                                @endif
                                            </small>
                                            <div class="mt-1">
                                                <span class="badge bg-light text-dark border" style="font-size: 0.75rem;">
                                                    {{ $item['quantity'] }} x Rp {{ number_format($item['price'], 0, ',', '.') }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold mb-1 text-success" style="font-size: 0.9rem;">
                                                Rp {{ number_format($item['subtotal'], 0, ',', '.') }}
                                            </div>
                                            <button class="btn btn-sm btn-outline-danger p-1 px-2 rounded-2" 
                                                    wire:click="removeFromCart('{{ $item['id'] }}')" 
                                                    title="Hapus">
                                                <i class="bi bi-trash" style="font-size: 0.85rem;"></i>
                                            </button>
                                        </div>
                                    </div>
                                </li>
                            @empty
                                <li class="list-group-item text-center text-muted py-5 border-0">
                                    <i class="bi bi-cart-x fs-1 d-block mb-3 text-secondary opacity-50"></i>
                                    <div class="fw-bold">Belum ada pesanan</div>
                                    <small class="text-muted">Pilih menu di samping untuk menambahkan</small>
                                </li>
                            @endforelse
                        </ul>
                    </div>
                    <div class="card-footer bg-white border-top p-3 p-md-4 mt-auto" style="border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                        <div class="d-flex justify-content-between mb-3 fs-5">
                            <span class="fw-bold">Total:</span>
                            <span class="fw-bold text-success">Rp {{ number_format($this->total, 0, ',', '.') }}</span>
                        </div>
                        <button class="btn btn-success w-100 py-3 fw-bold fs-6 rounded-3 shadow-sm" wire:click="openPaymentModal" @if(empty($cart)) disabled @endif>
                            <i class="bi bi-credit-card me-1"></i>Proses Pembayaran
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @if(count($cart) > 0)
        <!-- Sticky Bottom Cart Bar for Mobile Web View -->
        <div class="d-md-none position-fixed bottom-0 start-0 end-0 p-3 bg-white border-top shadow-lg" style="z-index: 1030;">
            <div class="d-flex justify-content-between align-items-center gap-2">
                <div>
                    <div class="small text-muted">{{ count($cart) }} menu dipilih</div>
                    <div class="fw-bold text-success fs-6">Rp {{ number_format($this->total, 0, ',', '.') }}</div>
                </div>
                <div class="d-flex gap-2">
                    <a href="#cart-section" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
                        <i class="bi bi-cart-fill me-1"></i>Pesanan
                    </a>
                    <button class="btn btn-success btn-sm px-4 rounded-pill fw-bold" wire:click="openPaymentModal">
                        Bayar <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>
        @endif
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

                    @if($selectedProduct->category->allow_topping ?? true)
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
                    @endif

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
                                @if($this->qrisImage)
                                    <img src="{{ $this->qrisImage }}" alt="QRIS" class="img-fluid mb-3 shadow-sm" style="max-height: 250px; border-radius: 12px; display: block; margin: 0 auto;">
                                    <p class="mb-0 fw-bold text-center">Scan QRIS untuk bayar Rp {{ number_format($this->total, 0, ',', '.') }}</p>
                                @else
                                    <p class="mb-0 text-danger text-center">QRIS belum dikonfigurasi.</p>
                                @endif
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
                    
                    @include('partials.struk', ['order' => $lastOrder, 'kasir' => auth()->user()->username, 'kembalian' => $lastKembalian])

                </div>
                
                @include('partials.struk-actions', [
                    'receipt' => $lastOrder->receiptData(auth()->user()->username, $lastKembalian),
                    'closeAction' => "\$set('showReceiptModal', false)",
                    'label' => 'Cetak Struk',
                ])
            </div>
        </div>
    </div>
    @endif
</div>
