<?php

namespace App\Models;

use App\Enums\TransactionPresetType;
use Database\Factories\TransactionPresetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionPreset extends Model
{
    /** @use HasFactory<TransactionPresetFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function defaultCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'default_category_id');
    }

    public function defaultSourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_source_account_id');
    }

    public function defaultDestinationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_destination_account_id');
    }

    protected function casts(): array
    {
        return [
            'type' => TransactionPresetType::class,
            'default_amount' => 'decimal:2',
            'default_transfer_fee' => 'decimal:2',
        ];
    }
}
