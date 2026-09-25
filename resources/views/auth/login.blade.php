<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Username -->
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input id="username" class="form-control" type="text" name="username" :value="old('username')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('username')" class="text-danger mt-2" />
        </div>

        <!-- Password -->
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
                <input id="password" class="form-control" type="password" name="password" required autocomplete="current-password" />
                <button type="button" class="btn btn-outline-secondary" title="Lihat password" aria-label="Lihat password"
                        onclick="const i = document.getElementById('password'); const show = i.type === 'password'; i.type = show ? 'text' : 'password'; this.firstElementChild.className = show ? 'bi bi-eye-slash' : 'bi bi-eye'; this.title = this.ariaLabel = show ? 'Sembunyikan password' : 'Lihat password';">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="text-danger mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="mb-3 form-check">
            <input id="remember_me" type="checkbox" class="form-check-input" name="remember">
            <label for="remember_me" class="form-check-label">Remember me</label>
        </div>

        <div class="d-grid gap-2">
            <button class="btn btn-success" type="submit">
                Log in
            </button>
        </div>
    </form>
</x-guest-layout>
