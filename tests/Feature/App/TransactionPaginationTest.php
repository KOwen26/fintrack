<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Objects\TransactionCursor;
use App\Services\TransactionService;

function paginationAccountForUser(): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    return [$user, $account];
}

/** Rows of a service page keyed by id for overlap checks. */
function pageIds(array $page): array
{
    return array_map(fn (array $row): int => $row['id'], $page['rows']);
}

/** Attributes shaping a plain recent-dated row for the paged-account tests. */
function pagedRowAttributes(int $account_id, int $created_by): array
{
    return [
        'account_id' => $account_id,
        'created_by' => $created_by,
        // The default list period is the last 6 months — stay inside it.
        'transaction_date' => fake()->dateTimeBetween('-5 months', 'now')->format('Y-m-d H:i:s'),
    ];
}

test('returns the first page limited to PAGE_SIZE with totals', function (): void {
    [$user, $account] = paginationAccountForUser();
    Transaction::factory()->count(65)->create(pagedRowAttributes($account->id, $user->id));

    // Another user's rows must not leak into pages or totals.
    $foreign = User::factory()->create();
    Transaction::factory()->count(3)->create(pagedRowAttributes(
        Account::factory()->create(['owner_id' => $foreign->id])->id,
        $foreign->id,
    ));

    $page = TransactionService::getTransactionsPage($user, null);

    expect($page['rows'])->toHaveCount(TransactionService::PAGE_SIZE)
        ->and($page['total'])->toBe(65)
        ->and($page['has_more'])->toBeTrue();
});

test('cursor continues after the last row without overlap or skipping', function (): void {
    [$user, $account] = paginationAccountForUser();
    Transaction::factory()->count(65)->create(pagedRowAttributes($account->id, $user->id));

    $first = TransactionService::getTransactionsPage($user, null);
    $last = $first['rows'][TransactionService::PAGE_SIZE - 1];

    $second = TransactionService::getTransactionsPage(
        $user,
        TransactionCursor::fromWire($last['transaction_date'] . '|' . $last['id']),
    );

    expect($second['rows'])->toHaveCount(5)
        ->and($second['total'])->toBe(65)
        ->and($second['has_more'])->toBeFalse()
        ->and(array_intersect(pageIds($first), pageIds($second)))->toBeEmpty()
        ->and(array_unique([...pageIds($first), ...pageIds($second)]))->toHaveCount(65);
});

test('same-day rows split by id, not date', function (): void {
    [$user, $account] = paginationAccountForUser();

    // 65 rows share one exact datetime: a date-only comparison would deliver
    // them all in page 1; the id tiebreaker must carry the boundary.
    Transaction::factory()->count(65)->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'transaction_date' => '2026-10-19 09:00:00',
    ]);

    $first = TransactionService::getTransactionsPage($user, null);
    $last = $first['rows'][TransactionService::PAGE_SIZE - 1];

    expect($first['rows'])->toHaveCount(60)
        ->and($last['transaction_date'])->toStartWith('2026-10-19');

    $second = TransactionService::getTransactionsPage(
        $user,
        TransactionCursor::fromWire($last['transaction_date'] . '|' . $last['id']),
    );

    expect($second['rows'])->toHaveCount(5)
        ->and(array_intersect(pageIds($first), pageIds($second)))->toBeEmpty();
});

test('transfer inflow rows are excluded from pages and totals', function (): void {
    [$user, $account] = paginationAccountForUser();
    $destination = Account::factory()->create(['owner_id' => $user->id]);
    $transfer = Transfer::factory()->create(['created_by' => $user->id]);
    Transaction::factory()->count(10)->create(pagedRowAttributes($account->id, $user->id));
    Transaction::factory()->transferOutflow($transfer->id)->create(pagedRowAttributes($account->id, $user->id));
    Transaction::factory()->transferInflow($transfer->id)->create(pagedRowAttributes($destination->id, $user->id));

    $page = TransactionService::getTransactionsPage($user, null);

    expect($page['total'])->toBe(11)
        ->and(array_filter($page['rows'], fn (array $row): bool => $row['type'] === 'transfer'))->toBeEmpty();
});

test('getTransactions stays unpaginated for other consumers', function (): void {
    [$user, $account] = paginationAccountForUser();
    Transaction::factory()->count(TransactionService::PAGE_SIZE + 5)->create(pagedRowAttributes($account->id, $user->id));

    expect(TransactionService::getTransactions($user))->toHaveCount(TransactionService::PAGE_SIZE + 5);
});

test('malformed cursors are rejected', function (): void {
    User::factory()->create();

    expect(fn (): ?TransactionCursor => TransactionCursor::fromWire('not-a-cursor'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn (): ?TransactionCursor => TransactionCursor::fromWire('2026-10-19T09:00:00+00:00|x'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn (): ?TransactionCursor => TransactionCursor::fromWire('|1'))
        ->toThrow(InvalidArgumentException::class)
        ->and(TransactionCursor::fromWire(null))->toBeNull()
        ->and(TransactionCursor::fromWire(''))->toBeNull();
});
