<?php

use App\Enums\Cashflow;
use App\Enums\Category;
use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Models\ImportRow;

it('casts row flow, category and boolean flags', function (): void {
    $row = ImportRow::factory()->create([
        'flow' => Cashflow::Inflow,
        'category_id' => Category::OtherIncome,
        'is_included' => false,
        'is_duplicate' => true,
        'parse_errors' => ['amount' => 'No amount column has a readable value.'],
    ]);

    expect($row->fresh())
        ->flow->toBe(Cashflow::Inflow)
        ->category_id->toBe(Category::OtherIncome)
        ->is_included->toBeFalse()
        ->is_duplicate->toBeTrue()
        ->parse_errors->toBe(['amount' => 'No amount column has a readable value.']);
});

it('casts batch status and datetimes', function (): void {
    $batch = ImportBatch::factory()->approved()->create();

    expect($batch->fresh())
        ->status->toBe(ImportBatchStatus::Approved)
        ->approved_at->not->toBeNull();
});
