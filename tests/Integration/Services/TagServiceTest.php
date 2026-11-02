<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\TagService;

it('normalizes raw tags to ordered unique snake_case slugs', function (array $raw, array $expected): void {
    expect(TagService::normalize($raw))->toBe($expected);
})->with([
    'symbols and casing collapse' => [['Bills & Rent!'], ['bills_rent']],
    'leading trailing junk is trimmed' => [['--FOOD--'], ['food']],
    'case and symbol variants dedupe' => [['Food', 'food!', 'FOOD'], ['food']],
    'separator runs collapse to one' => [['Kopi  Senja!!'], ['kopi_senja']],
    'surrounding spacing is trimmed' => [['  spacing  test  '], ['spacing_test']],
    'slug truncates at 32 chars' => [[str_repeat('a', 40)], [str_repeat('a', 32)]],
    'per-tag truncation before dedupe' => [['a', str_repeat('b', 64), 'a'], ['a', str_repeat('b', 32)]],
    'symbol-only input drops out' => [['!!!', '   ', ''], []],
]);

it('keeps unicode letters and digits', function (): void {
    expect(TagService::normalize(['Görk Uçuş-2']))->toBe(['görk_uçuş_2']);
});

it('caps the list at MAX_TAGS preserving first-seen order', function (): void {
    $raw = array_map(fn (int $i): string => "tag {$i}", range(1, 12));

    $normalized = TagService::normalize($raw);

    expect($normalized)->toHaveCount(TagService::MAX_TAGS)
        ->and($normalized[0])->toBe('tag_1')
        ->and($normalized[9])->toBe('tag_10');
});

it('returns null when nothing survives normalization', function (array $raw): void {
    expect(TagService::normalizeToNullable($raw))->toBeNull();
})->with([
    'empty input' => [[]],
    'symbol-only tags' => [['!!!']],
    'blank strings' => [['', '  ']],
]);

it('returns the sorted distinct tag union across the user’s live rows', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);
    $other = User::factory()->create();

    $transfer = Transfer::factory()->create(['created_by' => $user->id]);

    Transaction::factory()->create(['account_id' => $account->id, 'created_by' => $user->id, 'tags' => ['bills']]);
    Transaction::factory()->transferOutflow($transfer)->create(['account_id' => $account->id, 'created_by' => $user->id, 'tags' => ['food']]);
    Transaction::factory()->transferFee($transfer)->create(['account_id' => $account->id, 'created_by' => $user->id]);
    Transaction::factory()->create(['created_by' => $other->id, 'tags' => ['private']]);

    expect(TagService::getUniqueTags($user))->toBe(['bills', 'food']);
});

it('excludes soft-deleted rows from tag suggestions', function (): void {
    $user = User::factory()->create();
    $doomed = Transaction::factory()->create(['created_by' => $user->id, 'tags' => ['gone']]);
    Transaction::factory()->create(['created_by' => $user->id, 'tags' => ['kept']]);

    $doomed->delete();

    expect(TagService::getUniqueTags($user))->toBe(['kept']);
});
