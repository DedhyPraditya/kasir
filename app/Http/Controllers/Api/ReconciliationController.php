<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Order;
use App\Models\ReceiptSetting;
use App\Models\Reconciliation;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReconciliationController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $date = $request->query('date', Carbon::today()->toDateString());
        $user = auth()->user();

        $cashSales = (float) Order::whereDate('created_at', $date)
            ->where('payment_method', 'cash')
            ->sum('total');

        $qrisSales = (float) Order::whereDate('created_at', $date)
            ->where('payment_method', 'qris')
            ->sum('total');

        $cashExpenses = (float) Expense::whereDate('expense_date', $date)
            ->sum('amount');

        $totalTransactions = Order::whereDate('created_at', $date)->count();

        return response()->json([
            'date' => $date,
            'kasir_name' => $user->username,
            'cash_sales' => $cashSales,
            'qris_sales' => $qrisSales,
            'cash_expenses' => $cashExpenses,
            'total_sales' => $cashSales + $qrisSales,
            'total_transactions' => $totalTransactions,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'shift_name' => ['nullable', 'string', 'max:50'],
            'starting_cash' => ['required', 'numeric', 'min:0'],
            'actual_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = auth()->user();
        $date = $data['date'] ?? Carbon::today()->toDateString();

        $cashSales = (float) Order::whereDate('created_at', $date)
            ->where('payment_method', 'cash')
            ->sum('total');

        $qrisSales = (float) Order::whereDate('created_at', $date)
            ->where('payment_method', 'qris')
            ->sum('total');

        $cashExpenses = (float) Expense::whereDate('expense_date', $date)
            ->sum('amount');

        $startingCash = (float) $data['starting_cash'];
        $actualCash = (float) $data['actual_cash'];
        $expectedCash = $startingCash + $cashSales - $cashExpenses;
        $difference = $actualCash - $expectedCash;

        $recon = Reconciliation::create([
            'id' => (string) Str::uuid(),
            'store_id' => $user->tenantId(),
            'user_id' => $user->id,
            'reconciliation_date' => $date,
            'shift_name' => $data['shift_name'] ?: 'Harian',
            'starting_cash' => $startingCash,
            'cash_sales' => $cashSales,
            'qris_sales' => $qrisSales,
            'cash_expenses' => $cashExpenses,
            'expected_cash' => $expectedCash,
            'actual_cash' => $actualCash,
            'difference' => $difference,
            'notes' => $data['notes'] ?? null,
        ]);

        $receipt = ReceiptSetting::current();
        $store = $user->tenantId() ? Store::find($user->tenantId()) : null;
        $storeName = !empty($receipt->store_name) ? $receipt->store_name : ($store?->name ?? 'Kasir POS');

        return response()->json([
            'success' => true,
            'message' => 'Rekonsiliasi kasir berhasil disimpan.',
            'data' => [
                'id' => $recon->id,
                'store_name' => $storeName,
                'kasir_name' => $user->username,
                'date' => $date,
                'shift_name' => $recon->shift_name,
                'starting_cash' => $startingCash,
                'cash_sales' => $cashSales,
                'qris_sales' => $qrisSales,
                'cash_expenses' => $cashExpenses,
                'expected_cash' => $expectedCash,
                'actual_cash' => $actualCash,
                'difference' => $difference,
                'difference_status' => $difference == 0 ? 'PAS' : ($difference > 0 ? 'LEBIH' : 'KURANG'),
                'notes' => $recon->notes,
                'created_at' => $recon->created_at?->format('d/m/Y H:i'),
            ],
        ], 201);
    }

    public function history(Request $request): JsonResponse
    {
        $list = Reconciliation::with('user:id,username')
            ->latest('reconciliation_date')
            ->latest('created_at')
            ->limit(30)
            ->get();

        return response()->json([
            'data' => $list->map(fn (Reconciliation $r) => [
                'id' => $r->id,
                'date' => $r->reconciliation_date?->format('Y-m-d'),
                'shift_name' => $r->shift_name,
                'kasir_name' => $r->user?->username ?? 'Kasir',
                'starting_cash' => (float) $r->starting_cash,
                'cash_sales' => (float) $r->cash_sales,
                'qris_sales' => (float) $r->qris_sales,
                'cash_expenses' => (float) $r->cash_expenses,
                'expected_cash' => (float) $r->expected_cash,
                'actual_cash' => (float) $r->actual_cash,
                'difference' => (float) $r->difference,
                'notes' => $r->notes,
                'created_at' => $r->created_at?->format('d/m/Y H:i'),
            ]),
        ]);
    }
}
