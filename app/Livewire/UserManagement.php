<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class UserManagement extends Component
{
    /** Pilihan peran di form => role Spatie yang diberikan. */
    public const ROLE_MAP = [
        'kasir'     => ['kasir'],
        'admin'     => ['admin'],
        'developer' => ['admin', 'developer'],
    ];

    public const ROLE_LABELS = [
        'kasir'     => 'Kasir',
        'admin'     => 'Admin',
        'developer' => 'Developer',
    ];

    public string $search = '';

    // Form tambah/edit
    public bool $showForm = false;
    public ?string $editingId = null;
    public string $username = '';
    public string $role = 'kasir';
    public string $password = '';
    public string $password_confirmation = '';

    // Konfirmasi hapus
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

    public function edit(string $userId): void
    {
        $user = User::findOrFail($userId);

        $this->resetForm();
        $this->editingId = $user->id;
        $this->username  = $user->username;
        $this->role      = self::roleOf($user);
        $this->showForm  = true;
    }

    public function closeForm(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $user = $this->editingId ? User::findOrFail($this->editingId) : null;

        $this->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user?->id)],
            'role'     => ['required', Rule::in(array_keys(self::ROLE_MAP))],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:6', 'confirmed'],
        ], [
            'username.required'  => 'Username wajib diisi.',
            'username.unique'    => 'Username sudah dipakai.',
            'username.alpha_dash'=> 'Username hanya boleh huruf, angka, - dan _ (tanpa spasi).',
            'password.required'  => 'Password wajib diisi.',
            'password.min'       => 'Password minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
        ]);

        if ($user && $user->is(auth()->user()) && $this->role !== 'developer') {
            $this->addError('role', 'Anda tidak bisa menurunkan peran akun sendiri.');
            return;
        }

        if ($user && self::roleOf($user) === 'developer' && $this->role !== 'developer' && $this->developerCount() <= 1) {
            $this->addError('role', 'Harus ada minimal satu akun developer.');
            return;
        }

        DB::transaction(function () use ($user) {
            $user ??= new User();
            $roleChanged = $user->exists && self::roleOf($user) !== $this->role;

            $user->username = trim($this->username);
            if ($this->password !== '') {
                $user->password = $this->password;
            }
            $user->save();

            $user->syncRoles(array_map(
                fn ($name) => Role::firstOrCreate(['name' => $name]),
                self::ROLE_MAP[$this->role]
            ));

            // Password/peran berubah: paksa login ulang di web & aplikasi mobile.
            if ($user->wasRecentlyCreated === false && ($this->password !== '' || $roleChanged) && ! $user->is(auth()->user())) {
                $this->logoutEverywhere($user);
            }
        });

        session()->flash('message', $this->editingId ? 'Akun berhasil diperbarui.' : 'Akun berhasil ditambahkan.');
        $this->resetForm();
    }

    public function confirmDelete(string $userId): void
    {
        $this->deletingId = $userId;
    }

    public function cancelDelete(): void
    {
        $this->deletingId = null;
    }

    public function delete(): void
    {
        $user = User::findOrFail($this->deletingId);
        $this->deletingId = null;

        if ($user->is(auth()->user())) {
            session()->flash('error', 'Anda tidak bisa menghapus akun sendiri.');
            return;
        }

        if (self::roleOf($user) === 'developer' && $this->developerCount() <= 1) {
            session()->flash('error', 'Harus ada minimal satu akun developer.');
            return;
        }

        DB::transaction(function () use ($user) {
            $this->logoutEverywhere($user);
            $user->syncRoles([]);
            $user->delete();
        });

        session()->flash('message', 'Akun '.$user->username.' berhasil dihapus.');
    }

    public static function roleOf(User $user): string
    {
        if ($user->hasRole('developer')) {
            return 'developer';
        }

        return $user->hasRole('admin') ? 'admin' : 'kasir';
    }

    private function developerCount(): int
    {
        return User::role('developer')->count();
    }

    private function logoutEverywhere(User $user): void
    {
        $user->forceFill(['api_token' => null, 'remember_token' => null])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editingId', 'username', 'role', 'password', 'password_confirmation']);
        $this->resetErrorBag();
    }

    public function render()
    {
        $users = User::with('roles')
            ->when($this->search, fn ($q) => $q->where('username', 'like', '%'.$this->search.'%'))
            ->orderBy('username')
            ->get();

        return view('livewire.user-management', [
            'users'      => $users,
            'deleting'   => $this->deletingId ? User::find($this->deletingId) : null,
            'roleLabels' => self::ROLE_LABELS,
        ])->layout('layouts.app');
    }
}
