<?php

namespace App\Models;

use App\Enums\ImportBatchStatus;
use Database\Factories\ImportBatchFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(ImportBatchFactory::class)]
final class ImportBatch extends Model
{
    /** @use HasFactory<ImportBatchFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ImportBatchStatus::class,
            'total_rows' => 'integer',
            'imported_rows' => 'integer',
            'approved_at' => 'datetime',
            'discarded_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'batch_id');
    }
}
