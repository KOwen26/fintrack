<?php

namespace App\Models;

use App\Enums\AccountAccessType;
use App\Enums\AccountType;
use App\Objects\Decoration;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'access_type' => AccountAccessType::class,
            'current_balance' => 'integer',
            'initial_balance' => 'integer',
            'decorations' => Decoration::class,
            'archived_at' => 'datetime',
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

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    #[Scope]
    protected function notArchived(Builder $builder): void
    {
        $builder->whereNull('archived_at');
    }

    #[Scope]
    protected function archived(Builder $builder): void
    {
        $builder->whereNotNull('archived_at');
    }

    #[Scope]
    protected function shareable(Builder $builder): void
    {
        $builder->orWhere('access_type', AccountAccessType::Joint);
    }
}
