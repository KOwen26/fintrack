<?php

namespace App\Services\Import;

use App\Enums\Cashflow;
use Carbon\CarbonImmutable;

/** One parsed statement line — nulls mark values that failed to parse. */
final readonly class ParsedRow
{
    /**
     * @param  array<int, string|null>  $raw  original row columns
     * @param  array<string, string>  $errors  field name => message
     */
    public function __construct(
        public readonly ?CarbonImmutable $date,
        public readonly ?string $description,
        public readonly ?float $amount,
        public readonly ?Cashflow $flow,
        public readonly array $raw,
        public readonly array $errors = [],
    ) {}
}
