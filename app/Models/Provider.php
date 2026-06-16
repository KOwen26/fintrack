<?php

namespace App\Models;

use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use Database\Factories\ProviderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provider extends Model
{
    /** @use HasFactory<ProviderFactory> */
    use HasFactory;

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    protected function casts(): array
    {
        return [
            'type' => ProviderType::class,
            'status' => ProviderStatus::class,
            'cosmetics' => 'array',
        ];
    }
}
