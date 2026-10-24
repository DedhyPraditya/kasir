<?php

namespace App\Livewire;

use App\Models\Expense;
use App\Models\Order;
use App\Models\ReceiptSetting;
use App\Models\Reconciliation;
use App\Models\Store;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class Rekonsiliasi extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $reconciliation_date = '';
    public string $shift_name = 'Harian';
    public string $starting_cash = '0';
    public string $actual_cash = '';
    public string $notes = '';

    public ?string $detailReconId = null;

    protected $rules = [
        'reconciliation_date' => 'required|date',
        'shift_name'          => 'required|string|max:50',
        'starting_cash'       => 'required|numeric|min:0',
        'actual_cash'         => 'required|numeric|min:0',
        'notes'               => 'nullable|string|max:500',
    ];

    public function mount(): void
    {
        $this->reconciliation_date = Carbon::today()->toDateString();
    }

    public function submit(): void
    {
        $this->validate();

        $user = auth()->user();
        $date = $this->reconciliation_date;

        $cashSales = (float) Order::whereDate('created_at', $date)
            ->where('payment_method', 'cash')
            ->sum('total');

        $qrisSales = (float) Order::whereDate('created_at', $date)
            ->where('payment_method', 'qris')
            ->sum('total');

        $cashExpenses = (float) Expense::whereDate('expense_date', $date)
            ->sum('amount');

        $starting = (float) $this->starting_cash;
        $actual = (float) $this->actual_cash;
        $expected = $starting + $cashSales - $cashExpenses;
        $diff = $actual - $expected;

        $recon = Reconciliation::create([
            'store_id'            => $user->tenantId(),
            'user_id'             => $user->id,
            'reconciliation_date' => $date,
            'shift_name'          => $this->shift_name,
            'starting_cash'       => $starting,
            'cash_sales'          => $cashSales,
            'qris_sales'          => $qrisSales,
            'cash_expenses'       => $cashExpenses,
            'expected_cash'       => $expected,
            'actual_cash'         => $actual,
            'difference'          => $diff,
            'notes'               => $this->notes ?: null,
        ]);

        $this->detailReconId = $recon->id;
        $this->reset(['actual_cash', 'notes']);
        session()->flash('message', 'Rekonsiliasi kasir berhasil disimpan!');
    }

    public function showDetail(string $id): void
    {
        $this->detailReconId = $id;
    }

    public function closeDetail(): void
    {
        $this->detailReconId = null;
    }

    public function render()
    {
        $date = $this->reconciliation_date ?: Carbon::today()->toDateString();

        $cashSales = (float) Order::whereDate('created_at', $date)
            ->where('payment_method', 'cash')
            ->sum('total');

        $qrisSales = (float) Order::whereDate('created_at', $date)
            ->where('payment_method', 'qris')
            ->sum('total');

        $cashExpenses = (float) Expense::whereDate('expense_date', $date)
            ->sum('amount');

        $starting = (float) ($this->starting_cash ?: 0);
        $expectedCash = $starting + $cashSales - $cashExpenses;

        $actual = is_numeric($this->actual_cash) ? (float) $this->actual_cash : null;
        $difference = $actual !== null ? ($actual - $expectedCash) : null;

        $history = Reconciliation::with('user')
            ->latest('reconciliation_date')
            ->latest('created_at')
            ->paginate(10);

        $selectedRecon = $this->detailReconId
            ? Reconciliation::with('user', 'store')->find($this->detailReconId)
            : null;

        $receiptSetting = ReceiptSetting::current();

        return view('livewire.rekonsiliasi', [
            'cashSales'      => $cashSales,
            'qrisSales'      => $qrisSales,
            'cashExpenses'   => $cashExpenses,
            'expectedCash'   => $expectedCash,
            'actual'         => $actual,
            'difference'     => $difference,
            'history'        => $history,
            'selectedRecon'  => $selectedRecon,
            'receiptSetting' => $receiptSetting,
        ])->layout('layouts.app');
    }
}
