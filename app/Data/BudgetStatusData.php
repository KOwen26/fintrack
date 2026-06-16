<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class BudgetStatusData extends Data
{
    public function __construct(
        public readonly string $limit_amount,
        public readonly string $spend,
        public readonly float $percentage,
        public readonly string $status, // 'on_track' | 'at_risk' | 'over_budget'
    ) {}
}
