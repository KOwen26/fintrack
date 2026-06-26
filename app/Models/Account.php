<?php

namespace App\Models;

use App\Data\DecorationData;
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
    public const string DEFAULT_CURRENCY = 'IDR';

    /** @use HasFactory<AccountFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $attributes = [
        'currency' => self::DEFAULT_CURRENCY,
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'access_type' => AccountAccessType::class,
            'initial_balance' => 'decimal:2',
            'credit_card_limit' => 'decimal:2',
            'archived_at' => 'datetime',
            'decorations' => DecorationData::class,
        ];
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
    protected function notArchived(Builder $builder): void
    {
        $builder->whereNull('archived_at');
    }

    #[Scope]
    protected function shareable(Builder $builder): void
    {
        $builder->orWhere('access_type', AccountAccessType::Joint);
    }
}
