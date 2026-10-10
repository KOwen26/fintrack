<?php

namespace App\Models;

use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use App\Objects\Decoration;
use Database\Factories\ProviderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Provider extends Model
{
    /** @use HasFactory<ProviderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => ProviderType::class,
            'decorations' => Decoration::class,
            'status' => ProviderStatus::class,
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
