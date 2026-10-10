<?php

use App\Enums\Category;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function openingRow(Account $account): ?Transaction
{
    return Transaction::query()
        ->where('account_id', $account->id)
        ->where('category_id', Category::InitialBalance)
        ->first();
}

it('books an opening income row and sets current_balance on create', function (): void {
    $account = Account::factory()->create(['initial_balance' => 1_000_000]);

    $row = openingRow($account);

    expect($row)->not->toBeNull()
        ->and($row->type->value)->toBe('income')
        ->and($row->flow->value)->toBe('inflow')
        ->and((float) $row->amount)->toBe(1_000_000.0)
        ->and($row->description)->toBe('Initial balance')
        ->and($row->transaction_date->toDateString())->toBe($account->created_at->toDateString())
        ->and((float) $account->fresh()->current_balance)->toBe(1_000_000.0);
});

it('books nothing for a zero initial balance', function (): void {
    $account = Account::factory()->create(['initial_balance' => 0]);

    expect(openingRow($account))->toBeNull()
        ->and((float) $account->fresh()->current_balance)->toBe(0.0);
});

it('updates the opening row in place when the initial balance is edited', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id, 'initial_balance' => 500_000]);
    $rowId = openingRow($account)->id;

    resolve(AccountService::class)->update($account, ['initial_balance' => 1_200_000]);

    $row = openingRow($account);

    expect($row->id)->toBe($rowId)
        ->and((float) $row->amount)->toBe(1_200_000.0)
        ->and((float) $account->fresh()->current_balance)->toBe(1_200_000.0)
        ->and($row->transaction_date->toDateString())->toBe($account->created_at->toDateString());
});

it('soft-deletes the opening row when the initial balance is set to zero', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id, 'initial_balance' => 500_000]);

    resolve(AccountService::class)->update($account, ['initial_balance' => 0]);

    expect(openingRow($account))->toBeNull()
        ->and((float) $account->fresh()->current_balance)->toBe(0.0);
});

it('keeps exactly one live row through a zero-X-zero-X cycle', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id, 'initial_balance' => 0]);
    $service = resolve(AccountService::class);

    $service->update($account, ['initial_balance' => 300_000]);
    $service->update($account, ['initial_balance' => 0]);
    $service->update($account, ['initial_balance' => 450_000]);

    expect(Transaction::withTrashed()
        ->where('account_id', $account->id)
        ->where('category_id', Category::InitialBalance)
        ->count())->toBe(2)
        ->and(openingRow($account))->not->toBeNull()
        ->and((float) $account->fresh()->current_balance)->toBe(450_000.0);
});

it('re-creates the row when it was manually deleted and the balance is edited', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id, 'initial_balance' => 500_000]);
    openingRow($account)->delete();

    resolve(AccountService::class)->update($account, ['initial_balance' => 800_000]);

    expect(openingRow($account))->not->toBeNull()
        ->and((float) $account->fresh()->current_balance)->toBe(800_000.0);
});

it('scopes the opening row to its own account in a multi-account setup', function (): void {
    $user = User::factory()->create();
    $accountA = Account::factory()->create(['owner_id' => $user->id, 'initial_balance' => 100_000]);
    $accountB = Account::factory()->create(['owner_id' => $user->id, 'initial_balance' => 0]);

    resolve(AccountService::class)->update($accountB, ['initial_balance' => 700_000]);

    expect((float) openingRow($accountA)->amount)->toBe(100_000.0)
        ->and((float) $accountA->fresh()->current_balance)->toBe(100_000.0)
        ->and((float) openingRow($accountB)->amount)->toBe(700_000.0);
});
