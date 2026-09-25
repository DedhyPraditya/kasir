<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemTopping;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

    /** @var array<int, string> ID transaksi yang dicentang developer */
    public array $selected = [];

    /** @var array<int, string> ID transaksi yang menunggu konfirmasi hapus */
    public array $pendingDelete = [];

    public function updatingSearch(): void  { $this->resetPage(); $this->selected = []; }
    public function updatingDateFrom(): void { $this->resetPage(); $this->selected = []; }
    public function updatingDateTo(): void   { $this->resetPage(); $this->selected = []; }

    public function confirmDelete(?string $orderId = null): void
    {
        $this->authorizeDeveloper();

        $this->pendingDelete = $orderId ? [$orderId] : array_values($this->selected);
    }

    public function cancelDelete(): void
    {
        $this->pendingDelete = [];
    }

    public function deleteOrders(): void
    {
        $this->authorizeDeveloper();

        $ids = $this->pendingDelete;
        if (empty($ids)) {
            return;
        }

        $deleted = DB::transaction(function () use ($ids) {
            $itemIds = OrderItem::whereIn('order_id', $ids)->pluck('id');
            OrderItemTopping::whereIn('order_item_id', $itemIds)->delete();
            OrderItem::whereIn('id', $itemIds)->delete();

            return Order::whereIn('id', $ids)->delete();
        });

        $this->selected      = array_values(array_diff($this->selected, $ids));
        $this->pendingDelete = [];

        session()->flash('message', $deleted . ' transaksi berhasil dihapus.');
    }

    private function authorizeDeveloper(): void
    {
        abort_unless(auth()->user()?->hasRole('developer'), 403);
    }

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
            'isDeveloper'     => auth()->user()?->hasRole('developer') ?? false,
        ])->layout('layouts.app');
    }
}
