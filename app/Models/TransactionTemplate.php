<?php

namespace App\Models;

use App\Enums\Category;
use App\Enums\TransactionType;
use App\Objects\Decoration;
use Database\Factories\TransactionTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TransactionTemplate extends Model
{
    /** @use HasFactory<TransactionTemplateFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'category_id' => Category::class,
            'amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'decorations' => Decoration::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Optional default source account — a soft default when applying, never a restriction. */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** Fixed destination for transfer templates. */
    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'destination_account_id');
    }
}
