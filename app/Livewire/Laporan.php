<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Order;
use Carbon\Carbon;

class Laporan extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $dateFrom = '';
    public string $dateTo   = '';
    public string $search   = '';

    public function mount(): void
    {
        $this->dateFrom = Carbon::today()->format('Y-m-d');
        $this->dateTo   = Carbon::today()->format('Y-m-d');
    }

    public function updatingSearch(): void  { $this->resetPage(); }
    public function updatingDateFrom(): void { $this->resetPage(); }
    public function updatingDateTo(): void   { $this->resetPage(); }

    private function baseQuery()
    {
        $query = Order::query();

        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('invoice_number', 'like', '%' . $this->search . '%')
                  ->orWhere('customer_name',  'like', '%' . $this->search . '%');
            });
        }

        return $query;
    }

    public function render()
    {
        $base = $this->baseQuery();

        $totalPendapatan = (clone $base)->sum('total');
        $totalTransaksi  = (clone $base)->count();
        $totalCash       = (clone $base)->where('payment_method', 'cash')->sum('total');
        $totalQris       = (clone $base)->where('payment_method', 'qris')->sum('total');

        $orders = (clone $base)->with('items')->latest()->paginate(15);

        return view('livewire.laporan', [
            'orders'          => $orders,
            'totalPendapatan' => $totalPendapatan,
            'totalTransaksi'  => $totalTransaksi,
            'totalCash'       => $totalCash,
            'totalQris'       => $totalQris,
        ])->layout('layouts.app');
    }
}
