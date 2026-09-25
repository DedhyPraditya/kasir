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

    private const PER_PAGE = 15;

    public string $dateFrom = '';
    public string $dateTo   = '';
    public string $search   = '';

    public function mount(): void
    {
        $this->dateFrom = Carbon::today()->format('Y-m-d');
        $this->dateTo   = Carbon::today()->format('Y-m-d');
    }

    /** ID transaksi yang sedang dibuka di modal detail */
    public ?string $detailOrderId = null;

    /** @var array<int, string> ID transaksi yang dicentang developer */
    public array $selected = [];

    /** @var array<int, string> ID transaksi yang menunggu konfirmasi hapus */
    public array $pendingDelete = [];

    public function updatingSearch(): void  { $this->resetPage(); $this->selected = []; }
    public function updatingDateFrom(): void { $this->resetPage(); $this->selected = []; }
    public function updatingDateTo(): void   { $this->resetPage(); $this->selected = []; }

    public function showDetail(string $orderId): void
    {
        $this->detailOrderId = $orderId;
    }

    public function closeDetail(): void
    {
        $this->detailOrderId = null;
    }

    /**
     * Centang/lepas semua transaksi di halaman tabel yang sedang tampil.
     */
    public function toggleSelectPage(): void
    {
        $this->authorizeDeveloper();

        $pageIds = $this->currentPageIds();
        $allSelected = $pageIds !== [] && array_diff($pageIds, $this->selected) === [];

        $this->selected = $allSelected
            ? array_values(array_diff($this->selected, $pageIds))
            : array_values(array_unique(array_merge($this->selected, $pageIds)));
    }

    /**
     * Centang semua transaksi yang cocok dengan filter (semua halaman).
     */
    public function selectAllFiltered(): void
    {
        $this->authorizeDeveloper();

        $this->selected = $this->baseQuery()->pluck('id')->all();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    /** @return array<int, string> */
    private function currentPageIds(): array
    {
        return $this->baseQuery()->latest()->forPage($this->getPage(), self::PER_PAGE)->pluck('id')->all();
    }

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

        $detailOrder = $this->detailOrderId
            ? Order::with('items.toppings')->find($this->detailOrderId)
            : null;

        $totalPendapatan = (clone $base)->sum('total');
        $totalTransaksi  = (clone $base)->count();
        $totalCash       = (clone $base)->where('payment_method', 'cash')->sum('total');
        $totalQris       = (clone $base)->where('payment_method', 'qris')->sum('total');

        $orders = (clone $base)->with('items')->latest()->paginate(self::PER_PAGE);
        $pageIds = $orders->pluck('id')->all();

        return view('livewire.laporan', [
            'orders'          => $orders,
            'totalPendapatan' => $totalPendapatan,
            'totalTransaksi'  => $totalTransaksi,
            'totalCash'       => $totalCash,
            'totalQris'       => $totalQris,
            'detailOrder'     => $detailOrder,
            'pageAllSelected' => $pageIds !== [] && array_diff($pageIds, $this->selected) === [],
            'isAdmin'         => auth()->user()?->hasRole('admin') ?? false,
            'isDeveloper'     => auth()->user()?->hasRole('developer') ?? false,
        ])->layout('layouts.app');
    }
}
