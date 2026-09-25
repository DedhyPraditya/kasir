{{--
    Tombol aksi modal struk: cetak langsung ke printer thermal (Web Serial) atau via dialog browser.
    $receipt     : hasil Order::receiptData()
    $closeAction : aksi Livewire untuk tombol Tutup
    $label       : teks tombol cetak thermal
--}}
<div class="modal-footer border-top-0 pt-0 px-4 pb-4 d-print-none d-block" data-thermal>
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <button type="button" class="btn btn-light" wire:click="{{ $closeAction }}">Tutup</button>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()" title="Cetak via dialog print browser">
                <i class="bi bi-window me-1"></i> Browser
            </button>
            <button type="button" class="btn btn-primary px-3" data-receipt="{{ json_encode($receipt) }}" onclick="ThermalPrinter.printFromButton(this)">
                <i class="bi bi-printer me-1"></i> {{ $label ?? 'Cetak Struk' }}
            </button>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-start gap-2">
        <div data-thermal-status hidden></div>
        <a href="{{ route('printer.settings') }}" class="btn btn-link btn-sm text-decoration-none px-0 mt-1 ms-auto">
            <i class="bi bi-gear me-1"></i> Pengaturan Printer
        </a>
    </div>
</div>
