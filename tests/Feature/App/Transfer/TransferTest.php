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

test('creates a transfer unit with member rows via POST /transfers', function (): void {
    [$user, $sourceAccount] = userWithAccount();
    $destAccount = Account::factory()->for($user, 'owner')->create();

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
    [$user, $sourceAccount] = userWithAccount();
    $destAccount = Account::factory()->for($user, 'owner')->create();

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
        ->and($feeRow->category_id)->toBe(Category::AdminBankFees)
        ->and($feeRow->description)->toBe('Transfer fee')
        ->and((float) $feeRow->amount)->toEqual(6_500.0)
        ->and((float) $transfer->fee_amount)->toEqual(6_500.0);
});

test('rejects the legacy transfer pseudo-type on POST /transactions', function (): void {
    [$user, $account] = userWithAccount();

    $this->actingAs($user)->post(route('transactions.store'), [
        'account_id' => $account->id,
        'type' => 'transfer',
        'amount' => 1_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => Category::GeneralShopping->value,
    ])->assertInvalid('type');
});

test('rejects direct unit-member edits with 422 on PUT /transactions', function (): void {
    [$user, $sourceAccount] = userWithAccount();
    $destAccount = Account::factory()->for($user, 'owner')->create();

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
        'category_id' => Category::GeneralShopping->value,
    ])->assertUnprocessable();
});

test('edits a transfer unit via PUT /transfers keeping the id stable', function (): void {
    [$user, $sourceAccount] = userWithAccount();
    $destAccount = Account::factory()->for($user, 'owner')->create();

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
    [$user, $sourceAccount] = userWithAccount();
    $destAccount = Account::factory()->for($user, 'owner')->create();

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
    [$user] = userWithAccount();
    $otherUser = User::factory()->create();
    $source = Account::factory()->for($otherUser, 'owner')->create();
    $destination = Account::factory()->for($otherUser, 'owner')->create();

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
    [$user] = userWithAccount();
    $otherUser = User::factory()->create();
    $source = Account::factory()->for($otherUser, 'owner')->create();
    $destination = Account::factory()->for($otherUser, 'owner')->create();

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
    [$user, $sourceAccount] = userWithAccount();
    $destAccount = Account::factory()->for($user, 'owner')->create();

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
    [$user, $sourceAccount] = userWithAccount();
    $destAccount = Account::factory()->for($user, 'owner')->create();

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
