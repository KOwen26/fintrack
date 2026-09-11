<?php

use App\Data\Transaction\TransactionListData;
use App\Data\Transaction\TransferData;
use App\Enums\TransactionFlow;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\TransferService;

function createAccountForUser(): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    return [$user, $account];
}

it('lists transactions for the authenticated user', function (): void {
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

it('stores an income transaction', function (): void {
    [$user, $account] = createAccountForUser();
    $category = Category::factory()->create();

    $this->actingAs($user)->post(route('transactions.store'), [
        'account_id' => $account->id,
        'type' => TransactionType::Income->value,
        'amount' => 5_000_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => $category->id,
        'description' => 'Salary',
    ])->assertRedirect(route('transactions.index'));

    expect(Transaction::where('account_id', $account->id)->where('type', TransactionType::Income->value)->exists())->toBeTrue();
});

it('stores an expense transaction', function (): void {
    [$user, $account] = createAccountForUser();
    $category = Category::factory()->create();

    $this->actingAs($user)->post(route('transactions.store'), [
        'account_id' => $account->id,
        'type' => TransactionType::Expense->value,
        'amount' => 150_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => $category->id,
        'description' => 'Groceries',
    ])->assertRedirect();

    expect(Transaction::where('account_id', $account->id)->where('type', TransactionType::Expense->value)->exists())->toBeTrue();
});

it('creates a transfer unit with member rows via POST /transfers', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('transfers.store'), [
        'account_id' => $sourceAccount->id,
        'destination_account_id' => $destAccount->id,
        'amount' => 1_000_000,
        'transaction_date' => now()->toDateString(),
        'description' => 'Savings move',
    ])->assertRedirect(route('transactions.index'));

    $transfer = Transfer::first();
    expect($transfer)->not->toBeNull();

    $rows = $transfer->transactions()->get();
    expect($rows)->toHaveCount(2);

    $outflow = $rows->first(fn ($row): bool => $row->flow === TransactionFlow::Outflow && $row->type === TransactionType::Transfer);
    $inflow = $rows->first(fn ($row): bool => $row->flow === TransactionFlow::Inflow && $row->type === TransactionType::Transfer);

    expect($outflow->account_id)->toBe($sourceAccount->id)
        ->and($inflow->account_id)->toBe($destAccount->id)
        ->and($outflow->transfer_id)->toBe($transfer->id)
        ->and($inflow->transfer_id)->toBe($transfer->id);
});

it('books a transfer fee row in the Admin Fees category via POST /transfers', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id]);

    $parent = Category::factory()->create(['name' => 'Finance']);
    $adminFees = Category::factory()->create(['name' => 'Admin Fees', 'parent_id' => $parent->id]);

    $this->actingAs($user)->post(route('transfers.store'), [
        'account_id' => $sourceAccount->id,
        'destination_account_id' => $destAccount->id,
        'amount' => 500_000,
        'transaction_date' => now()->toDateString(),
        'fee_amount' => 6_500,
    ])->assertRedirect();

    $transfer = Transfer::first();
    $feeRow = $transfer->transactions()->get()->firstWhere('type', TransactionType::Expense);

    expect($feeRow)->not->toBeNull()
        ->and($feeRow->account_id)->toBe($sourceAccount->id)
        ->and($feeRow->category_id)->toBe($adminFees->id)
        ->and($feeRow->description)->toBe('Transfer fee')
        ->and((float) $feeRow->amount)->toEqual(6_500.0)
        ->and((float) $transfer->fee_amount)->toEqual(6_500.0);
});

it('books a transfer fee as uncategorized when no Admin Fees category exists', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('transfers.store'), [
        'account_id' => $sourceAccount->id,
        'destination_account_id' => $destAccount->id,
        'amount' => 500_000,
        'transaction_date' => now()->toDateString(),
        'fee_amount' => 6_500,
    ])->assertRedirect();

    $feeRow = Transfer::first()->transactions()->get()->firstWhere('type', TransactionType::Expense);
    expect($feeRow->category_id)->toBeNull();
});

it('rejects the legacy transfer pseudo-type on POST /transactions', function (): void {
    [$user, $account] = createAccountForUser();

    $this->actingAs($user)->post(route('transactions.store'), [
        'account_id' => $account->id,
        'type' => 'transfer',
        'amount' => 1_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => Category::factory()->create()->id,
    ])->assertInvalid('type');
});

it('rejects direct unit-member edits with 422 on PUT /transactions', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id]);

    $transfer = resolve(TransferService::class)->create($user, new TransferData(
        account_id: $sourceAccount->id,
        destination_account_id: $destAccount->id,
        amount: 250_000,
        transaction_date: now()->toDateString(),
    ));
    $outflow = $transfer->transactions()->get()->first(fn ($row): bool => $row->flow === TransactionFlow::Outflow);

    $this->actingAs($user)->put(route('transactions.update', $outflow), [
        'account_id' => $sourceAccount->id,
        'type' => 'expense',
        'amount' => 250_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => Category::factory()->create()->id,
    ])->assertStatus(422);
});

it('edits a transfer unit via PUT /transfers keeping the id stable', function (): void {
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
    ])->assertRedirect();

    $fresh = $transfer->fresh();
    expect($fresh->id)->toBe($transfer->id)
        ->and((float) $fresh->amount)->toEqual(300_000.0)
        ->and($fresh->transactions()->get())->toHaveCount(3); // source + destination + fee
});

it('soft-deletes the whole unit (aggregate, member rows, fee) when any member is deleted', function (): void {
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
    $inflow = $transfer->transactions()->get()->first(fn ($row): bool => $row->flow === TransactionFlow::Inflow);

    $this->actingAs($user)->delete(route('transactions.destroy', $inflow))->assertRedirect();

    expect($transfer->fresh()->trashed())->toBeTrue()
        ->and(Transaction::where('transfer_id', $transfer->id)->count())->toBe(0)
        ->and(Transaction::withTrashed()->where('transfer_id', $transfer->id)->count())->toBe(3);
});

it('lists transactions for any authenticated user', function (): void {
    [$user] = createAccountForUser();

    $this->actingAs($user)->get(route('transactions.index'))
        ->assertOk();
});

it('soft-deletes a transaction', function (): void {
    [$user, $account] = createAccountForUser();
    $transaction = Transaction::factory()->create(['account_id' => $account->id, 'created_by' => $user->id]);

    $this->actingAs($user)->delete(route('transactions.destroy', $transaction))
        ->assertRedirect();

    expect(Transaction::withTrashed()->find($transaction->id))->not->toBeNull();
});

it('resolves destination_account_id for both sides of a transfer unit', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id]);

    $transfer = resolve(TransferService::class)->create($user, new TransferData(
        account_id: $sourceAccount->id,
        destination_account_id: $destAccount->id,
        amount: 250_000,
        transaction_date: now()->toDateString(),
    ));
    $rows = $transfer->transactions()->get();

    $outflow = $rows->first(fn ($row): bool => $row->flow === TransactionFlow::Outflow);
    $inflow = $rows->first(fn ($row): bool => $row->flow === TransactionFlow::Inflow);

    expect(TransactionListData::fromTransaction($outflow)->destination_account_id)->toBe($destAccount->id)
        ->and(TransactionListData::fromTransaction($inflow)->destination_account_id)->toBe($sourceAccount->id);
});

it('sets destination_account_id to null for non-transfer transactions', function (): void {
    [$user, $account] = createAccountForUser();
    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
    ]);

    expect(TransactionListData::fromTransaction($transaction)->destination_account_id)->toBeNull();
});
