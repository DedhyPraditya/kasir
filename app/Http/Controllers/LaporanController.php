<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ReceiptSetting;
use App\Models\Store;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    public function export(Request $request)
    {
        $dateFrom = $request->get('dateFrom', Carbon::today()->format('Y-m-d'));
        $dateTo   = $request->get('dateTo',   Carbon::today()->format('Y-m-d'));
        $search   = $request->get('search', '');

        $query = Order::query()
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', '%'.$search.'%')
                  ->orWhere('customer_name',  'like', '%'.$search.'%');
            });
        }

        $orders          = $query->latest()->get();
        $totalPendapatan = $orders->sum('total');
        $totalCash       = $orders->where('payment_method', 'cash')->sum('total');
        $totalQris       = $orders->where('payment_method', 'qris')->sum('total');

        // Mengambil data toko dan pengaturan struk akun yang sedang aktif/login
        $user = auth()->user();
        $storeId = $user?->tenantId();
        $store = $storeId ? Store::find($storeId) : null;
        $receipt = ReceiptSetting::current();

        $storeName = !empty($receipt->store_name) ? $receipt->store_name : ($store?->name ?? 'Kasir POS');

        $headerLines = $receipt->headerLines();
        if (empty($headerLines) && $store) {
            $headerLines = array_values(array_filter([
                $store->address,
                $store->phone ? 'Telp: ' . $store->phone : null,
            ]));
        }
        $storeAddress = implode(' &nbsp;|&nbsp; ', $headerLines);

        $pdf = Pdf::loadView('laporan-pdf', compact(
            'orders', 'dateFrom', 'dateTo',
            'totalPendapatan', 'totalCash', 'totalQris',
            'storeName', 'storeAddress'
        ))->setPaper('a4', 'landscape');

        $filename = 'laporan-' . $dateFrom . '-sd-' . $dateTo . '.pdf';

        return $pdf->download($filename);
    }
}
