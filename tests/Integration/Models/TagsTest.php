<?php

use App\Models\Transaction;
use App\Objects\Tags;
use Illuminate\Support\Facades\DB;

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

it('persists null for an empty Tags instance', function (): void {
    $transaction = Transaction::factory()->make();

    $transaction->fill(['tags' => new Tags([])])->save();

    expect($transaction->getRawOriginal('tags'))->toBeNull()
        ->and($transaction->fresh()->tags)->toBeNull();
});

it('round-trips tags through a write and a fresh read', function (): void {
    $transaction = Transaction::factory()->make();

    $transaction->fill(['tags' => ['Bills & Rent!', 'food']])->save();

    expect($transaction->fresh()->tags?->toArray())->toBe(['bills_rent', 'food']);
});

/**
 * Forces a raw stored value, bypassing the cast — the only way to arrange
 * legacy/non-canonical json and prove hydration defends against it.
 */
function forceStoredTagsOnTransaction(Transaction $transaction, string $json): void
{
    DB::table('transactions')->where('id', $transaction->id)->update(['tags' => $json]);
}

it('hydrates null from empty or all-dropped stored json', function (string $stored): void {
    $transaction = Transaction::factory()->create(['tags' => ['food']]);

    forceStoredTagsOnTransaction($transaction, $stored);

    expect($transaction->fresh()->tags)->toBeNull();
})->with([
    'empty json' => ['[]'],
    'normalizes-to-empty json' => ['["!!!"]'],
]);
