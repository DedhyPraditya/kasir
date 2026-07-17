<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Courier New', monospace; font-size: 12px; color: #222; margin: 20px; }
        h2 { text-align: center; font-size: 16px; margin-bottom: 2px; }
        .subtitle { text-align: center; font-size: 11px; color: #555; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #198754; color: white; padding: 6px 8px; text-align: left; font-size: 11px; }
        td { padding: 5px 8px; font-size: 11px; border-bottom: 1px solid #e5e5e5; }
        .total-row td { font-weight: bold; background: #f0faf4; border-top: 2px solid #198754; }
        .text-right { text-align: right; }
        .badge-cash  { color: #198754; font-weight: bold; }
        .badge-qris  { color: #0d6efd; font-weight: bold; }
        .summary { margin-bottom: 16px; display: flex; gap: 32px; }
        .summary-item { background: #f8f9fa; border-left: 3px solid #198754; padding: 6px 12px; }
        .summary-item p { margin: 0; font-size: 10px; color: #666; }
        .summary-item h4 { margin: 2px 0 0; font-size: 14px; color: #198754; }
        .period { font-size: 11px; color: #555; margin-bottom: 12px; }
    </style>
</head>
<body>
    <h2>NYEMIL BEBS</h2>
    <div class="subtitle">Purnama Town House Blok H/1 &nbsp;|&nbsp; Telp: +62 823-9943-0312</div>
    <div class="subtitle"><strong>LAPORAN TRANSAKSI</strong></div>
    <div class="period">
        Periode: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} s/d {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}
        &nbsp;&nbsp;|&nbsp;&nbsp; Dicetak: {{ now()->format('d/m/Y H:i') }}
    </div>

    <table>
        <tr>
            <td style="width:33%; border:none;">
                <div class="summary-item">
                    <p>Total Pendapatan</p>
                    <h4>Rp {{ number_format($totalPendapatan, 0, ',', '.') }}</h4>
                </div>
            </td>
            <td style="width:33%; border:none;">
                <div class="summary-item" style="border-color:#198754">
                    <p>Tunai (Cash)</p>
                    <h4>Rp {{ number_format($totalCash, 0, ',', '.') }}</h4>
                </div>
            </td>
            <td style="width:33%; border:none;">
                <div class="summary-item" style="border-color:#0d6efd">
                    <p>QRIS</p>
                    <h4 style="color:#0d6efd">Rp {{ number_format($totalQris, 0, ',', '.') }}</h4>
                </div>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>No. Invoice</th>
                <th>Tanggal</th>
                <th>Pelanggan</th>
                <th>Metode</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $i => $order)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $order->invoice_number }}</td>
                <td>{{ $order->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $order->customer_name }}</td>
                <td class="{{ $order->payment_method === 'cash' ? 'badge-cash' : 'badge-qris' }}">
                    {{ $order->payment_method === 'cash' ? 'Tunai' : 'QRIS' }}
                </td>
                <td class="text-right">Rp {{ number_format($order->total, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center; padding:20px; color:#888;">Tidak ada transaksi</td></tr>
            @endforelse
            <tr class="total-row">
                <td colspan="5"><strong>TOTAL ({{ $orders->count() }} transaksi)</strong></td>
                <td class="text-right"><strong>Rp {{ number_format($totalPendapatan, 0, ',', '.') }}</strong></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
