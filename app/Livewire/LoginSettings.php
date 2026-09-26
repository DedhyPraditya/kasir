<?php

namespace App\Livewire;

use App\Models\LoginSetting;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class LoginSettings extends Component
{
    use WithFileUploads;

    public string $headline = '';
    public string $description = '';

    /** Logo baru (belum disimpan) */
    public $logo;

    /** true = kembalikan ke logo bawaan saat disimpan */
    public bool $removeLogo = false;

    /**
     * Dijalankan di setiap request komponen, tidak hanya bergantung pada middleware route.
     */
    public function boot(): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
    }

    public function mount(): void
    {
        $setting = LoginSetting::current();

        $this->headline    = $setting->headline;
        $this->description = (string) $setting->description;
    }

    public function updatedLogo(): void
    {
        $this->removeLogo = false;
        $this->validateOnly('logo');
    }

    public function useDefaultLogo(): void
    {
        $this->logo = null;
        $this->removeLogo = true;
    }

    protected function rules(): array
    {
        return [
            'headline'    => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:300'],
            'logo'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    protected function messages(): array
    {
        return [
            'headline.required' => 'Judul wajib diisi.',
            'logo.image'        => 'File harus berupa gambar.',
            'logo.mimes'        => 'Format logo harus JPG, PNG, atau WEBP.',
            'logo.max'          => 'Ukuran logo maksimal 4 MB.',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $setting = LoginSetting::latest('id')->first() ?? new LoginSetting();
        $oldLogo = null;

        if ($this->logo) {
            $oldLogo = $setting->logo_path;
            $setting->logo_path = $this->logo->store('login', 'public');
        } elseif ($this->removeLogo) {
            $oldLogo = $setting->logo_path;
            $setting->logo_path = null;
        }

        $setting->fill([
            'headline'    => trim($this->headline),
            'description' => trim($this->description),
            'updated_by'  => auth()->id(),
        ])->save();

        if ($oldLogo) {
            Storage::disk('public')->delete($oldLogo);
        }

        $this->reset(['logo', 'removeLogo']);
        session()->flash('message', 'Tampilan login berhasil disimpan.');
    }

    public function resetText(): void
    {
        $this->headline    = LoginSetting::DEFAULT_HEADLINE;
        $this->description = LoginSetting::DEFAULT_DESCRIPTION;
    }

    /**
     * URL logo untuk pratinjau: unggahan baru > bawaan (jika dihapus) > tersimpan; null = logo bawaan.
     */
    private function customLogoUrl(LoginSetting $setting): ?string
    {
        if ($this->logo && ! $this->getErrorBag()->has('logo')) {
            try {
                return $this->logo->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        if ($this->removeLogo || ! $setting->logo_path) {
            return null;
        }

        return asset('storage/'.$setting->logo_path);
    }

    public function render()
    {
        $customLogo = $this->customLogoUrl(LoginSetting::current());

        return view('livewire.login-settings', [
            'logoPreview'  => $customLogo ?? asset('logo.png'),
            'logoIsCustom' => $customLogo !== null,
        ])->layout('layouts.app');
    }
}
