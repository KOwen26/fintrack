<?php

use App\Models\Transaction;
use App\Objects\Tags;

it('normalizes on write and hydrates a Tags object on read', function (): void {
    $transaction = Transaction::factory()->create(['tags' => ['Food', 'food!']]);

    expect(json_decode($transaction->getRawOriginal('tags'), true))->toBe(['food'])
        ->and($transaction->tags)->toBeInstanceOf(Tags::class)
        ->and($transaction->tags->toArray())->toBe(['food']);
});

it('stores null when tags are absent or empty', function (): void {
    $absent = Transaction::factory()->create();

    $empty = Transaction::factory()->create(['tags' => []]);

    expect($absent->getRawOriginal('tags'))->toBeNull()
        ->and($empty->getRawOriginal('tags'))->toBeNull();
});

it('accepts a Tags instance on write', function (): void {
    $transaction = Transaction::factory()->make();

    $transaction->fill(['tags' => Tags::fromArray(['a', 'A!'])])->save();

    expect($transaction->fresh()->tags?->toArray())->toBe(['a']);
});
