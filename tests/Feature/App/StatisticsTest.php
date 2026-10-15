<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

test('renders the statistics page with monthly flow props', function (): void {
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
            ->has('monthlyFlow')
            ->has('monthlyFlow.data', 1)
        );
});

test('renders the statistics page for a user without accounts', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('statistics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/statistics/index')
            ->has('monthlyFlow')
            ->has('monthlyFlow.data', 0)
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
            ->where('monthlyFlow.from', '2026-03-01')
            ->has('monthlyFlow.data', 1)
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
            ->where('monthlyFlow.from', '2026-03-01')
            ->where('monthlyFlow.to', '2026-03-31')
            ->has('monthlyFlow.data', 1)
        );
});

test('falls back to the current month for an unparseable month', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('statistics.index', ['month' => 'garbage']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/statistics/index')
            ->where('monthlyFlow.from', now()->startOfMonth()->toDateString())
        );
});
