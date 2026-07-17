<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Order;
use Carbon\Carbon;

class Dashboard extends Component
{
    use WithPagination;
    
    protected $paginationTheme = 'bootstrap';

    public function render()
    {
        $today = Carbon::today();
        
        $totalIncomeToday = Order::whereDate('created_at', $today)->sum('total');
        $totalOrdersToday = Order::whereDate('created_at', $today)->count();
        $totalIncomeMonth = Order::whereMonth('created_at', $today->month)
                                 ->whereYear('created_at', $today->year)
                                 ->sum('total');

        $recentOrders = Order::with('items')->latest()->paginate(10);

        return view('livewire.dashboard', [
            'totalIncomeToday' => $totalIncomeToday,
            'totalOrdersToday' => $totalOrdersToday,
            'totalIncomeMonth' => $totalIncomeMonth,
            'orders' => $recentOrders,
        ])->layout('layouts.app');
    }
}
