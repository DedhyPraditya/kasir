<?php

namespace App\Livewire;

use App\Models\ReceiptSetting;
use Livewire\Component;

class PrinterSettings extends Component
{
    public string $storeName = '';
    public string $headerText = '';
    public string $footerText = '';

    public function mount(): void
    {
        $setting = ReceiptSetting::current();

        $this->storeName  = $setting->store_name;
        $this->headerText = (string) $setting->header_text;
        $this->footerText = (string) $setting->footer_text;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        $this->validate([
            'storeName'  => 'required|string|max:40',
            'headerText' => 'nullable|string|max:500',
            'footerText' => 'nullable|string|max:500',
        ], [
            'storeName.required' => 'Nama toko wajib diisi.',
            'storeName.max'      => 'Nama toko maksimal 40 karakter.',
        ]);

        (ReceiptSetting::latest('id')->first() ?? new ReceiptSetting())->fill([
            'store_name'  => trim($this->storeName),
            'header_text' => trim($this->headerText),
            'footer_text' => trim($this->footerText),
            'updated_by'  => auth()->id(),
        ])->save();

        session()->flash('message', 'Header & footer struk berhasil disimpan.');
    }

    public function resetToDefault(): void
    {
        $this->storeName  = ReceiptSetting::DEFAULT_STORE_NAME;
        $this->headerText = ReceiptSetting::DEFAULT_HEADER;
        $this->footerText = ReceiptSetting::DEFAULT_FOOTER;
    }

    /**
     * Struk contoh untuk tombol Tes Cetak, memakai isian form saat ini.
     */
    private function sampleReceipt(): array
    {
        $preview = new ReceiptSetting([
            'store_name'  => $this->storeName,
            'header_text' => $this->headerText,
            'footer_text' => $this->footerText,
        ]);

        return [
            'store'    => $preview->store_name,
            'header'   => $preview->headerLines(),
            'footer'   => $preview->footerLines(),
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
        $preview = new ReceiptSetting([
            'store_name'  => $this->storeName,
            'header_text' => $this->headerText,
            'footer_text' => $this->footerText,
        ]);

        return view('livewire.printer-settings', [
            'isAdmin'       => auth()->user()?->hasRole('admin') ?? false,
            'previewHeader' => $preview->headerLines(),
            'previewFooter' => $preview->footerLines(),
            'sampleReceipt' => $this->sampleReceipt(),
        ])->layout('layouts.app');
    }
}
