<?php

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * @deprecated Resolved decoration items are no longer stored in the database.
 *             Use getDecorationIcon() / getDecorationColor() helpers on the
 *             frontend to look up full decoration data by slug.
 */
class DecorationItemData extends Data
{
    public function __construct(
        public string $id,
        public string $value,
        public ?string $text_color = null,
    ) {}
}
