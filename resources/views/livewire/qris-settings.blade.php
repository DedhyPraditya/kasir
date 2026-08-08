<div>
    <div class="container-fluid py-4">
        {{-- Header --}}
        <div class="mb-4">
            <h3 class="fw-bold mb-0">Pengaturan QRIS</h3>
            <p class="text-muted mb-0 small">Ganti kode QRIS toko di sini. Setelah disimpan, kasir web & aplikasi mobile otomatis memakai QRIS baru dengan nominal yang menyesuaikan otomatis (dynamic).</p>
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

        <div class="row g-4">
            {{-- QRIS Aktif Saat Ini --}}
            <div class="col-md-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <h6 class="fw-bold mb-3">QRIS Aktif Saat Ini</h6>
                        @if($previewImage)
                            <img src="{{ $previewImage }}" alt="QRIS aktif" class="img-fluid mb-3" style="max-width: 240px; border-radius: 12px;">
                        @else
                            <p class="text-danger">Belum ada QRIS yang dikonfigurasi.</p>
                        @endif

                        @if($current)
                            <p class="small text-muted mb-0">
                                Diperbarui {{ $current->created_at->diffForHumans() }}
                                @if($current->updatedBy)
                                    oleh {{ $current->updatedBy->username ?? $current->updatedBy->name }}
                                @endif
                            </p>
                        @else
                            <p class="small text-muted mb-0">Masih memakai QRIS bawaan (default konfigurasi).</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Form Upload QRIS Baru --}}
            <div class="col-md-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Ganti QRIS</h6>

                        <div class="d-flex justify-content-end mb-3">
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="toggleManualInput">
                                @if($useManualInput)
                                    <i class="bi bi-image me-1"></i> Upload gambar saja
                                @else
                                    <i class="bi bi-input-cursor-text me-1"></i> Tempel string QRIS saja
                                @endif
                            </button>
                        </div>

                        @if(!$useManualInput)
                            <div class="mb-3">
                                <label class="form-label">Foto/screenshot kode QRIS baru</label>
                                <input type="file" wire:model="qrisImage" accept="image/*" class="form-control @error('qrisImage') is-invalid @enderror">
                                @error('qrisImage') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">Sistem akan otomatis membaca kode QR dari foto ini. Pastikan kode QR terlihat jelas & tidak terpotong.</div>
                                <div wire:loading wire:target="qrisImage" class="small text-muted mt-1">Mengunggah gambar...</div>
                                @if($qrisImage)
                                    <img src="{{ $qrisImage->temporaryUrl() }}" class="img-fluid mt-2" style="max-width: 160px; border-radius: 8px;">
                                @endif
                            </div>
                        @else
                            <div class="mb-3">
                                <label class="form-label">String EMV QRIS (diawali "000201...")</label>
                                <textarea wire:model="manualPayload" rows="4" class="form-control font-monospace @error('manualPayload') is-invalid @enderror" placeholder="00020101021226..."></textarea>
                                @error('manualPayload') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">Dipakai kalau kamu sudah punya string QRIS-nya langsung dari bank/e-wallet, tanpa perlu upload gambar.</div>
                            </div>
                        @endif

                        <button type="button" wire:click="save" wire:loading.attr="disabled" class="btn btn-success">
                            <i class="bi bi-check-lg me-1"></i> Simpan QRIS Baru
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Riwayat QRIS Sebelumnya --}}
        @if($history->isNotEmpty())
            <div class="mt-4">
                <h6 class="fw-bold mb-3">Riwayat QRIS Sebelumnya</h6>
                <div class="row g-3">
                    @foreach($history as $item)
                        <div class="col-md-3 col-sm-4 col-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body text-center p-3">
                                    <img src="{{ $item['preview'] }}" alt="Riwayat QRIS" class="img-fluid mb-2" style="max-width: 120px; border-radius: 8px;">
                                    <p class="small text-muted mb-2">
                                        {{ $item['created_at']->diffForHumans() }}
                                        @if($item['updated_by'])
                                            <br>oleh {{ $item['updated_by'] }}
                                        @endif
                                    </p>
                                    <button type="button" class="btn btn-sm btn-outline-success w-100"
                                            wire:click="activate({{ $item['id'] }})"
                                            wire:confirm="Aktifkan kembali QRIS ini?">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Aktifkan
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
