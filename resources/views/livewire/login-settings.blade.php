<div>
    <div class="container-fluid py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h3 class="fw-bold mb-0">Tampilan Login</h3>
                <p class="text-muted mb-0 small">Atur logo, judul, dan deskripsi yang tampil di halaman login.</p>
            </div>
            <a href="{{ route('login') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-box-arrow-up-right me-1"></i> Lihat halaman login
            </a>
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

        <form wire:submit="save">
            <div class="row g-4">
                {{-- Form --}}
                <div class="col-xl-7">
                    {{-- Logo --}}
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">Logo</h6>
                            <div class="d-flex flex-wrap align-items-center gap-3">
                                <div class="border rounded-3 bg-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 96px; height: 96px;">
                                    <img src="{{ $logoPreview }}" alt="Logo" style="max-width: 84px; max-height: 84px; object-fit: contain;">
                                </div>
                                <div class="flex-grow-1" style="min-width: 220px;">
                                    <input type="file" class="form-control @error('logo') is-invalid @enderror" wire:model="logo" accept="image/png,image/jpeg,image/webp">
                                    <div class="form-text">PNG transparan paling bagus. Maks 4 MB. Dipakai di atas form login dan di panel kiri.</div>
                                    <div wire:loading wire:target="logo" class="small text-success"><span class="spinner-border spinner-border-sm me-1"></span> Mengunggah...</div>
                                    @error('logo') <div class="text-danger small">{{ $message }}</div> @enderror
                                    @if($logoIsCustom)
                                    <button type="button" class="btn btn-link btn-sm text-danger px-0" wire:click="useDefaultLogo">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Pakai logo bawaan
                                    </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Teks --}}
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0">Teks Panel</h6>
                                <button type="button" class="btn btn-link btn-sm px-0" wire:click="resetText">Kembalikan teks bawaan</button>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold small mb-1">Judul</label>
                                <input type="text" class="form-control @error('headline') is-invalid @enderror" wire:model.live.debounce.400ms="headline" maxlength="120">
                                @error('headline') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label class="form-label fw-semibold small mb-1">Deskripsi</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" rows="3" wire:model.live.debounce.400ms="description" maxlength="300"></textarea>
                                @error('description') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Pratinjau --}}
                <div class="col-xl-5">
                    <div class="position-sticky" style="top: 1rem;">
                        <div class="small fw-semibold mb-2">Pratinjau panel login</div>
                        <div class="rounded-4 p-4 text-white" style="background: #198754;">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <img src="{{ $logoPreview }}" alt="" style="width: 36px; height: 36px; object-fit: contain; background: #fff; border-radius: 10px; padding: 3px;">
                                <span class="fw-bold" style="letter-spacing: .14em;">NYEMIL BEBS</span>
                            </div>
                            <div class="fw-bold fs-4 lh-sm mb-2" style="letter-spacing: -.02em;">{{ $headline }}</div>
                            <div class="small" style="color: #d1e7dd;">{{ $description }}</div>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-success px-4" wire:loading.attr="disabled" wire:target="save,logo">
                                <i class="bi bi-save me-1"></i> Simpan
                            </button>
                        </div>
                        @if($removeLogo || $logo)
                        <div class="small text-warning-emphasis mt-2"><i class="bi bi-info-circle me-1"></i> Perubahan logo belum disimpan.</div>
                        @endif
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
