<div>
    <div class="container-fluid py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h3 class="fw-bold mb-0">Kelola Pengguna</h3>
                <p class="text-muted mb-0 small">Tambah, ubah peran, reset password, atau hapus akun. Hanya akun developer yang bisa membuka halaman ini.</p>
            </div>
            <button type="button" class="btn btn-success" wire:click="create">
                <i class="bi bi-person-plus me-1"></i> Tambah Akun
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
            <div class="card-body border-bottom">
                <div class="input-group" style="max-width: 320px;">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" placeholder="Cari username..." wire:model.live.debounce.300ms="search">
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Username</th>
                                <th>Peran</th>
                                <th>Toko</th>
                                <th>Status APK Mobile</th>
                                <th>Dibuat</th>
                                <th class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $user)
                            @php $userRole = \App\Livewire\UserManagement::roleOf($user); @endphp
                            <tr wire:key="user-{{ $user->id }}">
                                <td class="ps-4">
                                    <span class="fw-semibold">{{ $user->username }}</span>
                                    @if($user->is(auth()->user()))
                                    <span class="badge bg-light text-secondary border ms-1">Anda</span>
                                    @endif
                                </td>
                                <td>
                                    @if($userRole === 'developer')
                                        <span class="badge bg-dark">Developer</span>
                                    @elseif($userRole === 'admin')
                                        <span class="badge bg-primary">Admin</span>
                                    @else
                                        <span class="badge bg-success">Kasir</span>
                                    @endif
                                </td>
                                <td class="small">
                                    @if($userRole === 'developer')
                                        <span class="text-muted">Semua toko</span>
                                    @else
                                        {{ $user->store?->name ?? '—' }}
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $latestToken = $user->apiTokens->first();
                                    @endphp
                                    @if($latestToken)
                                        @php
                                            $isOnline = $latestToken->last_used_at && $latestToken->last_used_at->isAfter(now()->subMinutes(30));
                                            $isRecent = $latestToken->last_used_at && $latestToken->last_used_at->isAfter(now()->subDays(2));
                                        @endphp
                                        <div class="d-inline-flex align-items-center gap-1">
                                            @if($isOnline)
                                                <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2" title="Terakhir aktif: {{ $latestToken->last_used_at->format('d/m/Y H:i') }}">
                                                    <i class="bi bi-phone-fill me-1"></i> Aktif ({{ $latestToken->last_used_at->diffForHumans() }})
                                                </span>
                                            @elseif($isRecent)
                                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle py-1 px-2" title="Terakhir aktif: {{ $latestToken->last_used_at->format('d/m/Y H:i') }}">
                                                    <i class="bi bi-phone me-1"></i> Terpasang ({{ $latestToken->last_used_at->diffForHumans() }})
                                                </span>
                                            @else
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle py-1 px-2" title="Terakhir aktif: {{ $latestToken->last_used_at ? $latestToken->last_used_at->format('d/m/Y H:i') : ($latestToken->created_at ? $latestToken->created_at->format('d/m/Y H:i') : '-') }}">
                                                    <i class="bi bi-phone me-1"></i> Terpasang ({{ $latestToken->last_used_at ? $latestToken->last_used_at->diffForHumans() : 'Belum aktif' }})
                                                </span>
                                            @endif

                                            <button type="button"
                                                    class="btn btn-sm btn-outline-danger border-0 p-1 ms-1"
                                                    wire:click="revokeApk('{{ $user->id }}')"
                                                    wire:confirm="Putuskan sesi APK untuk akun {{ $user->username }}? Aplikasi mobile kasir akan otomatis keluar/logout."
                                                    title="Putus Sesi APK (Logout dari HP)">
                                                <i class="bi bi-box-arrow-right"></i>
                                            </button>
                                        </div>
                                    @else
                                        <span class="badge bg-light text-muted border py-1 px-2">
                                            <i class="bi bi-phone me-1 opacity-50"></i> Belum Terpasang
                                        </span>
                                    @endif
                                </td>
                                <td class="text-muted small text-nowrap">{{ $user->created_at?->format('d/m/Y') }}</td>
                                <td class="text-end pe-4 text-nowrap">
                                    <button type="button" class="btn btn-outline-primary btn-sm" wire:click="edit('{{ $user->id }}')">
                                        <i class="bi bi-pencil me-1"></i> Ubah
                                    </button>
                                    @unless($user->is(auth()->user()))
                                    <button type="button" class="btn btn-outline-danger btn-sm" wire:click="confirmDelete('{{ $user->id }}')" title="Hapus akun">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    @endunless
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">Tidak ada akun yang cocok.</td>
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
                    <h5 class="modal-title fw-bold text-primary">{{ $editingId ? 'Ubah Akun' : 'Tambah Akun' }}</h5>
                    <button type="button" class="btn-close" wire:click="closeForm" aria-label="Close"></button>
                </div>
                <form wire:submit="save">
                    <div class="modal-body px-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('username') is-invalid @enderror" wire:model="username" autocomplete="off">
                            @error('username') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Peran <span class="text-danger">*</span></label>
                            <select class="form-select @error('role') is-invalid @enderror" wire:model="role">
                                @foreach($roleLabels as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Kasir: hanya POS &amp; Laporan di tokonya. Admin: semua menu di tokonya. Developer: semua toko, kelola toko &amp; pengguna.</div>
                            @error('role') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>

                        @if($role !== 'developer')
                        <div class="mb-3">
                            <label class="form-label fw-bold">Toko <span class="text-danger">*</span></label>
                            <select class="form-select @error('storeId') is-invalid @enderror" wire:model="storeId">
                                <option value="">— Pilih toko —</option>
                                @foreach($stores as $store)
                                <option value="{{ $store->id }}">{{ $store->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Akun hanya melihat data toko ini. Toko baru dibuat di menu Kelola Toko.</div>
                            @error('storeId') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                {{ $editingId ? 'Password Baru' : 'Password' }}
                                @unless($editingId) <span class="text-danger">*</span> @endunless
                            </label>
                            <div class="input-group" x-data="{ show: false }">
                                <input :type="show ? 'text' : 'password'" type="password" class="form-control @error('password') is-invalid @enderror" wire:model="password" autocomplete="new-password"
                                       placeholder="{{ $editingId ? 'Kosongkan jika tidak diganti' : 'Minimal 6 karakter' }}">
                                <button type="button" class="btn btn-outline-secondary" x-on:click="show = !show"
                                        :title="show ? 'Sembunyikan password' : 'Lihat password'" :aria-label="show ? 'Sembunyikan password' : 'Lihat password'">
                                    <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                                </button>
                            </div>
                            @error('password') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-bold">Ulangi Password</label>
                            <div class="input-group" x-data="{ show: false }">
                                <input :type="show ? 'text' : 'password'" type="password" class="form-control" wire:model="password_confirmation" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" x-on:click="show = !show"
                                        :title="show ? 'Sembunyikan password' : 'Lihat password'" :aria-label="show ? 'Sembunyikan password' : 'Lihat password'">
                                    <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                                </button>
                            </div>
                        </div>

                        @if($editingId)
                        <div class="form-text">Jika password atau peran diganti, akun ini otomatis keluar dari web &amp; aplikasi mobile dan harus login ulang.</div>
                        @endif
                    </div>
                    <div class="modal-footer border-top-0 px-4 pb-4">
                        <button type="button" class="btn btn-light" wire:click="closeForm">Batal</button>
                        <button type="submit" class="btn btn-success px-4" wire:loading.attr="disabled">
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
                    <h5 class="modal-title fw-bold text-danger">Hapus Akun</h5>
                    <button type="button" class="btn-close" wire:click="cancelDelete" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4">
                    Hapus akun <strong>{{ $deleting->username }}</strong>? Akun ini langsung keluar dari web &amp; aplikasi mobile dan tidak bisa login lagi.
                    Data transaksi tetap aman.
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
