<div>
    <div class="container-fluid py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h3 class="fw-bold mb-0">Kelola Toko</h3>
                <p class="text-muted mb-0 small">Tiap toko punya produk, transaksi, dan pengaturan sendiri. Akun admin dan kasir dihubungkan ke toko di menu Kelola Pengguna.</p>
            </div>
            <button type="button" class="btn btn-primary" wire:click="create">
                <i class="bi bi-plus-lg me-1"></i> Tambah Toko
            </button>
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

        @if (session()->has('error'))
            <div class="position-fixed top-0 start-50 translate-middle-x p-3 d-print-none" style="z-index: 9999; width: 90%; max-width: 400px;">
                <div class="alert alert-danger alert-dismissible fade show shadow border-0" role="alert" style="background-color: #dc3545; color: white;">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                        <div>{{ session('error') }}</div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        @endif

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Nama Toko</th>
                                <th>Alamat</th>
                                <th>Telepon</th>
                                <th class="text-center">Akun</th>
                                <th class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stores as $store)
                            <tr wire:key="store-{{ $store->id }}">
                                <td class="ps-4 fw-semibold">{{ $store->name }}</td>
                                <td class="small">{{ $store->address ?: '—' }}</td>
                                <td class="small text-nowrap">{{ $store->phone ?: '—' }}</td>
                                <td class="text-center"><span class="badge bg-light text-dark border">{{ $store->users_count }}</span></td>
                                <td class="text-end pe-4 text-nowrap">
                                    <button type="button" class="btn btn-outline-primary btn-sm" wire:click="edit('{{ $store->id }}')">
                                        <i class="bi bi-pencil me-1"></i> Ubah
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" wire:click="confirmDelete('{{ $store->id }}')" title="Hapus toko">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">Belum ada toko. Klik Tambah Toko.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Form Tambah / Ubah --}}
    @if($showForm)
    <div class="modal-backdrop fade show" style="z-index: 1040;"></div>
    <div class="modal fade show d-block" tabindex="-1" style="z-index: 1050;" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-white border-bottom-0 pt-4 px-4 pb-0">
                    <h5 class="modal-title fw-bold text-primary">{{ $editingId ? 'Ubah Toko' : 'Tambah Toko' }}</h5>
                    <button type="button" class="btn-close" wire:click="closeForm" aria-label="Close"></button>
                </div>
                <form wire:submit="save">
                    <div class="modal-body px-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nama Toko <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" autocomplete="off">
                            @error('name') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Alamat</label>
                            <input type="text" class="form-control @error('address') is-invalid @enderror" wire:model="address">
                            @error('address') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold">Telepon</label>
                            <input type="text" class="form-control @error('phone') is-invalid @enderror" wire:model="phone">
                            @error('phone') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 px-4 pb-4">
                        <button type="button" class="btn btn-light" wire:click="closeForm">Batal</button>
                        <button type="submit" class="btn btn-primary px-4" wire:loading.attr="disabled">
                            <i class="bi bi-save me-1"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Konfirmasi Hapus --}}
    @if($deleting)
    <div class="modal-backdrop fade show" style="z-index: 1040;"></div>
    <div class="modal fade show d-block" tabindex="-1" style="z-index: 1050;" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-white border-bottom-0 pt-4 px-4 pb-0">
                    <h5 class="modal-title fw-bold text-danger">Hapus Toko</h5>
                    <button type="button" class="btn-close" wire:click="cancelDelete" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4">
                    Hapus toko <strong>{{ $deleting->name }}</strong>? Toko yang masih punya akun atau data tidak bisa dihapus.
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4">
                    <button type="button" class="btn btn-light" wire:click="cancelDelete">Batal</button>
                    <button type="button" class="btn btn-danger" wire:click="delete" wire:loading.attr="disabled">
                        <i class="bi bi-trash me-1"></i> Ya, Hapus
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
