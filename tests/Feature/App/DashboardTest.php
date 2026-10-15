<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

test('renders the dashboard with overview props', function (): void {
    $user = User::factory()->create();
    Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/dashboard')
            ->has('accounts', 1)
            ->has('summary')
            ->has('categorySpending')
            ->has('recentTransactions')
        );
});

test('renders the welcome state for a user without accounts', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/dashboard')
            ->has('accounts', 0)
            ->has('summary')
            ->has('categorySpending')
            ->has('recentTransactions')
        );
});

test('feeds created transactions into recent transactions', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 150_000,
        'transaction_date' => now()->format('Y-m-d'),
    ]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/dashboard')
            ->has('accounts', 1)
            ->has('recentTransactions', 1)
            // JSON round-trip serializes -150000.0 as the int -150000
            ->has('summary', fn ($summary) => $summary->where('current_balance', -150_000)->etc())
        );
});
