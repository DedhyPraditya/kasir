<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $date = $request->query('date', Carbon::today()->toDateString());

        $expenses = Expense::with('user:id,username')
            ->whereDate('expense_date', $date)
            ->latest()
            ->get();

        $totalToday = Expense::whereDate('expense_date', $date)->sum('amount');

        return response()->json([
            'date' => $date,
            'total_amount' => (float) $totalToday,
            'data' => $expenses->map(fn (Expense $e) => [
                'id' => $e->id,
                'amount' => (float) $e->amount,
                'category' => $e->category,
                'description' => $e->description,
                'expense_date' => $e->expense_date?->format('Y-m-d') ?? (string) $e->expense_date,
                'created_by' => $e->user?->username ?? 'Kasir',
                'created_at' => $e->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'string', 'uuid'],
            'amount' => ['required', 'numeric', 'min:1'],
            'category' => ['nullable', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:255'],
            'expense_date' => ['nullable', 'date'],
        ]);

        $user = auth()->user();
        $storeId = $user->tenantId();

        // Idempotency: Jika ID dikirim dari mobile (antrean offline), cek apakah sudah ada
        if (! empty($data['id'])) {
            $existing = Expense::withoutGlobalScopes()->find($data['id']);
            if ($existing) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pengeluaran sudah tersimpan sebelumnya.',
                    'data' => [
                        'id' => $existing->id,
                        'amount' => (float) $existing->amount,
                        'category' => $existing->category,
                        'description' => $existing->description,
                        'expense_date' => $existing->expense_date?->format('Y-m-d') ?? (string) $existing->expense_date,
                    ],
                ], 200);
            }
        }

        $expense = Expense::create([
            'id' => $data['id'] ?? (string) Str::uuid(),
            'store_id' => $storeId,
            'user_id' => $user->id,
            'amount' => $data['amount'],
            'category' => $data['category'] ?: 'Operasional',
            'description' => $data['description'],
            'expense_date' => $data['expense_date'] ?? Carbon::today()->toDateString(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengeluaran berhasil dicatat.',
            'data' => [
                'id' => $expense->id,
                'amount' => (float) $expense->amount,
                'category' => $expense->category,
                'description' => $expense->description,
                'expense_date' => $expense->expense_date?->format('Y-m-d') ?? (string) $expense->expense_date,
            ],
        ], 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $expense = Expense::findOrFail($id);
        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Catatan pengeluaran berhasil dihapus.',
        ]);
    }
}
