<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemTopping;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Dashboard extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public function render()
    {
        $today = Carbon::today();

        $totalIncomeToday  = Order::whereDate('created_at', $today)->sum('total');
        $totalOrdersToday  = Order::whereDate('created_at', $today)->count();
        $totalIncomeMonth  = Order::whereMonth('created_at', $today->month)
                                 ->whereYear('created_at', $today->year)
                                 ->sum('total');
        $totalExpenseMonth = (float) Expense::whereMonth('expense_date', $today->month)
                                 ->whereYear('expense_date', $today->year)
                                 ->sum('amount');
        $netProfitMonth    = (float) ($totalIncomeMonth - $totalExpenseMonth);

        $recentOrders = Order::with('items')
            ->whereMonth('created_at', $today->month)
            ->whereYear('created_at', $today->year)
            ->latest()
            ->paginate(10);

        // Best Seller Produk: top 5 bulan ini berdasarkan total qty terjual
        $bestSellers = OrderItem::whereHas('order', function ($q) use ($today) {
                $q->whereMonth('created_at', $today->month)
                  ->whereYear('created_at', $today->year);
            })
            ->select('product_name', DB::raw('SUM(quantity) as total_qty'))
            ->groupBy('product_name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        // Best Seller Topping: top 5 bulan ini berdasarkan jumlah pemakaian
        $bestToppings = OrderItemTopping::whereHas('orderItem.order', function ($q) use ($today) {
                $q->whereMonth('created_at', $today->month)
                  ->whereYear('created_at', $today->year);
            })
            ->select('topping_name', DB::raw('COUNT(*) as total_used'))
            ->groupBy('topping_name')
            ->orderByDesc('total_used')
            ->limit(5)
            ->get();

        // Pendapatan per hari: 30 hari terakhir (bisa difilter 7, 14, 30 hari di frontend)
        $dailyIncome = collect(range(29, 0))->map(function ($daysAgo) {
            $date = Carbon::today()->subDays($daysAgo);
            $income = Order::whereDate('created_at', $date)->sum('total');
            return [
                'date'   => $date->format('d/m'),
                'income' => (float) $income,
            ];
        });

        $recent14 = $dailyIncome->slice(-14);
        $avgDailyIncome = (float) ($recent14->avg('income') ?: 0);
        $maxDailyIncome = (float) ($recent14->max('income') ?: 0);

        return view('livewire.dashboard', [
            'totalIncomeToday'  => $totalIncomeToday,
            'totalOrdersToday'  => $totalOrdersToday,
            'totalIncomeMonth'  => $totalIncomeMonth,
            'totalExpenseMonth' => $totalExpenseMonth,
            'netProfitMonth'    => $netProfitMonth,
            'orders'            => $recentOrders,
            'bestSellers'       => $bestSellers,
            'bestToppings'      => $bestToppings,
            'dailyIncome'       => $dailyIncome,
            'avgDailyIncome'    => $avgDailyIncome,
            'maxDailyIncome'    => $maxDailyIncome,
        ])->layout('layouts.app');
    }
}
