<?php

namespace App\Livewire;

use App\Models\Store;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class StoreManagement extends Component
{
    public bool $showForm = false;
    public ?string $editingId = null;
    public string $name = '';
    public string $address = '';
    public string $phone = '';

    public ?string $deletingId = null;

    /**
     * Dijalankan di setiap request komponen (termasuk aksi Livewire),
     * jadi tidak hanya bergantung pada middleware route.
     */
    public function boot(): void
    {
        abort_unless(auth()->user()?->hasRole('developer'), 403);
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(string $storeId): void
    {
        $store = Store::findOrFail($storeId);

        $this->resetForm();
        $this->editingId = $store->id;
        $this->name      = $store->name;
        $this->address   = (string) $store->address;
        $this->phone     = (string) $store->phone;
        $this->showForm  = true;
    }

    public function closeForm(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->validate([
            'name'    => ['required', 'string', 'max:100', Rule::unique('stores', 'name')->ignore($this->editingId)],
            'address' => ['nullable', 'string', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:50'],
        ], [
            'name.required' => 'Nama toko wajib diisi.',
            'name.unique'   => 'Nama toko sudah dipakai.',
        ]);

        $store = $this->editingId ? Store::findOrFail($this->editingId) : new Store();

        $store->name    = trim($this->name);
        $store->address = trim($this->address) ?: null;
        $store->phone   = trim($this->phone) ?: null;

        // Slug dibuat sekali saat toko dibuat dan tidak berubah saat nama diganti.
        if (! $store->exists) {
            $store->slug = $this->uniqueSlug($store->name);
        }

        $store->save();

        session()->flash('message', $this->editingId ? 'Toko berhasil diperbarui.' : 'Toko berhasil ditambahkan.');
        $this->resetForm();
    }

    public function confirmDelete(string $storeId): void
    {
        $this->deletingId = $storeId;
    }

    public function cancelDelete(): void
    {
        $this->deletingId = null;
    }

    public function delete(): void
    {
        $store = Store::findOrFail($this->deletingId);
        $this->deletingId = null;

        if ($store->isInUse()) {
            session()->flash('error', 'Toko masih punya akun atau data (produk/transaksi). Pindahkan atau hapus dulu sebelum menghapus toko.');
            return;
        }

        $store->delete();

        session()->flash('message', 'Toko '.$store->name.' berhasil dihapus.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'toko';
        $slug = $base;

        for ($i = 2; Store::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editingId', 'name', 'address', 'phone']);
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.store-management', [
            'stores'   => Store::withCount('users')->orderBy('name')->get(),
            'deleting' => $this->deletingId ? Store::find($this->deletingId) : null,
        ])->layout('layouts.app');
    }
}
