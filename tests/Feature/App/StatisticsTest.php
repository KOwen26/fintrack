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
