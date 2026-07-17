<?php

namespace App\Http\Controllers;

use App\Models\Order;
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

        $pdf = Pdf::loadView('laporan-pdf', compact(
            'orders', 'dateFrom', 'dateTo',
            'totalPendapatan', 'totalCash', 'totalQris'
        ))->setPaper('a4', 'landscape');

        $filename = 'laporan-' . $dateFrom . '-sd-' . $dateTo . '.pdf';

        return $pdf->download($filename);
    }
}
