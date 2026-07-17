<div>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold mb-0">Laporan Transaksi</h3>
            <a href="{{ route('laporan.export', ['dateFrom' => $dateFrom, 'dateTo' => $dateTo, 'search' => $search]) }}"
               class="btn btn-outline-danger btn-sm">
                <i class="bi bi-file-earmark-pdf me-1"></i> Export PDF
            </a>
        </div>

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

        {{-- Summary Cards --}}
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

        {{-- Table --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">No.</th>
                                <th>No. Invoice</th>
                                <th>Tanggal</th>
                                <th>Pelanggan</th>
                                <th>Item</th>
                                <th>Metode</th>
                                <th class="text-end pe-4">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $order)
                            <tr>
                                <td class="ps-4 text-muted">{{ $orders->firstItem() + $loop->index }}</td>
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
                                <td class="text-end pe-4 fw-bold">Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
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
</div>
