<div>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-0">Rekonsiliasi Kasir (Tutup Kas)</h3>
                <p class="text-muted small mb-0">Hitung dan cocokkan uang fisik di laci kasir dengan transaksi sistem.</p>
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

        <div class="row g-4">
            {{-- Form Rekonsiliasi --}}
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 pt-3 pb-0">
                        <h5 class="fw-bold mb-1"><i class="bi bi-calculator me-2 text-success"></i>Form Tutup Kas</h5>
                        <p class="text-muted small mb-0">Masukkan modal dan hitung uang laci saat ini.</p>
                    </div>
                    <div class="card-body p-4">
                        <form wire:submit.prevent="submit">
                            <div class="row g-2 mb-3">
                                <div class="col-7">
                                    <label class="form-label small fw-semibold text-muted mb-1">Tanggal Rekonsiliasi</label>
                                    <input type="date" class="form-control" wire:model.live="reconciliation_date">
                                </div>
                                <div class="col-5">
                                    <label class="form-label small fw-semibold text-muted mb-1">Shift</label>
                                    <select class="form-select" wire:model.live="shift_name">
                                        <option value="Harian">Harian</option>
                                        <option value="Shift 1 (Pagi)">Shift 1 (Pagi)</option>
                                        <option value="Shift 2 (Sore/Malam)">Shift 2 (Sore/Malam)</option>
                                    </select>
                                </div>
                            </div>

                            {{-- Rincian Otomatis Sistem --}}
                            <div class="p-3 bg-light rounded-3 mb-3 border">
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span>Penjualan Tunai (Sistem)</span>
                                    <span class="fw-bold text-success">+ Rp {{ number_format($cashSales, 0, ',', '.') }}</span>
                                </div>
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span>Pengeluaran Kasir (Sistem)</span>
                                    <span class="fw-bold text-danger">- Rp {{ number_format($cashExpenses, 0, ',', '.') }}</span>
                                </div>
                                <div class="d-flex justify-content-between small text-muted pt-1 border-top">
                                    <span>Penjualan QRIS (Bank)</span>
                                    <span class="text-primary">Rp {{ number_format($qrisSales, 0, ',', '.') }}</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold mb-1">1. Modal Awal Kasir (Uang Kembalian di Laci)</label>
                                <div class="input-group">
                                    <span class="input-group-text fw-bold">Rp</span>
                                    <input type="number" min="0" step="1000" class="form-control" placeholder="Contoh: 150000" wire:model.live.debounce.300ms="starting_cash">
                                </div>
                            </div>

                            {{-- Kas Seharusnya --}}
                            <div class="card bg-success bg-opacity-10 border-success border-opacity-25 mb-3">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-success small fw-semibold">Kas Seharusnya di Laci</div>
                                            <div class="small text-muted" style="font-size: 11px;">(Modal Awal + Tunai - Pengeluaran)</div>
                                        </div>
                                        <div class="fw-bold fs-4 text-success">
                                            Rp {{ number_format($expectedCash, 0, ',', '.') }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold mb-1">2. Uang Fisik Nyata di Laci (Dihitung Kasir) *</label>
                                <div class="input-group">
                                    <span class="input-group-text fw-bold">Rp</span>
                                    <input type="number" min="0" step="1000" class="form-control form-control-lg fw-bold @error('actual_cash') is-invalid @enderror" placeholder="Total hitungan uang laci..." wire:model.live.debounce.300ms="actual_cash">
                                </div>
                                @error('actual_cash') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            {{-- Status Selisih Live --}}
                            @if($difference !== null)
                                <div class="alert {{ $difference == 0 ? 'alert-success' : ($difference > 0 ? 'alert-primary' : 'alert-danger') }} d-flex justify-content-between align-items-center py-2 mb-3">
                                    <div>
                                        <i class="bi {{ $difference == 0 ? 'bi-check-circle-fill' : ($difference > 0 ? 'bi-plus-circle-fill' : 'bi-exclamation-triangle-fill') }} me-1"></i>
                                        <strong>Selisih:</strong>
                                    </div>
                                    <div class="fw-bold fs-5">
                                        @if($difference == 0)
                                            <span class="badge bg-success">PAS (Rp 0)</span>
                                        @elseif($difference > 0)
                                            <span class="badge bg-primary">+ Rp {{ number_format($difference, 0, ',', '.') }} (LEBIH)</span>
                                        @else
                                            <span class="badge bg-danger">- Rp {{ number_format(abs($difference), 0, ',', '.') }} (KURANG / TEKOR)</span>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <div class="mb-4">
                                <label class="form-label small fw-semibold text-muted mb-1">Catatan / Keterangan (Opsional)</label>
                                <textarea rows="2" class="form-control" placeholder="Alasan selisih jika ada..." wire:model="notes"></textarea>
                            </div>

                            <button type="submit" class="btn btn-success btn-lg w-100 fw-bold shadow-sm">
                                <i class="bi bi-lock-fill me-1"></i> Simpan & Tutup Kasir
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Riwayat Rekonsiliasi --}}
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-1"><i class="bi bi-clock-history me-2 text-primary"></i>Riwayat Tutup Kasir</h5>
                            <p class="text-muted small mb-0">Arsip rekonsiliasi dan bukti serah terima kasir.</p>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3 py-3">Tanggal & Shift</th>
                                        <th>Kasir</th>
                                        <th class="text-end">Kas Seharusnya</th>
                                        <th class="text-end">Uang Fisik</th>
                                        <th class="text-center">Selisih</th>
                                        <th class="text-center pe-3">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($history as $item)
                                        <tr>
                                            <td class="ps-3">
                                                <div class="fw-semibold">{{ $item->reconciliation_date?->format('d/m/Y') }}</div>
                                                <small class="text-muted">{{ $item->shift_name }}</small>
                                            </td>
                                            <td class="small">
                                                <i class="bi bi-person me-1"></i>{{ $item->user?->username ?? 'Kasir' }}
                                            </td>
                                            <td class="text-end small">
                                                Rp {{ number_format($item->expected_cash, 0, ',', '.') }}
                                            </td>
                                            <td class="text-end fw-bold">
                                                Rp {{ number_format($item->actual_cash, 0, ',', '.') }}
                                            </td>
                                            <td class="text-center">
                                                @if($item->difference == 0)
                                                    <span class="badge bg-success bg-opacity-10 text-success">PAS</span>
                                                @elseif($item->difference > 0)
                                                    <span class="badge bg-primary bg-opacity-10 text-primary">+{{ number_format($item->difference, 0, ',', '.') }}</span>
                                                @else
                                                    <span class="badge bg-danger bg-opacity-10 text-danger">-{{ number_format(abs($item->difference), 0, ',', '.') }}</span>
                                                @endif
                                            </td>
                                            <td class="text-center pe-3">
                                                <button class="btn btn-sm btn-outline-primary" wire:click="showDetail('{{ $item->id }}')" title="Lihat Struk Rekonsiliasi">
                                                    <i class="bi bi-receipt"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">
                                                <i class="bi bi-journal-x fs-2 d-block mb-2 text-secondary"></i>
                                                Belum ada riwayat rekonsiliasi kasir.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if($history->hasPages())
                        <div class="card-footer bg-white border-0 py-3">
                            {{ $history->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Struk Detail Rekonsiliasi --}}
    @if($selectedRecon)
        <div class="modal fade show d-block" style="background-color: rgba(0,0,0,0.5);" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-header border-0 pb-0">
                        <h6 class="modal-title fw-bold">Bukti Rekonsiliasi Kasir</h6>
                        <button type="button" class="btn-close" wire:click="closeDetail"></button>
                    </div>
                    <div class="modal-body p-4" id="struk-recon-print">
                        <div class="text-center mb-3">
                            <h6 class="fw-bold mb-1">{{ strtoupper($selectedRecon->store?->name ?? 'KASIR POS') }}</h6>
                            <div class="small text-muted">{{ $selectedRecon->store?->address }}</div>
                            <hr class="my-2 border-dark">
                            <div class="fw-bold small">LAPORAN TUTUP KASIR</div>
                        </div>

                        <div class="small lh-sm mb-2">
                            <div class="d-flex justify-content-between">
                                <span>Tanggal:</span>
                                <span>{{ $selectedRecon->reconciliation_date?->format('d/m/Y') }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Shift:</span>
                                <span>{{ $selectedRecon->shift_name }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Kasir:</span>
                                <span>{{ $selectedRecon->user?->username ?? 'Kasir' }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Waktu Cetak:</span>
                                <span>{{ $selectedRecon->created_at?->format('d/m/Y H:i') }}</span>
                            </div>
                        </div>

                        <hr class="my-2" style="border-top: 1px dashed #666;">

                        <div class="small lh-lg">
                            <div class="d-flex justify-content-between">
                                <span>Modal Awal:</span>
                                <span>Rp {{ number_format($selectedRecon->starting_cash, 0, ',', '.') }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Penjualan Tunai (+):</span>
                                <span>Rp {{ number_format($selectedRecon->cash_sales, 0, ',', '.') }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Pengeluaran Kasir (-):</span>
                                <span>Rp {{ number_format($selectedRecon->cash_expenses, 0, ',', '.') }}</span>
                            </div>
                            <div class="d-flex justify-content-between fw-bold pt-1 border-top">
                                <span>Kas Seharusnya:</span>
                                <span>Rp {{ number_format($selectedRecon->expected_cash, 0, ',', '.') }}</span>
                            </div>
                            <div class="d-flex justify-content-between fw-bold">
                                <span>Uang Fisik di Laci:</span>
                                <span>Rp {{ number_format($selectedRecon->actual_cash, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <hr class="my-2" style="border-top: 1px dashed #666;">

                        <div class="d-flex justify-content-between fw-bold fs-6">
                            <span>SELISIH:</span>
                            @if($selectedRecon->difference == 0)
                                <span class="text-success">PAS (Rp 0)</span>
                            @elseif($selectedRecon->difference > 0)
                                <span class="text-primary">+ Rp {{ number_format($selectedRecon->difference, 0, ',', '.') }}</span>
                            @else
                                <span class="text-danger">- Rp {{ number_format(abs($selectedRecon->difference), 0, ',', '.') }}</span>
                            @endif
                        </div>

                        <div class="small text-muted mt-2">
                            <span>Non-Tunai (QRIS):</span>
                            <span class="float-end">Rp {{ number_format($selectedRecon->qris_sales, 0, ',', '.') }}</span>
                        </div>

                        @if($selectedRecon->notes)
                            <div class="small mt-2 p-2 bg-light rounded border">
                                <strong>Catatan:</strong> {{ $selectedRecon->notes }}
                            </div>
                        @endif

                        <div class="row mt-4 text-center small">
                            <div class="col-6">
                                <div>Kasir</div>
                                <div style="height: 40px;"></div>
                                <div>( {{ $selectedRecon->user?->username ?? 'Kasir' }} )</div>
                            </div>
                            <div class="col-6">
                                <div>Supervisor/Owner</div>
                                <div style="height: 40px;"></div>
                                <div>( ..................... )</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light" wire:click="closeDetail">Tutup</button>
                        <button type="button" class="btn btn-primary" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i> Cetak Struk
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
