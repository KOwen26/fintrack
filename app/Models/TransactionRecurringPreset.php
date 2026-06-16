<?php

namespace App\Models;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionPresetType;
use Carbon\Carbon;
use Database\Factories\TransactionRecurringPresetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionRecurringPreset extends Model
{
    /** @use HasFactory<TransactionRecurringPresetFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'type' => TransactionPresetType::class,
        'frequency' => RecurringFrequency::class,
        'amount' => 'decimal:2',
        'next_run_date' => 'date',
        'recurrence_end_date' => 'date',
        'last_run_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function advanceNextRunDate(Carbon $from): Carbon
    {
        return match ($this->frequency) {
            RecurringFrequency::Daily => $from->addDay(),
            RecurringFrequency::Weekly => $from->addWeek(),
            RecurringFrequency::Fortnightly => $from->addWeeks(2),
            RecurringFrequency::Monthly => $from->addMonthNoOverflow(),
            RecurringFrequency::Yearly => $from->addYear(),
        };
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->where('next_run_date', '<=', today())
            ->where('is_active', true)
            ->whereNull('deleted_at');
    }
}
