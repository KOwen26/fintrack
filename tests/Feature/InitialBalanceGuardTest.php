<?php

use App\Enums\Category;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function guardUser(): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id, 'initial_balance' => 500_000]);

    return [$user, $account];
}

function openingRowOf(Account $account): Transaction
{
    return Transaction::query()
        ->where('account_id', $account->id)
        ->where('category_id', Category::InitialBalance)
        ->first();
}

it('rejects booking an income row in the Initial Balance category', function (): void {
    [$user, $account] = guardUser();

    $this->actingAs($user)->post(route('transactions.store'), [
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 100_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => Category::InitialBalance->value,
    ])->assertSessionHasErrors('category_id');
});

it('redirects the edit page of an opening row to the account edit page', function (): void {
    [$user, $account] = guardUser();

    $this->actingAs($user)->get(route('transactions.edit', openingRowOf($account)))
        ->assertRedirect(route('accounts.edit', $account));
});

it('rejects updating an opening row via the transactions endpoint', function (): void {
    [$user, $account] = guardUser();
    $row = openingRowOf($account);

    $this->actingAs($user)->put(route('transactions.update', $row), [
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 100_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => 'salary',
    ])->assertStatus(422);
});

it('rejects deleting an opening row via the transactions endpoint', function (): void {
    [$user, $account] = guardUser();
    $row = openingRowOf($account);

    $this->actingAs($user)->delete(route('transactions.destroy', $row))
        ->assertStatus(422);

    expect($row->fresh())->not->toBeNull()
        ->and((float) $account->fresh()->current_balance)->toBe(500_000.0);
});
