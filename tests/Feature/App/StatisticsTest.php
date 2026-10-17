<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;

test('renders the statistics page with deferred report props', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 150_000,
        'transaction_date' => now()->format('Y-m-d'),
    ]);

    $this->actingAs($user)->get(route('statistics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/statistics/index')
            // Deferred props are not part of the initial paint.
            ->missing('monthlyCashflow')
            ->missing('cashflowTrend')
            ->missing('balanceTrend')
            ->missing('weekdayPattern')
            ->loadDeferredProps(fn ($page) => $page
                ->has('monthlyCashflow')
                ->has('monthlyCashflow.data', 1)
                ->has('cashflowTrend')
                ->has('balanceTrend')
                ->has('weekdayPattern')
            )
        );
});

test('renders the statistics page for a user without accounts', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('statistics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/statistics/index')
            ->loadDeferredProps(fn ($page) => $page
                ->has('monthlyCashflow')
                ->has('monthlyCashflow.data', 0)
                ->has('cashflowTrend.data', 6)
                ->has('balanceTrend.data', 6)
                ->has('weekdayPattern.data', 7)
            )
        );
});

test('renders a requested month window', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 150_000,
        'transaction_date' => '2026-03-10',
    ]);

    $this->actingAs($user)->get(route('statistics.index', ['month' => '2026-03']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/statistics/index')
            ->loadDeferredProps(fn ($page) => $page
                ->where('monthlyCashflow.from', '2026-03-01')
                ->has('monthlyCashflow.data', 1)
            )
        );
});

test('accepts a Y-m-d month anchor and splits to the month window', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 90_000,
        'transaction_date' => '2026-03-28',
    ]);

    $this->actingAs($user)->get(route('statistics.index', ['month' => '2026-03-15']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/statistics/index')
            ->loadDeferredProps(fn ($page) => $page
                ->where('monthlyCashflow.from', '2026-03-01')
                ->where('monthlyCashflow.to', '2026-03-31')
                ->has('monthlyCashflow.data', 1)
            )
        );
});

test('falls back to the current month for an unparseable month', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('statistics.index', ['month' => 'garbage']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($page) => $page
                ->where('monthlyCashflow.from', now()->startOfMonth()->toDateString())
            )
        );
});

test('applies an explicit trend preset', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('statistics.index', ['trend' => 'last_3months']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($page) => $page
                ->has('cashflowTrend.data', 3)
                ->has('balanceTrend.data', 6)
            )
        );
});

test('maps multi-year trend presets to rolling month windows', function (string $trend, int $buckets): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('statistics.index', ['trend' => $trend]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($page) => $page
                ->has('cashflowTrend.data', $buckets)
            )
        );
})->with([
    'last 24 months' => ['last_24months', 24],
    'last 3 years' => ['last_3years', 36],
    'last 5 years' => ['last_5years', 60],
]);

test('applies an explicit balance preset alongside other params', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('statistics.index', ['month' => '2026-03', 'balance' => 'last_24months']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($page) => $page
                ->where('monthlyCashflow.from', '2026-03-01')
                ->has('cashflowTrend.data', 6)
                ->has('balanceTrend.data', 24)
            )
        );
});

test('maps multi-year balance presets to rolling spans', function (string $balance, int $buckets): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('statistics.index', ['balance' => $balance]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($page) => $page
                ->has('balanceTrend.data', $buckets)
            )
        );
})->with([
    'last 3 years' => ['last_3years', 36],
    'last 5 years' => ['last_5years', 60],
]);

test('falls non-rolling balance presets back to the default span', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('statistics.index', ['balance' => 'this_year']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($page) => $page
                ->has('balanceTrend.data', 6)
            )
        );
});

test('defaults out-of-range spans instead of rejecting', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('statistics.index', ['trend' => 'not-a-preset', 'balance' => 'nope']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($page) => $page
                ->has('cashflowTrend.data', 6)
                ->has('balanceTrend.data', 6)
            )
        );
});

test('bounds the All Time trend at the earliest transaction', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'amount' => 50_000,
        'transaction_date' => '2024-03-05',
    ]);

    $this->actingAs($user)->get(route('statistics.index', ['trend' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($page) => $page
                ->where('cashflowTrend.from', '2024-03-01')
            )
        );
});

test('projects the current week to a full week spending run-rate', function (): void {
    $this->travelTo(new CarbonImmutable('2026-10-15'));

    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'amount' => 200_000,
        'transaction_date' => '2026-10-13',
    ]);

    $this->actingAs($user)->get(route('statistics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($page) => $page
                // 200_000 / 4 elapsed days × 7 = 350_000 exactly.
                ->where('weeklySpendingAverage', 350_000.0)
            )
        );
});
