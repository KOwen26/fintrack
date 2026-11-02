<?php

use App\Enums\Category;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

function openingRowOf(Account $account): Transaction
{
    return Transaction::query()
        ->where('account_id', $account->id)
        ->where('category_id', Category::InitialBalance)
        ->first();
}

test('lists transactions for the authenticated user', function (): void {
    [$user, $account] = userWithAccount();
    Transaction::factory()->recent()->count(3)->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
    ]);

    // Another user's transaction must not leak into the list.
    Transaction::factory()->create(['created_by' => User::factory()->create()->id]);

    $this->actingAs($user)->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('transactions')
            ->loadDeferredProps(fn ($page) => $page
                ->has('transactions', 3)
            )
        );
});

test('renders the transaction creation page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('transactions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/transaction/create')
            ->has('accounts')
            ->has('transaction')
            ->where('transaction.type', TransactionType::Expense->value)
            ->where('transaction.account_id', null)
        );
});

test('seeds the creation page from query type and account', function (): void {
    [$user, $account] = userWithAccount();

    $this->actingAs($user)
        ->get(route('transactions.create', [
            'type' => TransactionType::Transfer->value,
            'account_id' => $account->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/transaction/create')
            ->where('transaction.type', TransactionType::Transfer->value)
            ->where('transaction.account_id', $account->id)
        );
});

test('stores an income transaction', function (): void {
    [$user, $account] = userWithAccount();

    $this->actingAs($user)->post(route('transactions.store'), [
        'account_id' => $account->id,
        'type' => TransactionType::Income->value,
        'amount' => 5_000_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => Category::Salary->value,
        'description' => 'Salary',
    ])->assertRedirectToRoute('dashboard');

    expect(Transaction::where('account_id', $account->id)->where('type', TransactionType::Income->value)->exists())->toBeTrue();
});

test('stores an expense transaction', function (): void {
    [$user, $account] = userWithAccount();

    $this->actingAs($user)->post(route('transactions.store'), [
        'account_id' => $account->id,
        'type' => TransactionType::Expense->value,
        'amount' => 150_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => Category::GeneralShopping->value,
        'description' => 'Groceries',
    ])->assertRedirectToRoute('dashboard');

    expect(Transaction::where('account_id', $account->id)->where('type', TransactionType::Expense->value)->exists())->toBeTrue();
});

test('updates a transaction and redirects to the list', function (): void {
    [$user, $account] = userWithAccount();
    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 100_000,
    ]);

    $this->actingAs($user)->put(route('transactions.update', $transaction), [
        'account_id' => $account->id,
        'type' => TransactionType::Expense->value,
        'amount' => 150_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => Category::GeneralShopping->value,
        'description' => 'Updated groceries',
    ])->assertRedirectToRoute('transactions.index');

    expect((float) $transaction->fresh()->amount)->toBe(150_000.0)
        ->and($transaction->fresh()->description)->toBe('Updated groceries')
        ->and((float) $account->fresh()->current_balance)->toBe(-150_000.0);
});

test('renders the edit page for its owner', function (): void {
    [$user, $account] = userWithAccount();
    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)->get(route('transactions.edit', $transaction))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/transaction/edit')
            ->has('accounts')
            ->has('transaction')
        );
});

test('shows a transaction to its creator', function (): void {
    [$user, $account] = userWithAccount();
    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/transaction/show')
            ->has('transaction')
        );
});

test('forbids viewing a transaction created by another user', function (): void {
    [$user] = userWithAccount();
    $otherTransaction = Transaction::factory()->expense()->create(['amount' => 200_000]); // another user's personal account

    $this->actingAs($user)->get(route('transactions.show', $otherTransaction))
        ->assertForbidden();
});

test('shows a transaction on a shareable account to any authenticated user', function (): void {
    [$user] = userWithAccount();
    $shareableAccount = Account::factory()->shareable()->create(); // another owner, shared
    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $shareableAccount->id,
        'created_by' => $shareableAccount->owner_id,
    ]);

    $this->actingAs($user)->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('app/transaction/show'));
});

test('lists transactions for any authenticated user', function (): void {
    [$user] = userWithAccount();

    $this->actingAs($user)->get(route('transactions.index'))
        ->assertOk();
});

test('soft-deletes a transaction', function (): void {
    [$user, $account] = userWithAccount();
    $transaction = Transaction::factory()->create(['account_id' => $account->id, 'created_by' => $user->id]);

    $this->actingAs($user)->delete(route('transactions.destroy', $transaction))
        ->assertRedirectToRoute('transactions.index');

    expect(Transaction::withTrashed()->find($transaction->id))->not->toBeNull();
});

test('forbids updating a transaction created by another user', function (): void {
    [$user] = userWithAccount();
    $otherTransaction = Transaction::factory()->expense()->create(['amount' => 200_000]); // created by another user

    $this->actingAs($user)->put(route('transactions.update', $otherTransaction), [
        'account_id' => $otherTransaction->account_id,
        'type' => TransactionType::Expense->value,
        'amount' => 150_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => Category::GeneralShopping->value,
    ])->assertForbidden();

    expect((float) $otherTransaction->fresh()->amount)->toBe(200_000.0);
});

test('forbids deleting a transaction created by another user', function (): void {
    [$user] = userWithAccount();
    $otherTransaction = Transaction::factory()->expense()->create(['amount' => 200_000]); // created by another user

    $this->actingAs($user)->delete(route('transactions.destroy', $otherTransaction))
        ->assertForbidden();

    expect($otherTransaction->fresh())->not->toBeNull();
});

describe('initial balance guard', function (): void {
    test('rejects booking an income row in the Initial Balance category', function (): void {
        [$user, $account] = userWithAccount(['initial_balance' => 500_000]);

        $this->actingAs($user)->post(route('transactions.store'), [
            'account_id' => $account->id,
            'type' => 'income',
            'amount' => 100_000,
            'transaction_date' => now()->toDateString(),
            'category_id' => Category::InitialBalance->value,
        ])->assertSessionHasErrors('category_id');
    });

    test('redirects the edit page of an opening row to the account edit page', function (): void {
        [$user, $account] = userWithAccount(['initial_balance' => 500_000]);

        $this->actingAs($user)->get(route('transactions.edit', openingRowOf($account)))
            ->assertRedirectToRoute('accounts.edit', ['account' => $account]);
    });

    test('rejects updating an opening row via the transactions endpoint', function (): void {
        [$user, $account] = userWithAccount(['initial_balance' => 500_000]);
        $row = openingRowOf($account);

        $this->actingAs($user)->put(route('transactions.update', $row), [
            'account_id' => $account->id,
            'type' => 'income',
            'amount' => 100_000,
            'transaction_date' => now()->toDateString(),
            'category_id' => Category::Salary->value,
        ])->assertUnprocessable();
    });

    test('rejects deleting an opening row via the transactions endpoint', function (): void {
        [$user, $account] = userWithAccount(['initial_balance' => 500_000]);
        $row = openingRowOf($account);

        $this->actingAs($user)->delete(route('transactions.destroy', $row))
            ->assertUnprocessable();

        expect($row->fresh())->not->toBeNull()
            ->and((float) $account->fresh()->current_balance)->toBe(500_000.0);
    });
});
