<?php

namespace App\Livewire;

use App\Models\ReceiptSetting;
use Livewire\Component;

class PrinterSettings extends Component
{
    public string $storeName = '';
    public string $headerText = '';
    public string $footerText = '';
    public int $feedLines = ReceiptSetting::DEFAULT_FEED_LINES;
    public bool $autoCut = false;

    public function mount(): void
    {
        $setting = ReceiptSetting::current();

        $this->storeName  = $setting->store_name;
        $this->headerText = (string) $setting->header_text;
        $this->footerText = (string) $setting->footer_text;
        $this->feedLines  = (int) $setting->feed_lines;
        $this->autoCut    = (bool) $setting->auto_cut;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        $this->validate([
            'storeName'  => 'required|string|max:40',
            'headerText' => 'nullable|string|max:500',
            'footerText' => 'nullable|string|max:500',
            'feedLines'  => 'required|integer|min:0|max:'.ReceiptSetting::MAX_FEED_LINES,
            'autoCut'    => 'boolean',
        ], [
            'storeName.required' => 'Nama toko wajib diisi.',
            'storeName.max'      => 'Nama toko maksimal 40 karakter.',
        ]);

        (ReceiptSetting::latest('id')->first() ?? new ReceiptSetting())->fill([
            'store_name'  => trim($this->storeName),
            'header_text' => trim($this->headerText),
            'footer_text' => trim($this->footerText),
            'feed_lines'  => $this->feedLines,
            'auto_cut'    => $this->autoCut,
            'updated_by'  => auth()->id(),
        ])->save();

        session()->flash('message', 'Pengaturan struk berhasil disimpan. Berlaku untuk web & aplikasi mobile.');
    }

    public function resetToDefault(): void
    {
        $this->storeName  = ReceiptSetting::DEFAULT_STORE_NAME;
        $this->headerText = ReceiptSetting::DEFAULT_HEADER;
        $this->footerText = ReceiptSetting::DEFAULT_FOOTER;
        $this->feedLines  = ReceiptSetting::DEFAULT_FEED_LINES;
        $this->autoCut    = false;
    }

    /**
     * Pengaturan dari isian form saat ini (belum tentu tersimpan).
     */
    private function draft(): ReceiptSetting
    {
        return new ReceiptSetting([
            'store_name'  => $this->storeName,
            'header_text' => $this->headerText,
            'footer_text' => $this->footerText,
            'feed_lines'  => max(0, min(ReceiptSetting::MAX_FEED_LINES, $this->feedLines)),
            'auto_cut'    => $this->autoCut,
        ]);
    }

    /**
     * Struk contoh untuk tombol Tes Cetak, memakai isian form saat ini.
     */
    private function sampleReceipt(): array
    {
        return $this->draft()->printOptions() + [
            'invoice'  => 'TES-PRINTER',
            'date'     => now()->format('d/m/Y H:i'),
            'kasir'    => auth()->user()?->username,
            'customer' => 'Tes Cetak',
            'items'    => [
                ['name' => 'Contoh Produk - Jumbo', 'qty' => 1, 'price' => 15000, 'subtotal' => 17000, 'toppings' => [
                    ['name' => 'Keju Parut', 'price' => 2000],
                ]],
            ],
            'total'    => 17000,
            'method'   => 'cash',
            'paid'     => 20000,
            'change'   => 3000,
            'reprint'  => false,
        ];
    }

    public function render()
    {
        $draft = $this->draft();

        return view('livewire.printer-settings', [
            'isAdmin'       => auth()->user()?->hasRole('admin') ?? false,
            'previewHeader' => $draft->headerLines(),
            'previewFooter' => $draft->footerLines(),
            'sampleReceipt' => $this->sampleReceipt(),
            'maxFeedLines'  => ReceiptSetting::MAX_FEED_LINES,
        ])->layout('layouts.app');
    }
}
