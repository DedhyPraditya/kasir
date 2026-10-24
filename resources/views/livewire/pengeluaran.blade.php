<div>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-0">Catatan Pengeluaran</h3>
                <p class="text-muted small mb-0">Kelola dan pantau seluruh pengeluaran operasional toko.</p>
            </div>
            <div>
                <button class="btn btn-success shadow-sm" wire:click="openModal">
                    <i class="bi bi-plus-circle me-1"></i> + Catat Pengeluaran
                </button>
            </div>
        </div>

        @if (session()->has('message'))
            <div class="position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 9999; width: 90%; max-width: 420px;">
                <div class="alert alert-success alert-dismissible fade show shadow border-0" role="alert" style="background-color: #198754; color: white;">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                        <div>{{ session('message') }}</div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        @endif

        {{-- Summary Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-danger bg-opacity-10 text-danger rounded-3 p-3 me-3">
                                <i class="bi bi-wallet2 fs-3"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Total Pengeluaran (Tersaring)</div>
                                <div class="fw-bold fs-4 text-danger">Rp {{ number_format($filteredTotal, 0, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-3 me-3">
                                <i class="bi bi-calendar-event fs-3"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Pengeluaran Hari Ini</div>
                                <div class="fw-bold fs-4 text-dark">Rp {{ number_format($totalToday, 0, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-3 me-3">
                                <i class="bi bi-calendar3 fs-3"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Pengeluaran Bulan Ini</div>
                                <div class="fw-bold fs-4 text-primary">Rp {{ number_format($totalMonth, 0, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filter Bar --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-body p-3">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Dari Tanggal</label>
                        <input type="date" class="form-control" wire:model.live="dateFrom">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Sampai Tanggal</label>
                        <input type="date" class="form-control" wire:model.live="dateTo">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Kategori</label>
                        <select class="form-select" wire:model.live="categoryFilter">
                            <option value="">Semua Kategori</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Cari Keterangan</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" class="form-control" placeholder="Beli es, gas, dll..." wire:model.live.debounce.300ms="search">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3 py-3" style="width: 140px;">Tanggal</th>
                                <th style="width: 150px;">Kategori</th>
                                <th>Keterangan</th>
                                <th class="text-end" style="width: 180px;">Nominal</th>
                                <th style="width: 150px;">Dicatat Oleh</th>
                                <th class="text-center pe-3" style="width: 120px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expenses as $item)
                                <tr>
                                    <td class="ps-3 fw-medium">
                                        {{ $item->expense_date?->format('d/m/Y') ?? '-' }}
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary bg-opacity-10 text-dark px-2 py-1">
                                            {{ $item->category }}
                                        </span>
                                    </td>
                                    <td class="fw-semibold text-dark">
                                        {{ $item->description }}
                                    </td>
                                    <td class="text-end fw-bold text-danger">
                                        Rp {{ number_format($item->amount, 0, ',', '.') }}
                                    </td>
                                    <td class="text-muted small">
                                        <i class="bi bi-person me-1"></i>{{ $item->user?->username ?? 'Kasir' }}
                                    </td>
                                    <td class="text-center pe-3">
                                        <button class="btn btn-sm btn-outline-primary me-1" wire:click="edit('{{ $item->id }}')" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="if(confirm('Yakin ingin menghapus catatan pengeluaran ini?')) { @this.delete('{{ $item->id }}') }" title="Hapus">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="bi bi-receipt-cutoff fs-1 d-block mb-2 text-secondary"></i>
                                            Tidak ada catatan pengeluaran pada rentang waktu ini.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($expenses->hasPages())
                <div class="card-footer bg-white border-0 py-3">
                    {{ $expenses->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Modal Tambah / Edit --}}
    @if($isModalOpen)
        <div class="modal fade show d-block" style="background-color: rgba(0,0,0,0.5);" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">
                            {{ $editingId ? 'Edit Catatan Pengeluaran' : 'Catat Pengeluaran Baru' }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeModal"></button>
                    </div>
                    <form wire:submit.prevent="save">
                        <div class="modal-body py-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold mb-1">Tanggal</label>
                                <input type="date" class="form-control @error('expense_date') is-invalid @enderror" wire:model="expense_date">
                                @error('expense_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold mb-1">Kategori</label>
                                <select class="form-select @error('category') is-invalid @enderror" wire:model="category">
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat }}">{{ $cat }}</option>
                                    @endforeach
                                </select>
                                @error('category') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold mb-1">Nominal (Rp)</label>
                                <div class="input-group">
                                    <span class="input-group-text fw-bold">Rp</span>
                                    <input type="number" min="1" step="1" class="form-control @error('amount') is-invalid @enderror" placeholder="Contoh: 25000" wire:model="amount">
                                </div>
                                @error('amount') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold mb-1">Keterangan</label>
                                <textarea rows="3" class="form-control @error('description') is-invalid @enderror" placeholder="Rincian pengeluaran (misal: Beli gas elpiji 3kg, beli es batu, dll)" wire:model="description"></textarea>
                                @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light" wire:click="closeModal">Batal</button>
                            <button type="submit" class="btn btn-success px-4">
                                <i class="bi bi-save me-1"></i> Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
