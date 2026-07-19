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

        <!-- Bootstrap CSS via Vite -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        
        <style>
            .sidebar {
                width: 260px;
                height: 100vh;
                position: sticky;
                top: 0;
                z-index: 1000;
            }
            .nav-link.active {
                background-color: #198754 !important; /* Bootstrap Success */
                color: white !important;
            }
            .nav-link {
                color: #495057;
                border-radius: 8px;
                margin-bottom: 5px;
                font-weight: 500;
                transition: all 0.2s ease;
            }
            .nav-link:hover {
                background-color: #f8f9fa;
                color: #198754;
            }
            .nav-link.active:hover {
                background-color: #157347 !important;
                color: white !important;
            }
            @media (max-width: 768px) {
                .sidebar {
                    position: fixed;
                    transform: translateX(-100%);
                    transition: transform 0.3s ease-in-out;
                }
                .sidebar.show {
                    transform: translateX(0);
                }
            }
            /* Print CSS adjustments */
            @media print {
                .sidebar, .mobile-header { display: none !important; }
                .main-content { width: 100% !important; margin: 0 !important; padding: 0 !important; }
            }
        </style>
    </head>
    <body class="font-sans antialiased bg-light">
        <div class="d-flex min-vh-100">
            <!-- Sidebar -->
            <div class="sidebar bg-white shadow-sm d-flex flex-column p-3">
                <a href="{{ route('dashboard') }}" class="d-flex align-items-center mb-4 mt-2 text-success text-decoration-none px-2">
                    <i class="bi bi-shop fs-2 me-2"></i>
                    <span class="fs-5 fw-bolder text-nowrap" style="letter-spacing: 0.5px;">NYEMIL BEBS</span>
                </a>
                <hr>
                <ul class="nav nav-pills flex-column mb-auto">
                    @role('admin')
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="nav-link py-3 px-3 {{ request()->routeIs('dashboard') ? 'active shadow-sm' : '' }}">
                            <i class="bi bi-grid-1x2-fill me-2 fs-5"></i> Dashboard
                        </a>
                    </li>
                    @endrole
                    <li>
                        <a href="{{ route('pos') }}" class="nav-link py-3 px-3 {{ request()->routeIs('pos') ? 'active shadow-sm' : '' }}">
                            <i class="bi bi-calculator-fill me-2 fs-5"></i> Kasir (POS)
                        </a>
                    </li>
                    @role('admin')
                    <li>
                        <a href="{{ route('produk') }}" class="nav-link py-3 px-3 {{ request()->routeIs('produk') ? 'active shadow-sm' : '' }}">
                            <i class="bi bi-box-seam-fill me-2 fs-5"></i> Produk
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('laporan') }}" class="nav-link py-3 px-3 {{ request()->routeIs('laporan') ? 'active shadow-sm' : '' }}">
                            <i class="bi bi-receipt me-2 fs-5"></i> Laporan
                        </a>
                    </li>
                    @endrole
                </ul>
                <hr>
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle px-2" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle fs-4 me-2 text-success"></i>
                        <strong>{{ auth()->user()->username ?? 'Admin' }}</strong>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-light text-small shadow" aria-labelledby="dropdownUser1">
                        <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i> Pengaturan</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="dropdown-item text-danger fw-bold" type="submit">
                                    <i class="bi bi-box-arrow-right me-2"></i> Keluar
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="flex-grow-1 d-flex flex-column main-content w-100" style="height: 100vh; overflow-y: auto;">
                <!-- Mobile Header (Visible only on small screens) -->
                <div class="d-md-none bg-white shadow-sm p-3 d-flex justify-content-between align-items-center mobile-header sticky-top">
                    <h5 class="mb-0 text-success fw-bold">
                        <i class="bi bi-shop me-1"></i> NYEMIL BEBS
                    </h5>
                    <button class="btn btn-outline-success" type="button" onclick="document.querySelector('.sidebar').classList.toggle('show')">
                        <i class="bi bi-list fs-4"></i>
                    </button>
                </div>

                <!-- Page Content Slot -->
                <main class="flex-grow-1 p-3 p-md-4">
                    {{ $slot }}
                </main>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        @livewireScripts
    </body>
</html>
