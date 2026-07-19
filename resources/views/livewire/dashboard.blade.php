<div>
    <div class="container-fluid py-4">
        <h3 class="fw-bold mb-4">Dashboard Ringkasan Transaksi</h3>

        {{-- Stat Cards --}}
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm bg-success text-white">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Pendapatan Hari Ini</h6>
                                <h3 class="fw-bold mb-0">Rp {{ number_format($totalIncomeToday, 0, ',', '.') }}</h3>
                            </div>
                            <i class="bi bi-wallet2 fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 shadow-sm bg-primary text-white">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Pendapatan Bulan Ini</h6>
                                <h3 class="fw-bold mb-0">Rp {{ number_format($totalIncomeMonth, 0, ',', '.') }}</h3>
                            </div>
                            <i class="bi bi-graph-up fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 shadow-sm bg-info text-white">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Total Pesanan Hari Ini</h6>
                                <h3 class="fw-bold mb-0">{{ $totalOrdersToday }} Pesanan</h3>
                            </div>
                            <i class="bi bi-cart-check fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Charts Row --}}
        <div class="row g-4 mb-4">
            {{-- Grafik Pendapatan Harian --}}
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-bar-chart-line text-success me-2"></i>
                            Pendapatan 14 Hari Terakhir
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="dailyIncomeChart" height="80"></canvas>
                    </div>
                </div>
            </div>

            {{-- Best Seller Produk --}}
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-trophy text-warning me-2"></i>
                            Best Seller Produk
                        </h5>
                    </div>
                    <div class="card-body d-flex flex-column align-items-center justify-content-center">
                        @if($bestSellers->isEmpty())
                            <p class="text-muted text-center py-4">Belum ada data penjualan</p>
                        @else
                            <canvas id="bestSellerChart" style="max-height: 230px;"></canvas>
                            <ul class="list-unstyled mt-3 w-100 small">
                                @foreach($bestSellers as $index => $item)
                                <li class="d-flex justify-content-between align-items-center py-1 border-bottom">
                                    <span>
                                        <span class="badge rounded-pill me-1"
                                            style="background-color: {{ ['#4CAF50','#2196F3','#FF9800','#9C27B0','#F44336'][$index] ?? '#888' }}">
                                            {{ $index + 1 }}
                                        </span>
                                        {{ $item->product_name }}
                                    </span>
                                    <span class="fw-bold">{{ number_format($item->total_qty) }} pcs</span>
                                </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Best Seller Topping --}}
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-stars text-danger me-2"></i>
                            Best Seller Topping
                        </h5>
                    </div>
                    <div class="card-body d-flex flex-column align-items-center justify-content-center">
                        @if($bestToppings->isEmpty())
                            <p class="text-muted text-center py-4">Belum ada data topping</p>
                        @else
                            <canvas id="bestToppingChart" style="max-height: 230px;"></canvas>
                            <ul class="list-unstyled mt-3 w-100 small">
                                @foreach($bestToppings as $index => $item)
                                <li class="d-flex justify-content-between align-items-center py-1 border-bottom">
                                    <span>
                                        <span class="badge rounded-pill me-1"
                                            style="background-color: {{ ['#FF6384','#FF9F40','#FFCD56','#4BC0C0','#9966FF'][$index] ?? '#888' }}">
                                            {{ $index + 1 }}
                                        </span>
                                        {{ $item->topping_name }}
                                    </span>
                                    <span class="fw-bold">{{ number_format($item->total_used) }}x dipakai</span>
                                </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>


        {{-- Riwayat Transaksi --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                <h5 class="fw-bold mb-0">Riwayat Transaksi</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>No. Invoice</th>
                                <th>Tanggal</th>
                                <th>Pelanggan</th>
                                <th>Metode Bayar</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $order)
                            <tr>
                                <td><span class="fw-bold">{{ $order->invoice_number }}</span></td>
                                <td>{{ $order->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $order->customer_name }}</td>
                                <td>
                                    @if($order->payment_method === 'cash')
                                        <span class="badge bg-success">Tunai</span>
                                    @else
                                        <span class="badge bg-primary">QRIS</span>
                                    @endif
                                </td>
                                <td class="fw-bold">Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                                <td><span class="badge bg-success text-uppercase">{{ $order->status }}</span></td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Belum ada transaksi</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $orders->links() }}
                </div>
            </div>
        </div>
    </div>

    {{-- Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // ── Grafik Pendapatan Harian ────────────────────────────────
            const dailyLabels  = @json($dailyIncome->pluck('date'));
            const dailyData    = @json($dailyIncome->pluck('income'));

            const ctxDaily = document.getElementById('dailyIncomeChart');
            if (ctxDaily) {
                new Chart(ctxDaily, {
                    type: 'bar',
                    data: {
                        labels: dailyLabels,
                        datasets: [{
                            label: 'Pendapatan (Rp)',
                            data: dailyData,
                            backgroundColor: 'rgba(25, 135, 84, 0.2)',
                            borderColor: 'rgba(25, 135, 84, 1)',
                            borderWidth: 2,
                            borderRadius: 6,
                            fill: true,
                            tension: 0.4,
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => 'Rp ' + ctx.raw.toLocaleString('id-ID')
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: (val) => 'Rp ' + val.toLocaleString('id-ID')
                                }
                            }
                        }
                    }
                });
            }

            // ── Best Seller Doughnut Chart ──────────────────────────────
            @if($bestSellers->isNotEmpty())
            const bestLabels = @json($bestSellers->pluck('product_name'));
            const bestData   = @json($bestSellers->pluck('total_qty'));

            const ctxBest = document.getElementById('bestSellerChart');
            if (ctxBest) {
                new Chart(ctxBest, {
                    type: 'doughnut',
                    data: {
                        labels: bestLabels,
                        datasets: [{
                            data: bestData,
                            backgroundColor: [
                                '#4CAF50', '#2196F3', '#FF9800', '#9C27B0', '#F44336'
                            ],
                            borderWidth: 2,
                            borderColor: '#fff',
                            hoverOffset: 8,
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => ctx.label + ': ' + ctx.raw + ' pcs'
                                }
                            }
                        },
                        cutout: '60%',
                    }
                });
            }
            @endif

            // ── Best Seller Topping Doughnut Chart ─────────────────────
            @if($bestToppings->isNotEmpty())
            const toppingLabels = @json($bestToppings->pluck('topping_name'));
            const toppingData   = @json($bestToppings->pluck('total_used'));

            const ctxTopping = document.getElementById('bestToppingChart');
            if (ctxTopping) {
                new Chart(ctxTopping, {
                    type: 'doughnut',
                    data: {
                        labels: toppingLabels,
                        datasets: [{
                            data: toppingData,
                            backgroundColor: [
                                '#FF6384', '#FF9F40', '#FFCD56', '#4BC0C0', '#9966FF'
                            ],
                            borderWidth: 2,
                            borderColor: '#fff',
                            hoverOffset: 8,
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => ctx.label + ': ' + ctx.raw + 'x dipakai'
                                }
                            }
                        },
                        cutout: '60%',
                    }
                });
            }
            @endif
        });
    </script>
</div>