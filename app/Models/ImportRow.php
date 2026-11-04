<?php

namespace App\Models;

use App\Enums\Cashflow;
use App\Enums\Category;
use Database\Factories\ImportRowFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(ImportRowFactory::class)]
final class ImportRow extends Model
{
    /** @use HasFactory<ImportRowFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'datetime',
            'amount' => 'float',
            'flow' => Cashflow::class,
            'category_id' => Category::class,
            'is_included' => 'boolean',
            'is_duplicate' => 'boolean',
            'parse_errors' => 'array',
            'raw' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function transferDestination(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'transfer_destination_account_id');
    }
}
