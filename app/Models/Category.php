<?php

namespace App\Models;

use App\Enums\CategoryType;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'cosmetics' => 'array',
        'type' => CategoryType::class,
        'order' => 'decimal:3',
    ];

    public function getIconAttribute(): string
    {
        $cosmetics = $this->cosmetics ?? [];

        return $cosmetics['icon'] ?? 'ph:tag';
    }

    public function getColorAttribute(): string
    {
        $cosmetics = $this->cosmetics ?? [];

        return $cosmetics['color'] ?? '#6366f1';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
}
