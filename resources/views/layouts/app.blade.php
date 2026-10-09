<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Nyemil Bebs POS') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Bootstrap CSS via Vite -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <script src="{{ asset('js/thermal-printer.js') }}?v={{ filemtime(public_path('js/thermal-printer.js')) }}"></script>
        
        <style>
            .sidebar {
                width: 260px;
                height: 100vh;
                height: 100dvh; /* tinggi layar yang benar-benar terlihat di browser HP */
                position: sticky;
                top: 0;
                z-index: 1000;
                overflow-y: auto;
                padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0px)) !important;
                transition: margin-left 0.25s ease-in-out, transform 0.3s ease-in-out;
                flex-shrink: 0;
            }
            @media (min-width: 769px) {
                .sidebar.collapsed {
                    margin-left: -260px;
                }
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
                    z-index: 1040; /* di atas header HP (sticky-top 1020) */
                    transform: translateX(-100%);
                    transition: transform 0.3s ease-in-out;
                }
                .sidebar.show {
                    transform: translateX(0);
                }
                .sidebar-backdrop {
                    position: fixed;
                    inset: 0;
                    background: rgba(0, 0, 0, .35);
                    z-index: 1035;
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
                <a href="{{ route('dashboard') }}" class="d-flex align-items-center justify-content-center mb-3 mt-2 text-decoration-none px-2">
                    <img src="{{ asset('logo-apps.png') }}" alt="Logo Aplikasi" class="img-fluid" style="max-height: 72px;">
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
                        <a href="{{ route('produk') }}" class="nav-link py-3 px-3 {{ request()->routeIs('produk') ? 'active shadow-sm' : '' }}">
                            <i class="bi bi-box-seam-fill me-2 fs-5"></i> Produk
                        </a>
                    </li>
                    @endrole
                    <li>
                        <a href="{{ route('laporan') }}" class="nav-link py-3 px-3 {{ request()->routeIs('laporan') ? 'active shadow-sm' : '' }}">
                            <i class="bi bi-receipt me-2 fs-5"></i> Laporan
                        </a>
                    </li>
                    @role('admin')
                    <li>
                        <a href="{{ route('qris.settings') }}" class="nav-link py-3 px-3 {{ request()->routeIs('qris.settings') ? 'active shadow-sm' : '' }}">
                            <i class="bi bi-qr-code me-2 fs-5"></i> Pengaturan QRIS
                        </a>
                    </li>
                    @endrole
                    <li>
                        <a href="{{ route('printer.settings') }}" class="nav-link py-3 px-3 d-flex align-items-center {{ request()->routeIs('printer.settings') ? 'active shadow-sm' : '' }}">
                            <i class="bi bi-printer-fill me-2 fs-5"></i> Printer
                            <span id="printer-status-badge" class="rounded-circle ms-auto border border-white" style="width: 10px; height: 10px; background: #adb5bd;"></span>
                        </a>
                    </li>
                    @role('developer')
                    <li>
                        <a href="{{ route('stores.index') }}" class="nav-link py-3 px-3 {{ request()->routeIs('stores.index') ? 'active shadow-sm' : '' }}">
                            <i class="bi bi-shop-window me-2 fs-5"></i> Kelola Toko
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('users.index') }}" class="nav-link py-3 px-3 {{ request()->routeIs('users.index') ? 'active shadow-sm' : '' }}">
                            <i class="bi bi-people-fill me-2 fs-5"></i> Kelola Pengguna
                        </a>
                    </li>
                    @endrole
                </ul>
                @role('developer')
                @php
                    $switchStores = \App\Models\Store::orderBy('name')->get(['id', 'name']);
                    $activeStoreId = auth()->user()->tenantId();
                @endphp
                <form method="POST" action="{{ route('store.switch') }}" class="px-2 mb-2">
                    @csrf
                    <label class="form-label small text-muted mb-1"><i class="bi bi-shop me-1"></i> Toko aktif</label>
                    <select name="store" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach($switchStores as $store)
                        <option value="{{ $store->id }}" @selected($store->id === $activeStoreId)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </form>
                @endrole
                @php $currentStore = auth()->user()->tenantId() ? \App\Models\Store::find(auth()->user()->tenantId()) : null; @endphp
                <hr>
                <div class="dropup">
                    <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle px-2" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle fs-3 me-2 text-success"></i>
                        <span class="d-flex flex-column lh-sm overflow-hidden">
                            <strong class="text-truncate">{{ auth()->user()->username ?? 'Admin' }}</strong>
                            @if($currentStore)
                            <small class="text-muted text-truncate" title="{{ $currentStore->name }}">{{ $currentStore->name }}</small>
                            @endif
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-light text-small shadow" aria-labelledby="dropdownUser1">
                        @role('admin')
                        <li><a class="dropdown-item" href="{{ route('qris.settings') }}"><i class="bi bi-gear me-2"></i> Pengaturan</a></li>
                        @endrole
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
            <div class="flex-grow-1 d-flex flex-column main-content w-100" style="height: 100vh; height: 100dvh; overflow-y: auto;">
                <!-- Desktop Header with Sidebar Toggle -->
                <div class="d-none d-md-flex bg-white shadow-sm px-3 py-2 justify-content-between align-items-center sticky-top border-bottom" style="z-index: 1010;">
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-light border text-secondary rounded-2 px-2 py-1 shadow-sm" 
                                type="button" 
                                id="desktopSidebarToggle" 
                                onclick="toggleDesktopSidebar()" 
                                title="Sembunyikan/Tampilkan Menu Samping">
                            <i class="bi bi-layout-sidebar-inset fs-5"></i>
                        </button>
                        <span class="fw-semibold text-muted small ms-1">
                            @if(request()->routeIs('pos'))
                                <span class="badge bg-success-subtle text-success border border-success-subtle me-1">POS</span> Kasir
                            @elseif(request()->routeIs('dashboard'))
                                Dashboard
                            @elseif(request()->routeIs('produk'))
                                Kelola Produk
                            @elseif(request()->routeIs('laporan'))
                                Laporan Penjualan
                            @else
                                {{ config('app.name', 'Kasir') }}
                            @endif
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        @php $currentStore = auth()->user()->tenantId() ? \App\Models\Store::find(auth()->user()->tenantId()) : null; @endphp
                        @if($currentStore)
                            <div class="d-flex align-items-center text-secondary small">
                                <i class="bi bi-shop me-1 text-success"></i>
                                <span class="fw-semibold">{{ $currentStore->name }}</span>
                            </div>
                            <div class="vr my-1 text-muted"></div>
                        @endif
                        <div class="d-flex align-items-center text-secondary small">
                            <i class="bi bi-person-circle me-1 text-success"></i>
                            <span class="fw-semibold">{{ auth()->user()->username ?? 'Kasir' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Mobile Header (Visible only on small screens) -->
                <div class="d-md-none bg-white shadow-sm p-3 d-flex justify-content-between align-items-center mobile-header sticky-top">
                    <img src="{{ asset('logo-apps.png') }}" alt="Logo Aplikasi" style="height: 40px;">
                    <div class="d-flex align-items-center gap-2">
                        <form method="POST" action="{{ route('logout') }}" class="m-0">
                            @csrf
                            <button class="btn btn-outline-danger" type="submit" title="Keluar" aria-label="Keluar">
                                <i class="bi bi-box-arrow-right fs-5"></i>
                            </button>
                        </form>
                        <button class="btn btn-outline-success" type="button" aria-label="Buka menu" onclick="toggleSidebar()">
                            <i class="bi bi-list fs-4"></i>
                        </button>
                    </div>
                </div>
                <div class="sidebar-backdrop d-md-none" hidden onclick="toggleSidebar(false)"></div>

                <!-- Page Content Slot -->
                <main class="flex-grow-1 p-3 p-md-4">
                    {{ $slot }}
                </main>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Buka/tutup sidebar di desktop (layar lebar) & simpan preferensi ke localStorage
            function toggleDesktopSidebar() {
                const sidebar = document.querySelector('.sidebar');
                const isCollapsed = sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebar-collapsed', isCollapsed ? 'true' : 'false');
                const btnIcon = document.querySelector('#desktopSidebarToggle i');
                if (btnIcon) {
                    btnIcon.className = isCollapsed ? 'bi bi-layout-sidebar fs-5' : 'bi bi-layout-sidebar-inset fs-5';
                }
            }

            // Pulihkan status sidebar desktop yang tersimpan
            document.addEventListener('DOMContentLoaded', function () {
                if (window.innerWidth > 768 && localStorage.getItem('sidebar-collapsed') === 'true') {
                    const sidebar = document.querySelector('.sidebar');
                    if (sidebar) {
                        sidebar.classList.add('collapsed');
                        const btnIcon = document.querySelector('#desktopSidebarToggle i');
                        if (btnIcon) {
                            btnIcon.className = 'bi bi-layout-sidebar fs-5';
                        }
                    }
                }
            });

            // Buka/tutup sidebar di HP; ketuk area gelap untuk menutup.
            function toggleSidebar(force) {
                const sidebar = document.querySelector('.sidebar');
                const open = sidebar.classList.toggle('show', force);
                document.querySelector('.sidebar-backdrop').hidden = !open;
            }
        </script>
        <script>
            // Indikator status printer thermal di sidebar.
            (function () {
                const badge = document.getElementById('printer-status-badge');
                if (!badge || !window.ThermalPrinter) return;
                const colors = { connected: '#198754', connecting: '#ffc107', disconnected: '#dc3545' };
                const labels = { connected: 'Terhubung', connecting: 'Menghubungkan', disconnected: 'Terputus', none: 'Belum diatur', unsupported: 'Tidak didukung' };
                const render = (s) => {
                    badge.style.background = colors[s.state] || '#adb5bd';
                    badge.title = 'Printer: ' + (labels[s.state] || '-');
                };
                window.addEventListener('thermal:status', (e) => render(e.detail));
                render(ThermalPrinter.status());
            })();
        </script>
        @livewireScripts
    </body>
</html>
