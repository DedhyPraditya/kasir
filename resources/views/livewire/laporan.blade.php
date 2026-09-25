<div>
    @include('partials.struk-style')

    <div class="container-fluid py-4 d-print-none">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold mb-0">Laporan Transaksi</h3>
            <div class="d-flex gap-2">
            @if($isDeveloper && count($selected) > 0)
            <button class="btn btn-danger btn-sm" wire:click="confirmDelete">
                <i class="bi bi-trash me-1"></i> Hapus Terpilih ({{ count($selected) }})
            </button>
            @endif
            @if($isAdmin)
            <a href="{{ route('laporan.export', ['dateFrom' => $dateFrom, 'dateTo' => $dateTo, 'search' => $search]) }}"
               class="btn btn-outline-danger btn-sm">
                <i class="bi bi-file-earmark-pdf me-1"></i> Export PDF
            </a>
            @endif
            </div>
        </div>

        @if (session()->has('message'))
            <div class="position-fixed top-0 start-50 translate-middle-x p-3 d-print-none" style="z-index: 9999; width: 90%; max-width: 400px;">
                <div class="alert alert-success alert-dismissible fade show shadow border-0" role="alert" style="background-color: #198754; color: white;">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                        <div>{{ session('message') }}</div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        @endif

        {{-- Filter Bar --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold mb-1">Dari Tanggal</label>
                        <input type="date" class="form-control" wire:model.live="dateFrom">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold mb-1">Sampai Tanggal</label>
                        <input type="date" class="form-control" wire:model.live="dateTo">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold mb-1">Cari Invoice / Pelanggan</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" placeholder="No. Invoice atau nama..." wire:model.live.debounce.300ms="search">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-secondary w-100" wire:click="$set('search','')">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Summary Cards (hanya Admin) --}}
        @if($isAdmin)
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3">
                        <p class="text-muted small mb-1">Total Pendapatan</p>
                        <h5 class="fw-bold text-success mb-0">Rp {{ number_format($totalPendapatan, 0, ',', '.') }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3">
                        <p class="text-muted small mb-1">Total Transaksi</p>
                        <h5 class="fw-bold text-primary mb-0">{{ $totalTransaksi }} Transaksi</h5>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3">
                        <p class="text-muted small mb-1">Tunai (Cash)</p>
                        <h5 class="fw-bold text-success mb-0">Rp {{ number_format($totalCash, 0, ',', '.') }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3">
                        <p class="text-muted small mb-1">QRIS</p>
                        <h5 class="fw-bold text-primary mb-0">Rp {{ number_format($totalQris, 0, ',', '.') }}</h5>
                    </div>
                </div>
            </div>
        </div>

        @endif

        {{-- Bar pilihan (developer) --}}
        @if($isDeveloper && count($selected) > 0)
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 py-2 mb-3">
            <i class="bi bi-check2-square"></i>
            <span><strong>{{ count($selected) }}</strong> transaksi dipilih.</span>
            @if(count($selected) < $totalTransaksi)
            <button type="button" class="btn btn-link btn-sm p-0" wire:click="selectAllFiltered">
                Pilih semua {{ $totalTransaksi }} transaksi sesuai filter
            </button>
            @else
            <span class="text-muted small">Semua transaksi sesuai filter terpilih. Hilangkan centang yang tidak ingin dihapus.</span>
            @endif
            <button type="button" class="btn btn-link btn-sm p-0 text-secondary ms-md-auto" wire:click="clearSelection">
                Batal pilih
            </button>
        </div>
        @endif

        {{-- Table --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                @if($isDeveloper)
                                <th class="ps-4" style="width: 1%;">
                                    <input type="checkbox" class="form-check-input" wire:key="select-page-{{ $pageAllSelected ? 'on' : 'off' }}"
                                           wire:click="toggleSelectPage" @checked($pageAllSelected) @disabled($orders->isEmpty())
                                           title="Pilih semua di halaman ini" aria-label="Pilih semua di halaman ini">
                                </th>
                                @endif
                                <th class="{{ $isDeveloper ? '' : 'ps-4' }}">No.</th>
                                <th>No. Invoice</th>
                                <th>Tanggal</th>
                                <th>Pelanggan</th>
                                <th>Item</th>
                                <th>Metode</th>
                                <th class="text-end">Total</th>
                                <th class="text-end pe-4" style="width: 1%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $order)
                            <tr wire:key="order-{{ $order->id }}">
                                @if($isDeveloper)
                                <td class="ps-4">
                                    <input type="checkbox" class="form-check-input" value="{{ $order->id }}" wire:model.live="selected" aria-label="Pilih {{ $order->invoice_number }}">
                                </td>
                                @endif
                                <td class="{{ $isDeveloper ? '' : 'ps-4' }} text-muted">{{ $orders->firstItem() + $loop->index }}</td>
                                <td><span class="fw-bold text-dark">{{ $order->invoice_number }}</span></td>
                                <td class="text-nowrap">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $order->customer_name }}</td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary">
                                        {{ $order->items->count() }} item
                                    </span>
                                </td>
                                <td>
                                    @if($order->payment_method === 'cash')
                                        <span class="badge bg-success">Tunai</span>
                                    @else
                                        <span class="badge bg-primary">QRIS</span>
                                    @endif
                                </td>
                                <td class="text-end fw-bold text-nowrap">Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                                <td class="text-end pe-4 text-nowrap">
                                    <button class="btn btn-outline-primary btn-sm" wire:click="showDetail('{{ $order->id }}')" title="Detail & cetak ulang struk">
                                        <i class="bi bi-eye me-1"></i> Detail
                                    </button>
                                    @if($isDeveloper)
                                    <button class="btn btn-outline-danger btn-sm" wire:click="confirmDelete('{{ $order->id }}')" title="Hapus transaksi">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $isDeveloper ? 9 : 8 }}" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                    Tidak ada transaksi pada periode ini
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($orders->hasPages())
            <div class="card-footer bg-white border-top-0">
                {{ $orders->links() }}
            </div>
            @endif
        </div>

    </div>

    {{-- Detail Transaksi + Cetak Ulang Struk --}}
    @if($detailOrder)
    <div class="modal-backdrop fade show" style="z-index: 1040;"></div>
    <div class="modal fade show d-block" tabindex="-1" style="z-index: 1050;" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-white border-bottom-0 pt-4 px-4 pb-0 d-print-none">
                    <h5 class="modal-title fw-bold text-primary">Detail Transaksi</h5>
                    <button type="button" class="btn-close" wire:click="closeDetail" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 d-print-none">
                    <dl class="row small mb-3">
                        <dt class="col-5 text-muted fw-normal">No. Invoice</dt>
                        <dd class="col-7 fw-bold mb-1">{{ $detailOrder->invoice_number }}</dd>
                        <dt class="col-5 text-muted fw-normal">Tanggal</dt>
                        <dd class="col-7 mb-1">{{ $detailOrder->created_at->format('d/m/Y H:i') }}</dd>
                        <dt class="col-5 text-muted fw-normal">Pelanggan</dt>
                        <dd class="col-7 mb-1">{{ $detailOrder->customer_name ?: '-' }}</dd>
                        <dt class="col-5 text-muted fw-normal">Metode</dt>
                        <dd class="col-7 mb-1">
                            @if($detailOrder->payment_method === 'cash')
                                <span class="badge bg-success">Tunai</span>
                            @else
                                <span class="badge bg-primary">QRIS</span>
                            @endif
                        </dd>
                        <dt class="col-5 text-muted fw-normal">Status</dt>
                        <dd class="col-7 mb-0 text-capitalize">{{ $detailOrder->status }}</dd>
                    </dl>

                    <div class="border rounded">
                        @foreach($detailOrder->items as $item)
                        <div class="d-flex justify-content-between align-items-start p-3 {{ $loop->last ? '' : 'border-bottom' }}">
                            <div class="me-3">
                                <div class="fw-semibold">
                                    {{ $item->product_name }}{{ $item->variant_name ? ' - '.$item->variant_name : '' }}
                                </div>
                                <div class="small text-muted">{{ $item->quantity }} x Rp {{ number_format($item->price, 0, ',', '.') }}</div>
                                @foreach($item->toppings as $topping)
                                <div class="small text-muted">+ {{ $topping->topping_name }} (Rp {{ number_format($topping->price, 0, ',', '.') }})</div>
                                @endforeach
                            </div>
                            <div class="fw-semibold text-nowrap">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</div>
                        </div>
                        @endforeach
                        <div class="d-flex justify-content-between p-3 bg-light fw-bold border-top">
                            <span>Total</span>
                            <span>Rp {{ number_format($detailOrder->total, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                {{-- Struk: tersembunyi di layar, hanya muncul saat dicetak --}}
                <div class="modal-body p-4 struk-font d-none d-print-block" id="print-area">
                    @include('partials.struk', ['order' => $detailOrder, 'reprint' => true])
                </div>

                @include('partials.struk-actions', [
                    'receipt' => $detailOrder->receiptData(reprint: true),
                    'closeAction' => 'closeDetail',
                    'label' => 'Cetak Ulang Struk',
                ])
            </div>
        </div>
    </div>
    @endif

    {{-- Konfirmasi Hapus (developer) --}}
    @if($isDeveloper && count($pendingDelete) > 0)
    <div class="modal-backdrop fade show" style="z-index: 1040;"></div>
    <div class="modal fade show d-block" tabindex="-1" style="z-index: 1050;" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-white border-bottom-0 pt-4 px-4 pb-0">
                    <h5 class="modal-title fw-bold text-danger">Hapus Transaksi</h5>
                    <button type="button" class="btn-close" wire:click="cancelDelete" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4">
                    Hapus <strong>{{ count($pendingDelete) }} transaksi</strong> beserta item dan toppingnya?
                    Data yang dihapus tidak bisa dikembalikan.
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4">
                    <button type="button" class="btn btn-light" wire:click="cancelDelete">Batal</button>
                    <button type="button" class="btn btn-danger" wire:click="deleteOrders" wire:loading.attr="disabled">
                        <i class="bi bi-trash me-1"></i> Ya, Hapus
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
