<?php

use App\Data\Transaction\TransactionListFilters;
use App\Enums\DatePeriodPreset;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\TransactionListService;

function tagFilterAccountForUser(): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    return [$user, $account];
}

/** Rows of a service page keyed by id — locally owned, globally unique. */
function tagFilterPageIds(array $page): array
{
    return array_map(fn (array $row): int => $row['id'], $page['transactions']);
}

/** A plain recent-dated row inside the default list window. */
function tagFilterRowAttributes(int $account_id, int $created_by): array
{
    return [
        'account_id' => $account_id,
        'created_by' => $created_by,
        'transaction_date' => fake()->dateTimeBetween('-5 months', 'now')->format('Y-m-d H:i:s'),
    ];
}

test('filters rows by a single tag', function (): void {
    [$user, $account] = tagFilterAccountForUser();

    $tagged = Transaction::factory()->create([
        ...tagFilterRowAttributes($account->id, $user->id),
        'tags' => ['food'],
    ]);
    Transaction::factory()->create([...tagFilterRowAttributes($account->id, $user->id), 'tags' => ['bills']]);
    Transaction::factory()->create(tagFilterRowAttributes($account->id, $user->id));

    $page = TransactionListService::getTransactionsByCursorPage(
        $user,
        null,
        DatePeriodPreset::Last3Months,
        new TransactionListFilters(tags: ['food']),
    );

    expect(tagFilterPageIds($page))->toBe([$tagged->id]);
});

test('multiple selected tags match with OR semantics', function (): void {
    [$user, $account] = tagFilterAccountForUser();

    $food = Transaction::factory()->create([...tagFilterRowAttributes($account->id, $user->id), 'tags' => ['food']]);
    $bills = Transaction::factory()->create([...tagFilterRowAttributes($account->id, $user->id), 'tags' => ['bills']]);
    Transaction::factory()->create([...tagFilterRowAttributes($account->id, $user->id), 'tags' => ['utilities']]);

    $page = TransactionListService::getTransactionsByCursorPage(
        $user,
        null,
        DatePeriodPreset::Last3Months,
        new TransactionListFilters(tags: ['food', 'bills']),
    );

    expect(tagFilterPageIds($page))->toEqualCanonicalizing([$food->id, $bills->id]);
});

test('a tagged transfer unit surfaces exactly once — its outflow representative', function (): void {
    [$user, $account] = tagFilterAccountForUser();
    $destination = Account::factory()->create(['owner_id' => $user->id]);

    $transfer = Transfer::factory()->create(['created_by' => $user->id]);

    Transaction::factory()->transferOutflow($transfer)->create([
        ...tagFilterRowAttributes($account->id, $user->id),
        'tags' => ['monthly_sweep'],
    ]);
    Transaction::factory()->transferInflow($transfer)->create(tagFilterRowAttributes($destination->id, $user->id));
    Transaction::factory()->transferFee($transfer)->create(tagFilterRowAttributes($account->id, $user->id));

    $page = TransactionListService::getTransactionsByCursorPage(
        $user,
        null,
        DatePeriodPreset::Last3Months,
        new TransactionListFilters(tags: ['monthly_sweep']),
    );

    $ids = tagFilterPageIds($page);

    expect($ids)->toHaveCount(1)
        ->and(Transaction::query()->find($ids[0])->flow->value)->toBe('outflow');
});

test('rejects malformed tag filter values without rendering', function (): void {
    [$user] = tagFilterAccountForUser();

    $this->actingAs($user)->get(route('transactions.index', [
        'filters' => [['id' => 'tags', 'value' => ['Not A Slug!']]],
    ]))->assertSessionHasErrors('filters');
});

test('accepts the tags filter id with valid slugs', function (): void {
    [$user] = tagFilterAccountForUser();

    $this->actingAs($user)->get(route('transactions.index', [
        'filters' => [['id' => 'tags', 'value' => ['food']]],
    ]))->assertOk();
});

test('global search matches stored tags', function (): void {
    [$user, $account] = tagFilterAccountForUser();

    $tagged = Transaction::factory()->create([
        ...tagFilterRowAttributes($account->id, $user->id),
        'tags' => ['starbucks_coffee'],
        'description' => null,
    ]);
    Transaction::factory()->create([...tagFilterRowAttributes($account->id, $user->id), 'description' => null]);

    $page = TransactionListService::getTransactionsByCursorPage(
        $user,
        null,
        DatePeriodPreset::Last3Months,
        new TransactionListFilters(search: 'starbucks'),
    );

    expect(tagFilterPageIds($page))->toBe([$tagged->id]);
});

test('passes the tags prop on the transaction index', function (): void {
    [$user, $account] = tagFilterAccountForUser();
    Transaction::factory()->create([...tagFilterRowAttributes($account->id, $user->id), 'tags' => ['food']]);

    $this->actingAs($user)->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/transaction/index')
            ->where('tags', ['food'])
        );
});
