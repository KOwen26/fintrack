<?php

namespace App\Models;

use App\Enums\Cashflow;
use App\Enums\Category;
use App\Enums\TransactionType;
use App\Objects\DatePeriod;
use App\Objects\Tags;
use App\Observers\TransactionObserver;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[ObservedBy([TransactionObserver::class])]
final class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'category_id' => Category::class,
            'type' => TransactionType::class,
            'flow' => Cashflow::class,
            'amount' => 'integer',
            'transaction_date' => 'datetime',
            'tags' => Tags::class,
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The transfer unit this row belongs to — null for plain rows. */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    #[Scope]
    protected function withinPeriod(Builder $query, DatePeriod $period): Builder
    {
        return $query->whereBetween('transaction_date', $period->toRange());
    }

    /** Restrict rows to the given account set — the report queries' scope prefix. */
    #[Scope]
    protected function byAccounts(Builder $query, Collection $accountIds): Builder
    {
        return $query->whereIn('account_id', $accountIds);
    }

    /** Restrict rows to the given transaction types; enum instances only, no raw literals. */
    #[Scope]
    protected function byTypes(Builder $query, TransactionType ...$types): Builder
    {
        return $query->whereIn('type', $types);
    }
}
