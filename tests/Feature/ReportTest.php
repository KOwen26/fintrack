<?php

use App\Data\Report\CategoryLeakReportData;
use App\Data\Report\ContributionSplitData;
use App\Data\Report\CreditUtilizationData;
use App\Data\Report\FixedVariableData;
use App\Data\Report\TrendReportData;
use App\Enums\AccountAccessType;
use App\Enums\AccountType;
use App\Enums\AlertLevel;
use App\Events\TransactionSaved;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// ReportsController HTTP tests
// ---------------------------------------------------------------------------

it('reports index renders for account owner', function (): void {
    [$user, , $account] = createUserWithAccountAndHousehold();

    $this->actingAs($user)
        ->get(route('reports.index', $account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->has('account')
            ->has('trend')
            ->has('category_leak')
        );
});

it('reports index is forbidden for non-member on personal account', function (): void {
    [, , $account] = createUserWithAccountAndHousehold(['access_type' => AccountAccessType::Personal->value]);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('reports.index', $account))
        ->assertForbidden();
});

it('trend endpoint returns trend data', function (): void {
    [$user, , $account] = createUserWithAccountAndHousehold();

    $this->actingAs($user)
        ->get(route('reports.trend', $account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/trend')
            ->has('trend')
            ->where('months', 6)
        );
});

it('credit utilization endpoint is only accessible for credit card accounts', function (): void {
    [$user, , $debitAccount] = createUserWithAccountAndHousehold(['type' => AccountType::DebitAccount->value]);

    $this->actingAs($user)
        ->get(route('reports.credit-utilization', $debitAccount))
        ->assertStatus(422);
});

it('credit utilization endpoint renders for credit card accounts', function (): void {
    [$user, , $account] = createUserWithAccountAndHousehold([
        'type' => AccountType::CreditCard->value,
        'credit_card_limit' => 10_000_000,
        'initial_balance' => 10_000_000,
    ]);

    $this->actingAs($user)
        ->get(route('reports.credit-utilization', $account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/credit-utilization')
            ->has('credit_utilization')
        );
});

it('contribution split returns is_joint: false for personal accounts', function (): void {
    [$user, , $account] = createUserWithAccountAndHousehold(['access_type' => AccountAccessType::Personal->value]);

    $this->actingAs($user)
        ->get(route('reports.contribution-split', $account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('contribution_split.is_joint', false)
        );
});

it('fixed vs variable endpoint renders', function (): void {
    [$user, , $account] = createUserWithAccountAndHousehold();

    $this->actingAs($user)
        ->get(route('reports.fixed-vs-variable', $account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/fixed-vs-variable')
            ->has('fixed_vs_variable')
        );
});

// ---------------------------------------------------------------------------
// ReportService unit-style tests (use service directly with real DB)
// ---------------------------------------------------------------------------

it('trend report aggregates income and expense correctly', function (): void {
    [$user, , $account] = createUserWithAccountAndHousehold();
    $category = Category::factory()->create(['user_id' => $user->id]);

    Transaction::factory()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'category_id' => $category->id,
        'type' => 'income',
        'amount' => 5_000_000,
        'transaction_date' => Date::now()->startOfMonth(),
    ]);

    Transaction::factory()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'category_id' => $category->id,
        'type' => 'expense',
        'amount' => 2_000_000,
        'transaction_date' => Date::now()->startOfMonth(),
    ]);

    $service = resolve(ReportService::class);
    $result = $service->trend($account, 1);

    expect($result)->toBeInstanceOf(TrendReportData::class);
    expect($result->months)->toHaveCount(1);

    $month = $result->months[0];
    expect($month->income)->toBe(5_000_000.0);
    expect($month->expense)->toBe(2_000_000.0);
    expect($month->net)->toBe(3_000_000.0);
    expect($month->surplus_rate)->toBe(60.0);
});

it('category leak report returns ranked categories by spend', function (): void {
    [$user, , $account] = createUserWithAccountAndHousehold();
    $catA = Category::factory()->create(['user_id' => $user->id, 'name' => 'Food']);
    $catB = Category::factory()->create(['user_id' => $user->id, 'name' => 'Transport']);

    Transaction::factory()->create([
        'account_id' => $account->id, 'created_by' => $user->id,
        'category_id' => $catA->id, 'type' => 'expense',
        'amount' => 3_000_000, 'transaction_date' => Date::now()->startOfMonth(),
    ]);
    Transaction::factory()->create([
        'account_id' => $account->id, 'created_by' => $user->id,
        'category_id' => $catB->id, 'type' => 'expense',
        'amount' => 1_000_000, 'transaction_date' => Date::now()->startOfMonth(),
    ]);

    $service = resolve(ReportService::class);
    $result = $service->categorySpending($account, Date::now()->startOfMonth(), Date::now()->endOfMonth());

    expect($result)->toBeInstanceOf(CategoryLeakReportData::class);
    expect($result->categories)->toHaveCount(2);
    expect($result->categories[0]->name)->toBe('Food');
    expect($result->categories[0]->percentage)->toBe(75.0);
    expect($result->period_total)->toBe(4_000_000.0);
});

it('contribution split returns empty state for personal account without error', function (): void {
    [$user, , $account] = createUserWithAccountAndHousehold(['access_type' => AccountAccessType::Personal->value]);

    $service = resolve(ReportService::class);
    $result = $service->contributionSplit($account, Date::now()->startOfMonth(), Date::now()->endOfMonth());

    expect($result)->toBeInstanceOf(ContributionSplitData::class);
    expect($result->is_joint)->toBeFalse();
    expect($result->members)->toBeEmpty();
});

it('contribution split returns member shares for joint accounts', function (): void {
    $owner = User::factory()->create();
    $partner = User::factory()->create();

    $account = Account::factory()->create(['owner_id' => $owner->id, 'access_type' => AccountAccessType::Joint->value]);
    $category = Category::factory()->create(['user_id' => $owner->id]);

    Transaction::factory()->create([
        'account_id' => $account->id, 'created_by' => $owner->id,
        'category_id' => $category->id, 'type' => 'income',
        'amount' => 6_000_000, 'transaction_date' => Date::now()->startOfMonth(),
    ]);
    Transaction::factory()->create([
        'account_id' => $account->id, 'created_by' => $partner->id,
        'category_id' => $category->id, 'type' => 'income',
        'amount' => 4_000_000, 'transaction_date' => Date::now()->startOfMonth(),
    ]);

    $service = resolve(ReportService::class);
    $result = $service->contributionSplit($account, Date::now()->startOfMonth(), Date::now()->endOfMonth());

    expect($result->is_joint)->toBeTrue();
    expect($result->members)->toHaveCount(2);
    expect($result->total)->toBe(10_000_000.0);
    // Owner contributed 60%
    $ownerRow = collect($result->members)->firstWhere('name', $owner->name);
    expect($ownerRow?->percentage)->toBe(60.0);
});

it('credit utilization calculates alert levels correctly', function (): void {
    [$user, , $account] = createUserWithAccountAndHousehold([
        'type' => AccountType::CreditCard->value,
        'credit_card_limit' => 10_000_000,
        'initial_balance' => 10_000_000,
    ]);
    $category = Category::factory()->create(['user_id' => $user->id]);

    // Spend 8M → 80% utilization → high_risk
    Transaction::factory()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'category_id' => $category->id,
        'type' => 'expense',
        'amount' => 8_000_000,
        'transaction_date' => Date::now()->startOfMonth(),
    ]);

    $service = resolve(ReportService::class);
    $result = $service->creditUtilization($account);

    expect($result)->toBeInstanceOf(CreditUtilizationData::class);
    expect($result->utilization_pct)->toBe(80.0);
    expect($result->alert_level)->toBe(AlertLevel::HighRisk);
});

it('fixed vs variable splits expenses by is_fixed_cost', function (): void {
    [$user, , $account] = createUserWithAccountAndHousehold();
    $fixed = Category::factory()->fixed()->create(['user_id' => $user->id]);
    $variable = Category::factory()->create(['user_id' => $user->id]); // is_fixed_cost = false

    Transaction::factory()->create([
        'account_id' => $account->id, 'created_by' => $user->id,
        'category_id' => $fixed->id, 'type' => 'expense',
        'amount' => 3_000_000, 'transaction_date' => Date::now()->startOfMonth(),
    ]);
    Transaction::factory()->create([
        'account_id' => $account->id, 'created_by' => $user->id,
        'category_id' => $variable->id, 'type' => 'expense',
        'amount' => 1_000_000, 'transaction_date' => Date::now()->startOfMonth(),
    ]);

    $service = resolve(ReportService::class);
    $result = $service->fixedVsVariable($account, Date::now()->startOfMonth(), Date::now()->endOfMonth());

    expect($result)->toBeInstanceOf(FixedVariableData::class);
    expect($result->fixed_total)->toBe(3_000_000.0);
    expect($result->variable_total)->toBe(1_000_000.0);
    expect($result->fixed_pct)->toBe(75.0);
    expect($result->variable_pct)->toBe(25.0);
});

// ---------------------------------------------------------------------------
// Cache invalidation tests
// ---------------------------------------------------------------------------

it('InvalidateAccountReportCache listener flushes report cache tags on TransactionSaved', function (): void {
    [$user, , $account] = createUserWithAccountAndHousehold();

    // Warm the cache with a dummy value
    Cache::tags(['account:' . $account->id])->put(
        "reports:{$account->id}:trend:2026:06",
        'cached_value',
        60
    );

    expect(Cache::tags(['account:' . $account->id])->get("reports:{$account->id}:trend:2026:06"))
        ->toBe('cached_value');

    $category = Category::factory()->create(['user_id' => $user->id]);
    $transaction = Transaction::factory()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'category_id' => $category->id,
        'type' => 'expense',
        'amount' => 500_000,
    ]);

    // Fire event (Ledger spec fires this on transaction save)
    TransactionSaved::dispatch($transaction);

    expect(Cache::tags(['account:' . $account->id])->get("reports:{$account->id}:trend:2026:06"))
        ->toBeNull();
});
