<?php

namespace App\Livewire;

use App\Models\QrisSetting;
use App\Services\QrisService;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Log;
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

            $decodeError = null;

            try {
                $reader = new QrReader($this->qrisImage->getRealPath(), QrReader::SOURCE_TYPE_FILE);
                $payload = $reader->text();

                if (! $payload) {
                    $decodeError = $reader->getError();
                }
            } catch (Throwable $e) {
                $payload = false;
                $decodeError = $e;
            }

            if (! $payload) {
                Log::warning('Gagal membaca QR dari gambar upload QRIS.', [
                    'user_id' => auth()->id(),
                    'original_name' => $this->qrisImage->getClientOriginalName(),
                    'size_bytes' => $this->qrisImage->getSize(),
                    'error' => $decodeError?->getMessage(),
                ]);

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

        // Hapus QRIS lama dan file gambarnya dari storage & DB
        QrisSetting::all()->each(function (QrisSetting $old) {
            if ($old->image_path) {
                Storage::disk('public')->delete($old->image_path);
            }
            $old->delete();
        });

        QrisSetting::create([
            'payload' => $payload,
            'image_path' => $imagePath,
            'updated_by' => auth()->id(),
        ]);

        $this->reset(['qrisImage', 'manualPayload']);
        session()->flash('message', 'QRIS berhasil diperbarui. Kasir & aplikasi mobile otomatis memakai QRIS baru ini.');
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

        return view('livewire.qris-settings', [
            'current' => $current,
            'previewImage' => $previewImage,
        ])->layout('layouts.app');
    }
}
