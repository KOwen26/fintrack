<?php

use App\Data\Transaction\TransactionListCursor;
use App\Data\Transaction\TransactionListFilters;
use App\Enums\DatePeriodPreset;
use App\Enums\TransactionSort;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\TransactionListService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

function paginationAccountForUser(): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    return [$user, $account];
}

/** Rows of a service page keyed by id for overlap checks. */
function pageIds(array $page): array
{
    return array_map(fn (array $row): int => $row['id'], $page['transactions']);
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

    $page = TransactionListService::getTransactionsByCursorPage($user, null, DatePeriodPreset::Last3Months);

    expect($page['transactions'])->toHaveCount(TransactionListService::PAGE_SIZE)
        ->and($page['meta']['total'])->toBe(65)
        ->and($page['meta']['has_more'])->toBeTrue()
        ->and($page['meta']['next_cursor'])->toBeString();
});

test('cursor continues after the last row without overlap or skipping', function (): void {
    [$user, $account] = paginationAccountForUser();
    Transaction::factory()->count(65)->create(pagedRowAttributes($account->id, $user->id));

    $first = TransactionListService::getTransactionsByCursorPage($user, null, DatePeriodPreset::Last3Months);

    $second = TransactionListService::getTransactionsByCursorPage(
        $user,
        TransactionListCursor::fromRequest(new Request(['cursor' => $first['meta']['next_cursor']]), TransactionSort::Newest),
        DatePeriodPreset::Last3Months,
    );

    expect($second['transactions'])->toHaveCount(5)
        ->and($second['meta']['total'])->toBe(65)
        ->and($second['meta']['has_more'])->toBeFalse()
        ->and($second['meta']['next_cursor'])->toBeNull()
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

    $first = TransactionListService::getTransactionsByCursorPage($user, null, DatePeriodPreset::Last3Months);
    $last = $first['transactions'][TransactionListService::PAGE_SIZE - 1];

    expect($first['transactions'])->toHaveCount(60)
        ->and($last['transaction_date'])->toStartWith('2026-10-19');

    $second = TransactionListService::getTransactionsByCursorPage(
        $user,
        TransactionListCursor::fromRequest(new Request(['cursor' => $first['meta']['next_cursor']]), TransactionSort::Newest),
        DatePeriodPreset::Last3Months,
    );

    expect($second['transactions'])->toHaveCount(5)
        ->and(array_intersect(pageIds($first), pageIds($second)))->toBeEmpty();
});

test('amount sort walks the full window in order via amount cursors', function (): void {
    [$user, $account] = paginationAccountForUser();

    // Amounts 1..65 (unique) — 'highest' must walk them descending with no
    // dupes or skips, proving the amount-flavor keyset carries the boundary.
    foreach (range(1, 65) as $i) {
        Transaction::factory()->create([
            ...pagedRowAttributes($account->id, $user->id),
            'amount' => $i,
            'transaction_date' => '2026-09-15 12:00:00', // one day: ids must not drive order
        ]);
    }

    $filters = new TransactionListFilters(sort: TransactionSort::Highest);
    $collected = [];

    $page = TransactionListService::getTransactionsByCursorPage($user, null, DatePeriodPreset::Last3Months, filters: $filters);

    while (true) {
        array_push($collected, ...array_map(fn (array $row): float => (float) $row['amount'], $page['transactions']));

        if (! $page['meta']['has_more']) {
            break;
        }

        $page = TransactionListService::getTransactionsByCursorPage(
            $user,
            TransactionListCursor::fromRequest(new Request(['cursor' => $page['meta']['next_cursor']]), TransactionSort::Highest),
            DatePeriodPreset::Last3Months,
            filters: $filters,
        );
    }

    expect($collected)->toHaveCount(65)
        ->and($collected)->each->toBeFloat();

    // Strictly descending: identical to range(65, 1, -1) if no dupe/skip.
    expect($collected)->toBe(array_map(floatval(...), range(65, 1, -1)));
});

test('filters narrow rows and totals', function (): void {
    [$user, $account] = paginationAccountForUser();
    $other = Account::factory()->create(['owner_id' => $user->id]);

    Transaction::factory()->count(4)->create(pagedRowAttributes($account->id, $user->id));
    Transaction::factory()->count(6)->create(pagedRowAttributes($other->id, $user->id));

    $byAccount = TransactionListService::getTransactionsByCursorPage(
        $user,
        null,
        DatePeriodPreset::Last3Months,
        filters: new TransactionListFilters(accountIds: [$account->id]),
    );

    expect($byAccount['meta']['total'])->toBe(4)
        ->and(pageIds($byAccount))->each->toBeIn(
            Transaction::where('account_id', $account->id)->pluck('id')->all()
        );

    $byType = TransactionListService::getTransactionsByCursorPage(
        $user,
        null,
        DatePeriodPreset::Last3Months,
        filters: new TransactionListFilters(types: ['income']),
    );

    expect($byType['transactions'])->toBeEmpty()
        ->and($byType['meta']['total'])->toBe(0)
        ->and($byType['meta']['has_more'])->toBeFalse();
});

test('search matches description, amount, account and category names', function (): void {
    [$user, $account] = paginationAccountForUser();

    Transaction::factory()->create([
        ...pagedRowAttributes($account->id, $user->id),
        'description' => 'Grocery run',
        'amount' => 123.45,
        'type' => 'expense',
    ]);
    Transaction::factory()->create([
        ...pagedRowAttributes($account->id, $user->id),
        'description' => 'Unrelated',
        'amount' => 777.00,
        'type' => 'expense',
    ]);
    Transaction::factory()->create([
        ...pagedRowAttributes($account->id, $user->id),
        'description' => 'Rent',
        'amount' => 50.00,
        'type' => 'expense',
    ]);

    $byDescription = TransactionListService::getTransactionsByCursorPage(
        $user,
        null,
        DatePeriodPreset::Last3Months,
        filters: new TransactionListFilters(search: 'grocery'),
    );
    expect($byDescription['meta']['total'])->toBe(1);

    // Amount-as-text: substring of the stored decimal.
    $byAmount = TransactionListService::getTransactionsByCursorPage(
        $user,
        null,
        DatePeriodPreset::Last3Months,
        filters: new TransactionListFilters(search: '123.4'),
    );
    expect($byAmount['meta']['total'])->toBe(1);

    // The account's own name.
    $byAccountName = TransactionListService::getTransactionsByCursorPage(
        $user,
        null,
        DatePeriodPreset::Last3Months,
        filters: new TransactionListFilters(search: $account->name),
    );
    expect($byAccountName['meta']['total'])->toBe(3);

    // No match anywhere → zero.
    $byMiss = TransactionListService::getTransactionsByCursorPage(
        $user,
        null,
        DatePeriodPreset::Last3Months,
        filters: new TransactionListFilters(search: 'salary'),
    );
    expect($byMiss['meta']['total'])->toBe(0);
});

test('oldest sort walks ascending with ascending cursors', function (): void {
    [$user, $account] = paginationAccountForUser();

    foreach (range(1, 65) as $i) {
        Transaction::factory()->create([
            ...pagedRowAttributes($account->id, $user->id),
            'transaction_date' => now()->subMonths(5)->addDays($i)->format('Y-m-d H:i:s'),
        ]);
    }

    $filters = new TransactionListFilters(sort: TransactionSort::Oldest);
    $page = TransactionListService::getTransactionsByCursorPage($user, null, DatePeriodPreset::Last3Months, filters: $filters);

    $firstDates = array_map(fn (array $row): string => $row['transaction_date'], $page['transactions']);

    expect($firstDates)->each->toBeString();

    // Ascending within the page.
    $sorted = $firstDates;
    sort($sorted);
    expect($firstDates)->toBe($sorted);

    $second = TransactionListService::getTransactionsByCursorPage(
        $user,
        TransactionListCursor::fromRequest(new Request(['cursor' => $page['meta']['next_cursor']]), TransactionSort::Oldest),
        DatePeriodPreset::Last3Months,
        filters: $filters,
    );

    expect(array_intersect(pageIds($page), pageIds($second)))->toBeEmpty()
        ->and(min(array_map(fn (array $row): string => $row['transaction_date'], $second['transactions'])))
        ->toBeGreaterThan(max($firstDates));
});

test('a cursor cut under another sort is rejected', function (): void {
    [$user, $account] = paginationAccountForUser();
    Transaction::factory()->count(2)->create(pagedRowAttributes($account->id, $user->id));

    // A date-flavored cursor is stale under an amount ordering.
    expect(fn (): ?TransactionListCursor => TransactionListCursor::fromRequest(
        new Request(['cursor' => 'date|2026-10-19 09:00:00|3']),
        TransactionSort::Highest,
    ))->toThrow(HttpException::class)
        ->and(fn (): ?TransactionListCursor => TransactionListCursor::fromRequest(
            new Request(['cursor' => 'amount|150.00|3']),
            TransactionSort::Newest,
        ))->toThrow(HttpException::class);
});

test('transfer inflow rows are excluded from pages and totals', function (): void {
    [$user, $account] = paginationAccountForUser();
    $destination = Account::factory()->create(['owner_id' => $user->id]);
    $transfer = Transfer::factory()->create(['created_by' => $user->id]);
    Transaction::factory()->count(10)->create(pagedRowAttributes($account->id, $user->id));
    Transaction::factory()->transferOutflow($transfer->id)->create(pagedRowAttributes($account->id, $user->id));
    Transaction::factory()->transferInflow($transfer->id)->create(pagedRowAttributes($destination->id, $user->id));

    $page = TransactionListService::getTransactionsByCursorPage($user, null, DatePeriodPreset::Last3Months);

    // Inflow rows are hidden; the single outflow row IS the transfer's list
    // representation and must survive.
    expect($page['meta']['total'])->toBe(11)
        ->and(array_filter(
            $page['transactions'],
            fn (array $row): bool => $row['type'] === 'transfer' && $row['flow'] === 'inflow',
        ))->toBeEmpty()
        ->and(array_filter(
            $page['transactions'],
            fn (array $row): bool => $row['type'] === 'transfer' && $row['flow'] === 'outflow',
        ))->toHaveCount(1);
});

test('getTransactions stays unpaginated for other consumers', function (): void {
    [$user, $account] = paginationAccountForUser();
    Transaction::factory()->count(TransactionListService::PAGE_SIZE + 5)->create(pagedRowAttributes($account->id, $user->id));

    expect(TransactionListService::getTransactions($user, DatePeriodPreset::Last6Months))->toHaveCount(TransactionListService::PAGE_SIZE + 5);
});

test('malformed or mismatched cursors are rejected', function (): void {
    User::factory()->create();

    expect(fn (): ?TransactionListCursor => TransactionListCursor::fromRequest(
        new Request(['cursor' => 'not-a-cursor']),
        TransactionSort::Newest,
    ))->toThrow(HttpException::class)
        ->and(fn (): ?TransactionListCursor => TransactionListCursor::fromRequest(new Request(['cursor' => '2026-10-19T09:00:00+00:00|x']), TransactionSort::Newest))
        ->toThrow(HttpException::class)
        ->and(fn (): ?TransactionListCursor => TransactionListCursor::fromRequest(new Request(['cursor' => '|1']), TransactionSort::Newest))
        ->toThrow(HttpException::class)
        ->and(fn (): ?TransactionListCursor => TransactionListCursor::fromRequest(new Request(['cursor' => 'amount|150.00|3']), TransactionSort::Newest))
        ->toThrow(HttpException::class)
        ->and(fn (): ?TransactionListCursor => TransactionListCursor::fromRequest(new Request(['cursor' => 'date|2026-10-19 09:00:00|x']), TransactionSort::Newest))
        ->toThrow(HttpException::class)
        ->and(TransactionListCursor::fromRequest(new Request(['cursor' => null]), TransactionSort::Newest))->toBeNull()
        ->and(TransactionListCursor::fromRequest(new Request(['cursor' => '']), TransactionSort::Newest))->toBeNull();
});
