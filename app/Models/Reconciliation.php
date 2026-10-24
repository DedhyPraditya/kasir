<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reconciliation extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'store_id',
        'user_id',
        'reconciliation_date',
        'shift_name',
        'starting_cash',
        'cash_sales',
        'qris_sales',
        'cash_expenses',
        'expected_cash',
        'actual_cash',
        'difference',
        'notes',
    ];

    protected $casts = [
        'reconciliation_date' => 'date:Y-m-d',
        'starting_cash'       => 'decimal:2',
        'cash_sales'          => 'decimal:2',
        'qris_sales'          => 'decimal:2',
        'cash_expenses'       => 'decimal:2',
        'expected_cash'       => 'decimal:2',
        'actual_cash'         => 'decimal:2',
        'difference'          => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
