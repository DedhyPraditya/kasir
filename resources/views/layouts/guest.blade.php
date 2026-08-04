<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Nyemil Bebs POS') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="d-flex flex-column justify-content-center align-items-center min-vh-100">

            <!-- Logo & Title -->
            <div class="text-center mb-4">
                <img src="{{ asset('logo.png') }}"
                     alt="Nyemil Bebs Logo"
                     style="width: 150px; height: 150px; object-fit: contain; display: block; margin: 0 auto;">
                <h2 class="fw-bold mt-3 mb-1 text-success" style="letter-spacing: 2px;">NYEMIL BEBS</h2>
            </div>

            <!-- Form dalam Card -->
            <div class="card shadow-sm w-100" style="max-width: 400px; border-radius: 12px;">
                <div class="card-body p-4">
                    {{ $slot }}
                </div>
            </div>

        </div>
    </body>
</html>
