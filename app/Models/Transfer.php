<?php

namespace App\Models;

use App\Enums\TransactionFlow;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:0',
            'fee_amount' => 'decimal:0',
            'transaction_date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** All unit member rows: source row, destination row, optional fee row. */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** The (transfer, outflow) row booked on the source account. */
    public function sourceTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class)
            ->where('type', TransactionType::Transfer->value)
            ->where('flow', TransactionFlow::Outflow->value);
    }

    /** The (transfer, inflow) row booked on the destination account. */
    public function destinationTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class)
            ->where('type', TransactionType::Transfer->value)
            ->where('flow', TransactionFlow::Inflow->value);
    }

    /** The (expense, outflow) fee row — may not exist. */
    public function feeTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class)
            ->where('type', TransactionType::Expense->value);
    }
}
