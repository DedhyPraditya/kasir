@php
    $login = \App\Models\LoginSetting::current();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#198754">

        <title>Masuk · {{ config('app.name', 'Nyemil Bebs POS') }}</title>
        <link rel="icon" type="image/png" href="{{ $login->logoUrl() }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

        <style>
            /*
             * Halaman login Nyemil Bebs: seluruh latar hijau merek (#198754),
             * panel merek di kiri (merek di atas, judul + deskripsi di tengah, hak cipta di bawah),
             * kartu form putih di kanan. Di layar kecil form tampil lebih dulu.
             */
            :root {
                --nb-green: #198754;
                --nb-green-deep: #146c43;
                --nb-green-ink: #0f5132;
                --nb-on-green: #ffffff;
                --nb-on-green-soft: #d1e7dd;
                --nb-ink: #1b2a22;
                --nb-muted: #5c6f64;
            }

            html, body { min-height: 100%; }

            body {
                margin: 0;
                font-family: 'Figtree', system-ui, -apple-system, 'Segoe UI', sans-serif;
                background: var(--nb-green);
                color: var(--nb-ink);
                -webkit-font-smoothing: antialiased;
            }

            ::selection { background: var(--nb-green); color: #fff; }

            .nb-login {
                min-height: 100vh;
                min-height: 100dvh;
                display: flex;
                align-items: center;
                padding-block: clamp(1.5rem, 4vw, 3.5rem);
            }

            .nb-login > .container { padding-inline: clamp(1rem, 4vw, 1.5rem); }

            /* ---------- Panel merek ---------- */
            .nb-brand { color: var(--nb-on-green); }

            .nb-brand-mark {
                display: inline-flex;
                align-items: center;
                gap: .75rem;
                text-decoration: none;
                color: var(--nb-on-green);
            }

            .nb-brand-mark img {
                width: 52px;
                height: 52px;
                object-fit: contain;
                background: #fff;
                border-radius: 14px;
                padding: 5px;
            }

            .nb-brand-mark span {
                font-weight: 800;
                font-size: 1.25rem;
                letter-spacing: .14em;
            }

            .nb-headline {
                font-weight: 700;
                font-size: clamp(2rem, 3.4vw, 3.1rem);
                line-height: 1.1;
                letter-spacing: -.028em;
                text-wrap: balance;
                max-width: 15ch;
                margin: 0 0 1.25rem;
            }

            .nb-lead {
                font-size: 1.1rem;
                line-height: 1.65;
                color: var(--nb-on-green-soft);
                max-width: 42ch;
                margin: 0;
            }

            .nb-foot {
                padding-top: 1.25rem;
                border-top: 1px solid rgba(255, 255, 255, .24);
                max-width: 440px;
                font-size: .875rem;
                color: var(--nb-on-green-soft);
            }

            @media (min-width: 992px) {
                .nb-login { padding-block: 2.5rem; }
                .nb-login .row { min-height: calc(100dvh - 5rem); }
                .nb-brand {
                    align-self: stretch;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                    gap: 3rem;
                }
            }

            /* ---------- Kartu form ---------- */
            .nb-card {
                background: #fff;
                border-radius: 16px;
                padding: clamp(1.75rem, 4vw, 2.5rem);
                box-shadow: 0 30px 60px -24px rgba(8, 40, 24, .6);
                max-width: 440px;
                margin-inline: auto;
            }

            .nb-card-logo {
                display: block;
                height: 84px;
                width: auto;
                max-width: 100%;
                object-fit: contain;
                margin: 0 auto 1.25rem;
            }

            .nb-card h1 {
                font-size: 1.6rem;
                font-weight: 700;
                letter-spacing: -.02em;
                margin: 0 0 .35rem;
                text-align: center;
            }

            .nb-card .nb-sub {
                color: var(--nb-muted);
                text-align: center;
                margin: 0 0 1.75rem;
            }

            .nb-card .form-label {
                font-weight: 600;
                font-size: .92rem;
                margin-bottom: .4rem;
            }

            .nb-card .form-control {
                padding: .7rem .9rem;
                border-radius: 10px;
                border-color: #cfd8d3;
                caret-color: var(--nb-green);
            }

            .nb-card .input-group .form-control { border-top-right-radius: 0; border-bottom-right-radius: 0; }

            .nb-card .form-control:focus,
            .nb-card .form-check-input:focus,
            .nb-card .btn:focus-visible {
                border-color: var(--nb-green);
                box-shadow: 0 0 0 .25rem rgba(25, 135, 84, .22);
            }

            .nb-eye {
                border-color: #cfd8d3;
                border-radius: 0 10px 10px 0;
                color: var(--nb-muted);
                padding-inline: .9rem;
            }

            .nb-eye:hover { background: #eef6f1; color: var(--nb-green-ink); border-color: #cfd8d3; }

            .nb-card .form-check-input:checked {
                background-color: var(--nb-green);
                border-color: var(--nb-green);
            }

            .nb-submit {
                padding: .8rem 1rem;
                border-radius: 10px;
                font-weight: 600;
                font-size: 1.05rem;
            }

            .nb-error { color: #b02a37; font-size: .875rem; list-style: none; padding: 0; margin: .4rem 0 0; }

            /* ---------- Gerak: satu kali masuk ---------- */
            @media (prefers-reduced-motion: no-preference) {
                .nb-rise { animation: nb-rise .7s cubic-bezier(.16, 1, .3, 1) both; }
                .nb-rise-2 { animation-delay: .08s; }
                .nb-rise-3 { animation-delay: .16s; }
                @keyframes nb-rise {
                    from { opacity: 0; transform: translateY(14px); }
                    to   { opacity: 1; transform: none; }
                }
            }

            @media (max-width: 991.98px) {
                .nb-brand { text-align: center; }
                .nb-brand-mark { display: none; }
                .nb-headline { max-width: 18ch; font-size: 1.6rem; margin-inline: auto; }
                .nb-lead { margin-inline: auto; font-size: 1rem; }
                .nb-foot { margin: 2rem auto 0; border-top: 0; padding-top: 0; }
            }
        </style>
    </head>
    <body>
        <main class="nb-login">
            <div class="container">
                <div class="row align-items-center gy-4 gx-lg-5">
                    {{-- Panel merek --}}
                    <section class="col-lg-6 order-2 order-lg-1 nb-brand" aria-label="Tentang Nyemil Bebs">
                        <div class="nb-brand-mark">
                            <img src="{{ $login->logoUrl() }}" alt="">
                            <span>NYEMIL BEBS</span>
                        </div>

                        <div class="nb-brand-body">
                            <h2 class="nb-headline nb-rise nb-rise-2">{{ $login->headline }}</h2>
                            @if($login->description)
                            <p class="nb-lead nb-rise nb-rise-2">{{ $login->description }}</p>
                            @endif
                        </div>

                        <footer class="nb-foot">&copy; {{ date('Y') }} Nyemil Bebs &middot; Sistem Kasir</footer>
                    </section>

                    {{-- Form login --}}
                    <section class="col-lg-5 offset-lg-1 order-1 order-lg-2">
                        <div class="nb-card nb-rise">
                            <img class="nb-card-logo" src="{{ $login->logoUrl() }}" alt="Nyemil Bebs">
                            <h1>Masuk</h1>
                            <p class="nb-sub">Masuk untuk mulai berjualan.</p>

                            @if (session('status'))
                                <div class="alert alert-success py-2 small">{{ session('status') }}</div>
                            @endif

                            <form method="POST" action="{{ route('login') }}">
                                @csrf

                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input id="username" class="form-control @error('username') is-invalid @enderror" type="text" name="username"
                                           value="{{ old('username') }}" required autofocus autocomplete="username">
                                    <x-input-error :messages="$errors->get('username')" class="nb-error" />
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <div class="input-group">
                                        <input id="password" class="form-control @error('password') is-invalid @enderror" type="password" name="password"
                                               required autocomplete="current-password">
                                        <button type="button" class="btn btn-outline-secondary nb-eye" title="Lihat password" aria-label="Lihat password"
                                                onclick="const i = document.getElementById('password'); const show = i.type === 'password'; i.type = show ? 'text' : 'password'; this.firstElementChild.className = show ? 'bi bi-eye-slash' : 'bi bi-eye'; this.title = this.ariaLabel = show ? 'Sembunyikan password' : 'Lihat password';">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <x-input-error :messages="$errors->get('password')" class="nb-error" />
                                </div>

                                <div class="form-check mb-4">
                                    <input id="remember_me" type="checkbox" class="form-check-input" name="remember">
                                    <label for="remember_me" class="form-check-label">Ingat saya</label>
                                </div>

                                <button class="btn btn-success w-100 nb-submit" type="submit">
                                    Masuk
                                </button>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </body>
</html>
