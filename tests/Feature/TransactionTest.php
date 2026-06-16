<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createAccountForUser(): array
{
    $user = User::factory()->create();
    $household = Household::factory()->create(['created_by' => $user->id]);
    HouseholdMember::factory()->owner()->create([
        'household_id' => $household->id,
        'user_id' => $user->id,
    ]);

    $account = Account::factory()->create([
        'owner_id' => $user->id,
        'household_id' => $household->id,
    ]);

    return [$user, $account];
}

it('lists transactions for an account', function (): void {
    [$user, $account] = createAccountForUser();
    Transaction::factory()->count(3)->create(['account_id' => $account->id, 'created_by' => $user->id]);

    $this->actingAs($user)->get(route('transactions.index', $account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('transactions.data', 3)
        );
});

it('stores an income transaction', function (): void {
    [$user, $account] = createAccountForUser();

    $this->actingAs($user)->post(route('transactions.store', $account), [
        'type' => TransactionType::Income->value,
        'amount' => 5_000_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => null,
        'description' => 'Salary',
    ])->assertRedirect(route('transactions.index', $account));

    expect(Transaction::where('account_id', $account->id)->where('type', TransactionType::Income->value)->exists())->toBeTrue();
});

it('stores an expense transaction', function (): void {
    [$user, $account] = createAccountForUser();
    $category = Category::factory()->create();

    $this->actingAs($user)->post(route('transactions.store', $account), [
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
    $destAccount = Account::factory()->create(['owner_id' => $user->id, 'household_id' => $sourceAccount->household_id]);

    $this->actingAs($user)->post(route('transactions.store', $sourceAccount), [
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

it('creates a transfer with fee when fee_amount is provided', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id, 'household_id' => $sourceAccount->household_id]);

    $this->actingAs($user)->post(route('transactions.store', $sourceAccount), [
        'type' => 'transfer',
        'amount' => 500_000,
        'transaction_date' => now()->toDateString(),
        'destination_account_id' => $destAccount->id,
        'fee_amount' => 6_500,
    ])->assertRedirect();

    $linkId = Transaction::where('account_id', $sourceAccount->id)
        ->where('type', TransactionType::TransferOut->value)
        ->value('transfer_link_id');

    expect(Transaction::where('transfer_link_id', $linkId)->count())->toBe(3);
    expect(Transaction::where('transfer_link_id', $linkId)->where('type', TransactionType::Fee->value)->exists())->toBeTrue();
});

it('soft-deletes all transfer rows when one is deleted', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id, 'household_id' => $sourceAccount->household_id]);

    $this->actingAs($user)->post(route('transactions.store', $sourceAccount), [
        'type' => 'transfer',
        'amount' => 200_000,
        'transaction_date' => now()->toDateString(),
        'destination_account_id' => $destAccount->id,
    ]);

    $outflow = Transaction::where('account_id', $sourceAccount->id)
        ->where('type', TransactionType::TransferOut->value)
        ->first();

    $this->actingAs($user)->delete(route('transactions.destroy', [$sourceAccount, $outflow]))
        ->assertRedirect();

    expect(Transaction::where('transfer_link_id', $outflow->transfer_link_id)->count())->toBe(0);
    expect(Transaction::withTrashed()->where('transfer_link_id', $outflow->transfer_link_id)->count())->toBe(2);
});

it('prevents viewing transactions for another user account', function (): void {
    [$user] = createAccountForUser();
    $otherAccount = Account::factory()->create();

    $this->actingAs($user)->get(route('transactions.index', $otherAccount))
        ->assertForbidden();
});

it('soft-deletes a transaction', function (): void {
    [$user, $account] = createAccountForUser();
    $transaction = Transaction::factory()->create(['account_id' => $account->id, 'created_by' => $user->id]);

    $this->actingAs($user)->delete(route('transactions.destroy', [$account, $transaction]))
        ->assertRedirect();

    expect(Transaction::withTrashed()->find($transaction->id))->not->toBeNull();
});
