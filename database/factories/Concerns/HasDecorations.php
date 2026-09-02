<?php

namespace Database\Factories\Concerns;

use App\Models\DecorationColor;
use App\Models\DecorationIcon;

trait HasDecorations
{
    /**
     * Pick a random active icon and color, tolerating an empty decoration source.
     *
     * @return array{icon: ?string, color: ?string}
     */
    protected function randomDecorations(): array
    {
        return [
            'icon' => DecorationIcon::query()
                ->where('status', 'Active')
                ->inRandomOrder()
                ->first()
                ?->slug,
            'color' => DecorationColor::query()
                ->where('status', 'Active')
                ->inRandomOrder()
                ->first()
                ?->slug,
        ];
    }
}
