<?php

namespace App\Livewire;

use App\Models\QrisSetting;
use App\Services\QrisService;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;
use Zxing\QrReader;

class QrisSettings extends Component
{
    use WithFileUploads;

    public $qrisImage;
    public $manualPayload = '';
    public $useManualInput = false;

    public function toggleManualInput()
    {
        $this->useManualInput = ! $this->useManualInput;
        $this->qrisImage = null;
        $this->manualPayload = '';
        $this->resetErrorBag();
    }

    public function save()
    {
        $qris = app(QrisService::class);
        $imagePath = null;

        if ($this->useManualInput) {
            $this->validate([
                'manualPayload' => 'required|string|min:20',
            ], [
                'manualPayload.required' => 'String QRIS wajib diisi.',
            ]);

            $payload = trim($this->manualPayload);
        } else {
            $this->validate([
                'qrisImage' => 'required|image|max:4096',
            ], [
                'qrisImage.required' => 'Silakan pilih gambar QRIS.',
                'qrisImage.image' => 'File harus berupa gambar.',
                'qrisImage.max' => 'Ukuran gambar maksimal 4MB.',
            ]);

            try {
                $reader = new QrReader($this->qrisImage->getRealPath(), QrReader::SOURCE_TYPE_FILE);
                $payload = $reader->text();
            } catch (Throwable) {
                $payload = false;
            }

            if (! $payload) {
                $this->addError('qrisImage', 'Gambar tidak mengandung kode QR yang bisa dibaca. Pastikan foto jelas dan tidak buram.');

                return;
            }

            $imagePath = $this->qrisImage->store('qris', 'public');
        }

        try {
            $qris->assertValidStaticPayload($payload);
        } catch (InvalidArgumentException $e) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            $this->addError($this->useManualInput ? 'manualPayload' : 'qrisImage', $e->getMessage());

            return;
        }

        $new = QrisSetting::create([
            'payload' => $payload,
            'image_path' => $imagePath,
            'updated_by' => auth()->id(),
        ]);

        $this->cleanupOldImages($new->id);

        $this->reset(['qrisImage', 'manualPayload']);
        session()->flash('message', 'QRIS berhasil diperbarui. Kasir & aplikasi mobile otomatis memakai QRIS baru ini.');
    }

    public function activate(int $id)
    {
        $setting = QrisSetting::findOrFail($id);
        $qris = app(QrisService::class);

        try {
            $qris->assertValidStaticPayload($setting->payload);
        } catch (InvalidArgumentException $e) {
            session()->flash('error', 'QRIS ini tidak bisa diaktifkan kembali: '.$e->getMessage());

            return;
        }

        $new = QrisSetting::create([
            'payload' => $setting->payload,
            'image_path' => null,
            'updated_by' => auth()->id(),
        ]);

        $this->cleanupOldImages($new->id);

        session()->flash('message', 'QRIS lama berhasil diaktifkan kembali.');
    }

    /**
     * Hapus file gambar QRIS lama dari storage untuk menghemat ruang.
     * Payload tetap disimpan di DB (riwayat), hanya file foto upload aslinya yang dibuang.
     */
    private function cleanupOldImages(int $keepId): void
    {
        QrisSetting::where('id', '!=', $keepId)
            ->whereNotNull('image_path')
            ->get()
            ->each(function (QrisSetting $setting) {
                Storage::disk('public')->delete($setting->image_path);
                $setting->update(['image_path' => null]);
            });
    }

    private function toQrDataUri(string $payload, int $size = 260): string
    {
        $qrCode = new QrCode(data: $payload, size: $size, margin: 8);

        return (new PngWriter())->write($qrCode)->getDataUri();
    }

    public function render()
    {
        $qris = app(QrisService::class);
        $current = QrisSetting::query()->latest('id')->with('updatedBy')->first();
        $activePayload = $qris->getActivePayload();

        $previewImage = $activePayload ? $this->toQrDataUri($activePayload) : null;

        $history = QrisSetting::query()
            ->latest('id')
            ->with('updatedBy')
            ->skip(1)
            ->take(6)
            ->get()
            ->map(fn (QrisSetting $setting) => [
                'id' => $setting->id,
                'preview' => $this->toQrDataUri($setting->payload, 120),
                'created_at' => $setting->created_at,
                'updated_by' => $setting->updatedBy?->username ?? $setting->updatedBy?->name,
            ]);

        return view('livewire.qris-settings', [
            'current' => $current,
            'previewImage' => $previewImage,
            'history' => $history,
        ])->layout('layouts.app');
    }
}
