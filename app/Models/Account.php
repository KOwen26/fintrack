<?php

namespace App\Models;

use App\Enums\AccountAccessType;
use App\Enums\AccountType;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $user): Builder
    {
        $householdIds = HouseholdMember::query()
            ->where('user_id', $user->id)
            ->whereNotNull('joined_at')
            ->pluck('household_id');

        return $query->where(function (Builder $q) use ($user, $householdIds): void {
            $q->where('owner_id', $user->id)
                ->orWhere(function (Builder $q) use ($householdIds): void {
                    $q->where('access_type', AccountAccessType::Joint->value)
                        ->whereIn('household_id', $householdIds);
                });
        });
    }

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'access_type' => AccountAccessType::class,
            'initial_balance' => 'decimal:2',
            'credit_card_limit' => 'decimal:2',
            'archived_at' => 'datetime',
            'cosmetics' => 'array',
        ];
    }
}
