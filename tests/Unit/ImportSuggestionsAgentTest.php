<?php

use App\Ai\Agents\ImportSuggestions;
use Laravel\Ai\Contracts\HasStructuredOutput;

it('lists every bookable category slug in its instructions', function (): void {
    $instructions = (new ImportSuggestions)->instructions();

    expect($instructions)->toContain('dining_out')
        ->toContain('Dining Out')
        ->toContain('Income categories')
        ->toContain('Spending categories')
        ->not->toContain('initial_balance');
});

it('is a structured-output agent', function (): void {
    expect(new ImportSuggestions)->toBeInstanceOf(HasStructuredOutput::class);
});
