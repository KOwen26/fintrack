<?php

namespace App\Models;

use App\Models\Concerns\HasSushiJsonSource;
use App\Observers\SushiJsonObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Sushi\Sushi;

#[ObservedBy([SushiJsonObserver::class])]
class DecorationColor extends Model
{
    use HasSushiJsonSource;
    use Sushi;

    protected $primaryKey = 'slug';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'slug', 'group', 'shade', 'label', 'hex', 'oklch', 'text_color', 'status',
    ];

    public function getRows(): array
    {
        return $this->getJsonRows();
    }

    protected function sushiShouldCache(): bool
    {
        return $this->jsonShouldCache();
    }

    protected function sushiCacheReferencePath(): ?string
    {
        return $this->jsonCacheReferencePath();
    }

    public function jsonSourcePath(): string
    {
        return resource_path('js/data/decoration-colors.json');
    }

    /**
     * @return array<int, string>
     */
    public function jsonColumns(): array
    {
        return ['slug', 'group', 'shade', 'label', 'hex', 'oklch', 'text_color', 'status'];
    }
}
