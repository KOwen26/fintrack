<?php

use App\Data\Transaction\TransferData;
use App\Enums\Cashflow;
use App\Enums\Category;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\TransferService;

function createAccountForUser(array $attributes = []): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id, ...$attributes]);

    return [$user, $account];
}

function openingRowOf(Account $account): Transaction
{
    return Transaction::query()
        ->where('account_id', $account->id)
        ->where('category_id', Category::InitialBalance)
        ->first();
}

test('lists transactions for the authenticated user', function (): void {
    [$user, $account] = createAccountForUser();
    Transaction::factory()->count(3)->create(['account_id' => $account->id, 'created_by' => $user->id]);

    // Another user's transaction must not leak into the list.
    Transaction::factory()->create(['created_by' => User::factory()->create()->id]);

    $this->actingAs($user)->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('transactions', 3)
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
    [$user, $account] = createAccountForUser();

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
    [$user, $account] = createAccountForUser();

    $this->actingAs($user)->post(route('transactions.store'), [
        'account_id' => $account->id,
        'type' => TransactionType::Income->value,
        'amount' => 5_000_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => 'salary',
        'description' => 'Salary',
    ])->assertRedirectToRoute('transactions.index');

    expect(Transaction::where('account_id', $account->id)->where('type', TransactionType::Income->value)->exists())->toBeTrue();
});

test('stores an expense transaction', function (): void {
    [$user, $account] = createAccountForUser();

    $this->actingAs($user)->post(route('transactions.store'), [
        'account_id' => $account->id,
        'type' => TransactionType::Expense->value,
        'amount' => 150_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => 'groceries',
        'description' => 'Groceries',
    ])->assertRedirectToRoute('transactions.index');

    expect(Transaction::where('account_id', $account->id)->where('type', TransactionType::Expense->value)->exists())->toBeTrue();
});

test('updates a transaction and redirects to the list', function (): void {
    [$user, $account] = createAccountForUser();
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
        'category_id' => 'groceries',
        'description' => 'Updated groceries',
    ])->assertRedirectToRoute('transactions.index');

    expect((float) $transaction->fresh()->amount)->toBe(150_000.0)
        ->and($transaction->fresh()->description)->toBe('Updated groceries')
        ->and((float) $account->fresh()->current_balance)->toBe(-150_000.0);
});

test('renders the edit page for its owner', function (): void {
    [$user, $account] = createAccountForUser();
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
    [$user, $account] = createAccountForUser();
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
    [$user] = createAccountForUser();
    $otherTransaction = Transaction::factory()->expense()->create(['amount' => 200_000]); // another user's personal account

    $this->actingAs($user)->get(route('transactions.show', $otherTransaction))
        ->assertForbidden();
});

test('shows a transaction on a shareable account to any authenticated user', function (): void {
    [$user] = createAccountForUser();
    $shareableAccount = Account::factory()->shareable()->create(); // another owner, shared
    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $shareableAccount->id,
        'created_by' => $shareableAccount->owner_id,
    ]);

    $this->actingAs($user)->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('app/transaction/show'));
});

test('creates a transfer unit with member rows via POST /transfers', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('transfers.store'), [
        'account_id' => $sourceAccount->id,
        'destination_account_id' => $destAccount->id,
        'amount' => 1_000_000,
        'transaction_date' => now()->toDateString(),
        'description' => 'Savings move',
    ])->assertRedirectToRoute('transactions.index');

    $transfer = Transfer::first();
    expect($transfer)->not->toBeNull();

    $rows = $transfer->transactions()->get();
    expect($rows)->toHaveCount(2);

    $outflow = $rows->first(fn ($row): bool => $row->flow === Cashflow::Outflow && $row->type === TransactionType::Transfer);
    $inflow = $rows->first(fn ($row): bool => $row->flow === Cashflow::Inflow && $row->type === TransactionType::Transfer);

    expect($outflow->account_id)->toBe($sourceAccount->id)
        ->and($inflow->account_id)->toBe($destAccount->id)
        ->and($outflow->transfer_id)->toBe($transfer->id)
        ->and($inflow->transfer_id)->toBe($transfer->id);
});

test('books a transfer fee row in the Admin Fees category via POST /transfers', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('transfers.store'), [
        'account_id' => $sourceAccount->id,
        'destination_account_id' => $destAccount->id,
        'amount' => 500_000,
        'transaction_date' => now()->toDateString(),
        'fee_amount' => 6_500,
    ])->assertRedirectToRoute('transactions.index');

    $transfer = Transfer::first();
    $feeRow = $transfer->transactions()->get()->firstWhere('type', TransactionType::Expense);

    expect($feeRow)->not->toBeNull()
        ->and($feeRow->account_id)->toBe($sourceAccount->id)
        ->and($feeRow->category_id)->toBe(Category::AdminFees)
        ->and($feeRow->description)->toBe('Transfer fee')
        ->and((float) $feeRow->amount)->toEqual(6_500.0)
        ->and((float) $transfer->fee_amount)->toEqual(6_500.0);
});

test('rejects the legacy transfer pseudo-type on POST /transactions', function (): void {
    [$user, $account] = createAccountForUser();

    $this->actingAs($user)->post(route('transactions.store'), [
        'account_id' => $account->id,
        'type' => 'transfer',
        'amount' => 1_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => 'groceries',
    ])->assertInvalid('type');
});

test('rejects direct unit-member edits with 422 on PUT /transactions', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id]);

    $transfer = resolve(TransferService::class)->create($user, new TransferData(
        account_id: $sourceAccount->id,
        destination_account_id: $destAccount->id,
        amount: 250_000,
        transaction_date: now()->toDateString(),
    ));
    $outflow = $transfer->transactions()->get()->first(fn ($row): bool => $row->flow === Cashflow::Outflow);

    $this->actingAs($user)->put(route('transactions.update', $outflow), [
        'account_id' => $sourceAccount->id,
        'type' => 'expense',
        'amount' => 250_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => 'groceries',
    ])->assertUnprocessable();
});

test('edits a transfer unit via PUT /transfers keeping the id stable', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('transfers.store'), [
        'account_id' => $sourceAccount->id,
        'destination_account_id' => $destAccount->id,
        'amount' => 200_000,
        'transaction_date' => now()->toDateString(),
    ])->assertRedirect();

    $transfer = Transfer::first();

    $this->actingAs($user)->put(route('transfers.update', $transfer), [
        'account_id' => $sourceAccount->id,
        'destination_account_id' => $destAccount->id,
        'amount' => 300_000,
        'transaction_date' => now()->toDateString(),
        'fee_amount' => 5_000,
    ])->assertRedirectToRoute('transactions.index');

    $fresh = $transfer->fresh();
    expect($fresh->id)->toBe($transfer->id)
        ->and((float) $fresh->amount)->toEqual(300_000.0)
        ->and($fresh->transactions()->get())->toHaveCount(3); // source + destination + fee
});

test('renders the transfer edit page for its creator', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id]);

    $transfer = resolve(TransferService::class)->create($user, new TransferData(
        account_id: $sourceAccount->id,
        destination_account_id: $destAccount->id,
        amount: 250_000,
        transaction_date: now()->toDateString(),
    ));

    $this->actingAs($user)->get(route('transfers.edit', $transfer))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/transaction/edit')
            ->has('accounts')
            ->has('transaction')
        );
});

test('forbids editing a transfer created by another user', function (): void {
    [$user] = createAccountForUser();
    $otherUser = User::factory()->create();
    $source = Account::factory()->create(['owner_id' => $otherUser->id]);
    $destination = Account::factory()->create(['owner_id' => $otherUser->id]);

    $transfer = resolve(TransferService::class)->create($otherUser, new TransferData(
        account_id: $source->id,
        destination_account_id: $destination->id,
        amount: 250_000,
        transaction_date: now()->toDateString(),
    ));

    $this->actingAs($user)->get(route('transfers.edit', $transfer))
        ->assertForbidden();
});

test('forbids updating a transfer created by another user', function (): void {
    [$user] = createAccountForUser();
    $otherUser = User::factory()->create();
    $source = Account::factory()->create(['owner_id' => $otherUser->id]);
    $destination = Account::factory()->create(['owner_id' => $otherUser->id]);

    $transfer = resolve(TransferService::class)->create($otherUser, new TransferData(
        account_id: $source->id,
        destination_account_id: $destination->id,
        amount: 250_000,
        transaction_date: now()->toDateString(),
    ));

    $this->actingAs($user)->put(route('transfers.update', $transfer), [
        'account_id' => $source->id,
        'destination_account_id' => $destination->id,
        'amount' => 999_999,
        'transaction_date' => now()->toDateString(),
    ])->assertForbidden();

    expect((float) $transfer->fresh()->amount)->toBe(250_000.0);
});

test('redirects a transfer member edit to the transfer edit page', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id]);

    $transfer = resolve(TransferService::class)->create($user, new TransferData(
        account_id: $sourceAccount->id,
        destination_account_id: $destAccount->id,
        amount: 250_000,
        transaction_date: now()->toDateString(),
    ));
    $outflow = $transfer->transactions()->get()->first(fn ($row): bool => $row->flow === Cashflow::Outflow);

    $this->actingAs($user)->get(route('transactions.edit', $outflow))
        ->assertRedirectToRoute('transfers.edit', ['transfer' => $transfer]);
});

test('soft-deletes the whole unit (aggregate, member rows, fee) when any member is deleted', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('transfers.store'), [
        'account_id' => $sourceAccount->id,
        'destination_account_id' => $destAccount->id,
        'amount' => 200_000,
        'transaction_date' => now()->toDateString(),
        'fee_amount' => 2_500,
    ])->assertRedirect();

    $transfer = Transfer::first();
    $inflow = $transfer->transactions()->get()->first(fn ($row): bool => $row->flow === Cashflow::Inflow);

    $this->actingAs($user)->delete(route('transactions.destroy', $inflow))->assertRedirectToRoute('transactions.index');

    expect($transfer->fresh()->trashed())->toBeTrue()
        ->and(Transaction::where('transfer_id', $transfer->id)->count())->toBe(0)
        ->and(Transaction::withTrashed()->where('transfer_id', $transfer->id)->count())->toBe(3);
});

test('lists transactions for any authenticated user', function (): void {
    [$user] = createAccountForUser();

    $this->actingAs($user)->get(route('transactions.index'))
        ->assertOk();
});

test('soft-deletes a transaction', function (): void {
    [$user, $account] = createAccountForUser();
    $transaction = Transaction::factory()->create(['account_id' => $account->id, 'created_by' => $user->id]);

    $this->actingAs($user)->delete(route('transactions.destroy', $transaction))
        ->assertRedirectToRoute('transactions.index');

    expect(Transaction::withTrashed()->find($transaction->id))->not->toBeNull();
});

test('forbids updating a transaction created by another user', function (): void {
    [$user] = createAccountForUser();
    $otherTransaction = Transaction::factory()->expense()->create(['amount' => 200_000]); // created by another user

    $this->actingAs($user)->put(route('transactions.update', $otherTransaction), [
        'account_id' => $otherTransaction->account_id,
        'type' => TransactionType::Expense->value,
        'amount' => 150_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => 'groceries',
    ])->assertForbidden();

    expect((float) $otherTransaction->fresh()->amount)->toBe(200_000.0);
});

test('forbids deleting a transaction created by another user', function (): void {
    [$user] = createAccountForUser();
    $otherTransaction = Transaction::factory()->expense()->create(['amount' => 200_000]); // created by another user

    $this->actingAs($user)->delete(route('transactions.destroy', $otherTransaction))
        ->assertForbidden();

    expect($otherTransaction->fresh())->not->toBeNull();
});

describe('initial balance guard', function (): void {
    test('rejects booking an income row in the Initial Balance category', function (): void {
        [$user, $account] = createAccountForUser(['initial_balance' => 500_000]);

        $this->actingAs($user)->post(route('transactions.store'), [
            'account_id' => $account->id,
            'type' => 'income',
            'amount' => 100_000,
            'transaction_date' => now()->toDateString(),
            'category_id' => Category::InitialBalance->value,
        ])->assertSessionHasErrors('category_id');
    });

    test('redirects the edit page of an opening row to the account edit page', function (): void {
        [$user, $account] = createAccountForUser(['initial_balance' => 500_000]);

        $this->actingAs($user)->get(route('transactions.edit', openingRowOf($account)))
            ->assertRedirectToRoute('accounts.edit', ['account' => $account]);
    });

    test('rejects updating an opening row via the transactions endpoint', function (): void {
        [$user, $account] = createAccountForUser(['initial_balance' => 500_000]);
        $row = openingRowOf($account);

        $this->actingAs($user)->put(route('transactions.update', $row), [
            'account_id' => $account->id,
            'type' => 'income',
            'amount' => 100_000,
            'transaction_date' => now()->toDateString(),
            'category_id' => 'salary',
        ])->assertUnprocessable();
    });

    test('rejects deleting an opening row via the transactions endpoint', function (): void {
        [$user, $account] = createAccountForUser(['initial_balance' => 500_000]);
        $row = openingRowOf($account);

        $this->actingAs($user)->delete(route('transactions.destroy', $row))
            ->assertUnprocessable();

        expect($row->fresh())->not->toBeNull()
            ->and((float) $account->fresh()->current_balance)->toBe(500_000.0);
    });
});
