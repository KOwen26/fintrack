<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

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
                ->has('balanceTrend.data', 12)
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
                ->has('balanceTrend.data', 12)
            )
        );
});

test('applies an explicit balance span alongside other params', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('statistics.index', ['month' => '2026-03', 'balance' => 24]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($page) => $page
                ->where('monthlyCashflow.from', '2026-03-01')
                ->has('cashflowTrend.data', 6)
                ->has('balanceTrend.data', 24)
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
                ->has('balanceTrend.data', 12)
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
