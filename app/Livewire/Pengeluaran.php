<?php

namespace App\Livewire;

use App\Models\Expense;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class Pengeluaran extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    private const PER_PAGE = 15;

    public string $dateFrom = '';
    public string $dateTo = '';
    public string $categoryFilter = '';
    public string $search = '';

    // Form modal state
    public bool $isModalOpen = false;
    public ?string $editingId = null;
    public string $amount = '';
    public string $category = 'Operasional';
    public string $description = '';
    public string $expense_date = '';

    protected function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1'],
            'category' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:255'],
            'expense_date' => ['required', 'date'],
        ];
    }

    public function mount(): void
    {
        $this->dateFrom = Carbon::now()->startOfMonth()->toDateString();
        $this->dateTo = Carbon::today()->toDateString();
        $this->expense_date = Carbon::today()->toDateString();
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingDateFrom(): void { $this->resetPage(); }
    public function updatingDateTo(): void { $this->resetPage(); }
    public function updatingCategoryFilter(): void { $this->resetPage(); }

    public function openModal(): void
    {
        $this->resetValidation();
        $this->reset(['editingId', 'amount', 'description']);
        $this->category = 'Operasional';
        $this->expense_date = Carbon::today()->toDateString();
        $this->isModalOpen = true;
    }

    public function edit(string $id): void
    {
        $this->resetValidation();
        $expense = Expense::findOrFail($id);

        $this->editingId = $expense->id;
        $this->amount = (string) (int) $expense->amount;
        $this->category = $expense->category;
        $this->description = $expense->description;
        $this->expense_date = $expense->expense_date?->format('Y-m-d') ?? (string) $expense->expense_date;
        $this->isModalOpen = true;
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->reset(['editingId', 'amount', 'description']);
    }

    public function save(): void
    {
        $this->validate();

        $user = auth()->user();

        if ($this->editingId) {
            $expense = Expense::findOrFail($this->editingId);
            $expense->update([
                'amount' => $this->amount,
                'category' => $this->category,
                'description' => $this->description,
                'expense_date' => $this->expense_date,
            ]);
            session()->flash('message', 'Catatan pengeluaran berhasil diperbarui.');
        } else {
            Expense::create([
                'store_id' => $user->tenantId(),
                'user_id' => $user->id,
                'amount' => $this->amount,
                'category' => $this->category,
                'description' => $this->description,
                'expense_date' => $this->expense_date,
            ]);
            session()->flash('message', 'Pengeluaran baru berhasil dicatat.');
        }

        $this->closeModal();
    }

    public function delete(string $id): void
    {
        $expense = Expense::findOrFail($id);
        $expense->delete();
        session()->flash('message', 'Catatan pengeluaran berhasil dihapus.');
    }

    public function render()
    {
        $today = Carbon::today()->toDateString();
        $currentMonth = Carbon::today()->month;
        $currentYear = Carbon::today()->year;

        $totalToday = Expense::whereDate('expense_date', $today)->sum('amount');
        $totalMonth = Expense::whereMonth('expense_date', $currentMonth)
            ->whereYear('expense_date', $currentYear)
            ->sum('amount');

        $query = Expense::with('user');

        if ($this->dateFrom) {
            $query->whereDate('expense_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('expense_date', '<=', $this->dateTo);
        }
        if ($this->categoryFilter) {
            $query->where('category', $this->categoryFilter);
        }
        if ($this->search) {
            $query->where('description', 'like', '%' . $this->search . '%');
        }

        $filteredTotal = (clone $query)->sum('amount');
        $expenses = $query->latest('expense_date')->latest('created_at')->paginate(self::PER_PAGE);

        return view('livewire.pengeluaran', [
            'expenses' => $expenses,
            'filteredTotal' => $filteredTotal,
            'totalToday' => $totalToday,
            'totalMonth' => $totalMonth,
            'categories' => ['Operasional', 'Bahan Baku', 'Utilitas', 'Gaji/Uang Makan', 'Lainnya'],
        ])->layout('layouts.app');
    }
}
