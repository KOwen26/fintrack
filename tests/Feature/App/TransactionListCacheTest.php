<?php

use App\Enums\Category;
use App\Enums\DatePeriodPreset;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionListService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** The period every test caches and asserts against. */
function listPeriod(): DatePeriodPreset
{
    return DatePeriodPreset::Last3Months;
}

/** Creates a user with one in-window transaction and primes the list cache. */
function primeTransactionListCache(): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);
    Transaction::factory()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'transaction_date' => now(),
        'description' => 'Cached row',
    ]);

    TransactionListService::getCachedTransactions($user, listPeriod());

    return [$user, $account, TransactionListService::cacheKey($user->id)];
}

function listedIds(User $user): array
{
    return array_map(
        fn (array $row): int => $row['id'],
        TransactionListService::getCachedTransactions($user, listPeriod()),
    );
}

test('serves the full-window list from cache without re-querying', function (): void {
    [$user] = primeTransactionListCache();

    expect(Cache::has(TransactionListService::cacheKey($user->id)))->toBeTrue();

    DB::enableQueryLog();
    $ids = listedIds($user);

    expect($ids)->toHaveCount(1)
        // A hit must touch no transaction tables — only the cache store.
        ->and(collect(DB::getQueryLog())->filter(
            fn (array $entry): bool => str_contains($entry['query'], 'transactions'),
        )->all())->toBeEmpty();
});

test('the raw getTransactions read bypasses the cache', function (): void {
    [$user] = primeTransactionListCache();

    DB::enableQueryLog();
    $ids = TransactionListService::getTransactions($user, listPeriod());

    // A warm cache entry exists, yet the raw read re-queries the tables.
    expect($ids)->toHaveCount(1)
        ->and(Cache::has(TransactionListService::cacheKey($user->id)))->toBeTrue()
        ->and(collect(DB::getQueryLog())->filter(
            fn (array $entry): bool => str_contains($entry['query'], 'transactions'),
        )->all())->not->toBeEmpty();
});

test('creating a transaction invalidates the cached list', function (): void {
    [$user, $account, $key] = primeTransactionListCache();

    $this->actingAs($user)->post(route('transactions.store'), [
        'account_id' => $account->id,
        'type' => TransactionType::Income->value,
        'amount' => 5_000_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => Category::Salary->value,
        'description' => 'Fresh row',
    ])->assertRedirectToRoute('transactions.index');

    $freshId = Transaction::where('description', 'Fresh row')->value('id');

    expect(Cache::has($key))->toBeFalse()
        ->and(listedIds($user))->toContain($freshId);
});

test('deleting a transaction invalidates the cached list', function (): void {
    [$user, $account, $key] = primeTransactionListCache();

    $doomed = Transaction::factory()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'transaction_date' => now(),
        'description' => 'Doomed row',
    ]);

    // Re-prime so the cache holds the row the delete must evict.
    TransactionListService::getCachedTransactions($user, listPeriod());

    $this->actingAs($user)->delete(route('transactions.destroy', $doomed))
        ->assertRedirectToRoute('transactions.index');

    expect(Cache::has($key))->toBeFalse()
        ->and(listedIds($user))->not->toContain($doomed->id);
});
