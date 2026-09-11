<?php

use App\Data\Transaction\TransferData;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReportService;
use App\Services\TransferService;
use Illuminate\Cache\TaggableStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('excludes transfer rows from dashboard monthly summaries', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);
    $other = Account::factory()->create(['owner_id' => $user->id]);
    $today = now()->toDateString();

    Transaction::factory()->income()->create([
        'account_id' => $account->id, 'created_by' => $user->id,
        'amount' => 1_000_000, 'transaction_date' => $today,
    ]);
    Transaction::factory()->expense()->create([
        'account_id' => $account->id, 'created_by' => $user->id,
        'amount' => 200_000, 'transaction_date' => $today,
    ]);

    // Both unit rows land on user-owned accounts — old code counted these as income/expense.
    resolve(TransferService::class)->create($user, new TransferData(
        account_id: $account->id,
        destination_account_id: $other->id,
        amount: 500_000,
        transaction_date: $today,
    ));

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.monthly_income', 1_000_000.0)
            ->where('summary.monthly_expenses', 200_000.0));
});

it('excludes transfer rows from trend income and expense', function (): void {
    if (! Cache::getStore() instanceof TaggableStore) {
        $this->markTestSkipped('Cache store does not support tags — trend caching unavailable in this env.');
    }

    Cache::flush();

    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);
    $other = Account::factory()->create(['owner_id' => $user->id]);
    $today = now()->toDateString();

    Transaction::factory()->income()->create([
        'account_id' => $account->id, 'created_by' => $user->id,
        'amount' => 1_000_000, 'transaction_date' => $today,
    ]);
    Transaction::factory()->expense()->create([
        'account_id' => $account->id, 'created_by' => $user->id,
        'amount' => 200_000, 'transaction_date' => $today,
    ]);
    resolve(TransferService::class)->create($user, new TransferData(
        account_id: $account->id,
        destination_account_id: $other->id,
        amount: 500_000,
        transaction_date: $today,
    ));

    $report = resolve(ReportService::class)->trend($account, 1);

    expect($report->months[0]->income)->toEqual(1_000_000.0)
        ->and($report->months[0]->expense)->toEqual(200_000.0);
});
