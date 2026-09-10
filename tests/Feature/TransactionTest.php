<?php

use App\Data\Transaction\TransactionListData;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

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

it('creates a transfer with 2 rows sharing the same transfer_link_id', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'account_id' => $sourceAccount->id,
        'type' => 'transfer',
        'amount' => 1_000_000,
        'transaction_date' => now()->toDateString(),
        'destination_account_id' => $destAccount->id,
        'description' => 'Savings move',
    ])->assertRedirect();

    $linkId = Transaction::where('account_id', $sourceAccount->id)
        ->where('type', TransactionType::TransferOut->value)
        ->value('transfer_link_id');

    expect($linkId)->not->toBeNull();
    expect(Transaction::where('transfer_link_id', $linkId)->count())->toBe(2);
    expect(Transaction::where('transfer_link_id', $linkId)->where('account_id', $destAccount->id)->where('type', TransactionType::TransferIn->value)->exists())->toBeTrue();
});

it('books a transfer fee as an expense in the Admin Fees category', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id]);

    $parent = Category::factory()->create(['name' => 'Finance']);
    $adminFees = Category::factory()->create(['name' => 'Admin Fees', 'parent_id' => $parent->id]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'account_id' => $sourceAccount->id,
        'type' => 'transfer',
        'amount' => 500_000,
        'transaction_date' => now()->toDateString(),
        'destination_account_id' => $destAccount->id,
        'fee_amount' => 6_500,
    ])->assertRedirect();

    $linkId = Transaction::where('account_id', $sourceAccount->id)
        ->where('type', TransactionType::TransferOut->value)
        ->value('transfer_link_id');

    $feeRow = Transaction::where('transfer_link_id', $linkId)
        ->where('type', TransactionType::Expense->value)
        ->first();

    expect(Transaction::where('transfer_link_id', $linkId)->count())->toBe(3);
    expect($feeRow)->not->toBeNull();
    expect($feeRow->account_id)->toBe($sourceAccount->id);
    expect($feeRow->category_id)->toBe($adminFees->id);
    expect((float) $feeRow->amount)->toBe(6_500.0);
});

it('books a transfer fee as uncategorized when no Admin Fees category exists', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'account_id' => $sourceAccount->id,
        'type' => 'transfer',
        'amount' => 500_000,
        'transaction_date' => now()->toDateString(),
        'destination_account_id' => $destAccount->id,
        'fee_amount' => 6_500,
    ])->assertRedirect();

    $linkId = Transaction::where('account_id', $sourceAccount->id)
        ->where('type', TransactionType::TransferOut->value)
        ->value('transfer_link_id');

    $feeRow = Transaction::where('transfer_link_id', $linkId)
        ->where('type', TransactionType::Expense->value)
        ->first();

    expect($feeRow)->not->toBeNull();
    expect($feeRow->category_id)->toBeNull();
});

it('soft-deletes all transfer rows when one is deleted', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'account_id' => $sourceAccount->id,
        'type' => 'transfer',
        'amount' => 200_000,
        'transaction_date' => now()->toDateString(),
        'destination_account_id' => $destAccount->id,
    ]);

    $outflow = Transaction::where('account_id', $sourceAccount->id)
        ->where('type', TransactionType::TransferOut->value)
        ->first();

    $this->actingAs($user)->delete(route('transactions.destroy', $outflow))
        ->assertRedirect();

    expect(Transaction::where('transfer_link_id', $outflow->transfer_link_id)->count())->toBe(0);
    expect(Transaction::withTrashed()->where('transfer_link_id', $outflow->transfer_link_id)->count())->toBe(2);
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

it('resolves destination_account_id for both sides of a transfer pair', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'account_id' => $sourceAccount->id,
        'type' => 'transfer',
        'amount' => 250_000,
        'transaction_date' => now()->toDateString(),
        'destination_account_id' => $destAccount->id,
    ])->assertRedirect();

    $outflow = Transaction::where('type', TransactionType::TransferOut->value)->first();
    $inflow = Transaction::where('type', TransactionType::TransferIn->value)->first();

    expect(TransactionListData::fromTransaction($outflow)->destination_account_id)->toBe($destAccount->id);
    expect(TransactionListData::fromTransaction($inflow)->destination_account_id)->toBe($sourceAccount->id);
});

it('sets destination_account_id to null for non-transfer transactions', function (): void {
    [$user, $account] = createAccountForUser();
    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
    ]);

    expect(TransactionListData::fromTransaction($transaction)->destination_account_id)->toBeNull();
});
