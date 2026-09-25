<div>
    <div class="container-fluid py-4">
        {{-- Header --}}
        <div class="mb-4">
            <h3 class="fw-bold mb-0">Pengaturan Printer</h3>
            <p class="text-muted mb-0 small">Hubungkan printer thermal sekali saja di komputer ini, lalu atur isi header & footer struk.</p>
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

        <div class="row g-4">
            {{-- Koneksi Printer (per browser/komputer) --}}
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm h-100" wire:ignore>
                    <div class="card-body" id="printer-connection">
                        <h6 class="fw-bold mb-3"><i class="bi bi-bluetooth me-1"></i> Koneksi Printer</h6>

                        <div class="d-flex align-items-center p-3 rounded bg-light mb-3">
                            <span class="rounded-circle me-3 flex-shrink-0" data-el="dot" style="width: 14px; height: 14px; background: #adb5bd;"></span>
                            <div>
                                <div class="fw-semibold" data-el="state">Memeriksa...</div>
                                <div class="small text-muted" data-el="message"></div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <button type="button" class="btn btn-primary" data-el="choose">
                                <i class="bi bi-link-45deg me-1"></i> <span>Hubungkan Printer</span>
                            </button>
                            <button type="button" class="btn btn-outline-success" data-el="reconnect">
                                <i class="bi bi-arrow-repeat me-1"></i> Sambung Ulang
                            </button>
                            <button type="button" class="btn btn-outline-danger" data-el="forget">
                                <i class="bi bi-x-circle me-1"></i> Lupakan
                            </button>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small mb-1">Baud rate</label>
                            <select class="form-select form-select-sm" data-el="baud" style="max-width: 180px;">
                                @foreach([9600, 19200, 38400, 57600, 115200] as $baud)
                                <option value="{{ $baud }}">{{ $baud }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Biarkan 9600 kecuali hasil cetak berupa karakter acak.</div>
                        </div>

                        <div class="alert alert-warning small mb-0 d-none" data-el="unsupported">
                            Browser ini tidak mendukung koneksi printer langsung. Pakai <strong>Google Chrome</strong> atau <strong>Microsoft Edge</strong> di komputer, dan buka web lewat <strong>https://</strong>.
                        </div>

                        <details class="small text-muted">
                            <summary class="fw-semibold">Cara menghubungkan printer Bluetooth</summary>
                            <ol class="ps-3 mt-2 mb-0">
                                <li>Nyalakan printer, lalu pair di <em>Pengaturan Windows &rarr; Bluetooth &amp; perangkat</em> (PIN biasanya 0000 atau 1234).</li>
                                <li>Klik <strong>Hubungkan Printer</strong>, pilih port printer (mis. <em>Standard Serial over Bluetooth link (COMx)</em>), klik <em>Connect</em>.</li>
                                <li>Klik <strong>Tes Cetak</strong>. Selesai &mdash; berikutnya printer tersambung otomatis setiap web dibuka.</li>
                            </ol>
                        </details>
                    </div>
                </div>
            </div>

            {{-- Header & Footer Struk (berlaku untuk semua kasir) --}}
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-receipt me-1"></i> Header &amp; Footer Struk</h6>

                        @unless($isAdmin)
                        <div class="alert alert-info small py-2">Hanya admin yang dapat mengubah header &amp; footer struk.</div>
                        @endunless

                        <div class="row g-4">
                            <div class="col-md-7">
                                <form wire:submit="save">
                                    <fieldset @disabled(! $isAdmin)>
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold small mb-1">Nama Toko</label>
                                            <input type="text" class="form-control @error('storeName') is-invalid @enderror" wire:model.live.debounce.400ms="storeName" maxlength="40">
                                            @error('storeName') <span class="text-danger small">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold small mb-1">Header <span class="text-muted fw-normal">(alamat, telepon &mdash; satu baris per info)</span></label>
                                            <textarea class="form-control" rows="3" wire:model.live.debounce.400ms="headerText"></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold small mb-1">Footer <span class="text-muted fw-normal">(ucapan, info promo, dll.)</span></label>
                                            <textarea class="form-control" rows="3" wire:model.live.debounce.400ms="footerText"></textarea>
                                        </div>
                                        @if($isAdmin)
                                        <div class="d-flex flex-wrap gap-2">
                                            <button type="submit" class="btn btn-success">
                                                <i class="bi bi-save me-1"></i> Simpan
                                            </button>
                                            <button type="button" class="btn btn-light" wire:click="resetToDefault">Kembalikan Bawaan</button>
                                        </div>
                                        @endif
                                    </fieldset>
                                </form>
                            </div>

                            {{-- Pratinjau (lebar 32 karakter = kertas 58 mm) --}}
                            <div class="col-md-5" data-thermal>
                                <div class="small fw-semibold mb-1">Pratinjau</div>
                                <div class="border rounded bg-white p-3 text-center" style="font-family: 'Courier New', monospace; font-size: 12px; max-width: 260px;">
                                    <div class="fw-bold fs-6 text-break">{{ $storeName }}</div>
                                    @foreach($previewHeader as $line)
                                    <div class="text-break">{{ $line }}</div>
                                    @endforeach
                                    <div class="text-muted my-2">- - - isi pesanan - - -</div>
                                    @foreach($previewFooter as $line)
                                    <div class="text-break">{{ $line }}</div>
                                    @endforeach
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm mt-3" data-receipt="{{ json_encode($sampleReceipt) }}" onclick="ThermalPrinter.printFromButton(this)">
                                    <i class="bi bi-printer me-1"></i> Tes Cetak
                                </button>
                                <div data-thermal-status hidden></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const root = document.getElementById('printer-connection');
            if (!root || !window.ThermalPrinter) return;
            const el = (name) => root.querySelector('[data-el="' + name + '"]');
            const labels = {
                connected:    ['Terhubung', '#198754'],
                connecting:   ['Menghubungkan...', '#ffc107'],
                disconnected: ['Terputus', '#dc3545'],
                none:         ['Belum ada printer', '#adb5bd'],
                idle:         ['Memeriksa...', '#adb5bd'],
                unsupported:  ['Tidak didukung', '#dc3545'],
            };

            function render(status) {
                const [text, color] = labels[status.state] || labels.idle;
                el('state').textContent = text;
                el('message').textContent = status.message || '';
                el('dot').style.background = color;
                el('choose').querySelector('span').textContent = status.state === 'none' ? 'Hubungkan Printer' : 'Ganti Printer';
                el('reconnect').hidden = status.state === 'none' || status.state === 'connected';
                el('forget').hidden = status.state === 'none';
                const unsupported = status.state === 'unsupported';
                el('unsupported').classList.toggle('d-none', !unsupported);
                ['choose', 'reconnect', 'forget', 'baud'].forEach((n) => el(n).disabled = unsupported);
            }

            window.addEventListener('thermal:status', (e) => render(e.detail));
            render(ThermalPrinter.status());
            el('baud').value = String(ThermalPrinter.baudRate());

            el('choose').addEventListener('click', async () => {
                try { await ThermalPrinter.choosePrinter(); } catch (e) { if (e.name !== 'NotFoundError') console.error(e); }
            });
            el('reconnect').addEventListener('click', () => ThermalPrinter.connect());
            el('forget').addEventListener('click', () => ThermalPrinter.forgetPrinter());
            el('baud').addEventListener('change', (e) => ThermalPrinter.setBaudRate(e.target.value));
        })();
    </script>
</div>
