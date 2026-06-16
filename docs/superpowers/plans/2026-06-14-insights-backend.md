# Insights — Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the complete read-only analytics backend for FinTrack — enum, DTOs, ReportService with five cached SQL aggregate methods, ReportsController, routes, cache invalidation listener, and full feature tests.

**Architecture:** ReportService is the sole entry point for all report data — controllers call it and pass typed Data objects to Inertia. All five report methods compute results via a single SQL aggregate query each; no PHP collection reduction. Cache uses Redis (or database driver as fallback), tagged by `account:{id}` so a single `Cache::tags()->flush()` invalidates all reports for an account. Cache invalidation is handled by a new `InvalidateAccountReportCache` listener wired to the existing `TransactionSaved` and `TransactionDeleted` events.

**Tech Stack:** PHP 8.4, Laravel 13, Spatie Laravel Data, Pest 4, Inertia v3

**Depends on:** Foundation, Ledger, and Automation specs fully implemented.

---

## File Map

```
app/Enums/
  AlertLevel.php                         ← new

app/Data/
  Report/
    TrendMonthData.php                   ← single month row inside TrendReportData
    TrendReportData.php
    CategoryLeakItemData.php             ← single item inside CategoryLeakReportData
    CategoryLeakReportData.php
    ContributionMemberData.php           ← single member inside ContributionSplitData
    ContributionSplitData.php
    CreditUtilizationData.php
    FixedVariableData.php

app/Listeners/
  InvalidateAccountReportCache.php       ← new

app/Providers/
  EventServiceProvider.php              ← modify: register new listener

app/Services/
  ReportService.php                      ← new

app/Http/Controllers/
  ReportsController.php                  ← new

routes/web.php                           ← modify: add report routes

tests/Feature/
  ReportTest.php                         ← new
```

---

## Task 1: AlertLevel Enum

- [ ] **Create `app/Enums/AlertLevel.php`**

No artisan command for enums — create the file directly:

```php
<?php

namespace App\Enums;

enum AlertLevel: string
{
    case Normal   = 'normal';
    case Warning  = 'warning';
    case HighRisk = 'high_risk';
}
```

---

## Task 2: Data Objects (Spatie Laravel Data)

All five report DTOs represent computed, cross-model shapes — they are correct use cases for Spatie Data. After creating all DTOs, run `composer generate:ts` once at the end of this task.

- [ ] **Create `app/Data/Report/TrendMonthData.php`**

```php
<?php

namespace App\Data\Report;

use Spatie\LaravelData\Data;

class TrendMonthData extends Data
{
    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly float $income,
        public readonly float $expense,
        public readonly float $net,
        public readonly float $surplus_rate,
    ) {}
}
```

- [ ] **Create `app/Data/Report/TrendReportData.php`**

```php
<?php

namespace App\Data\Report;

use Spatie\LaravelData\Data;

class TrendReportData extends Data
{
    public function __construct(
        /** @var TrendMonthData[] */
        public readonly array $months,
        public readonly int $months_count,
    ) {}
}
```

- [ ] **Create `app/Data/Report/CategoryLeakItemData.php`**

```php
<?php

namespace App\Data\Report;

use Spatie\LaravelData\Data;

class CategoryLeakItemData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $color,
        public readonly string $icon,
        public readonly float $total,
        public readonly float $percentage,
    ) {}
}
```

- [ ] **Create `app/Data/Report/CategoryLeakReportData.php`**

```php
<?php

namespace App\Data\Report;

use Spatie\LaravelData\Data;

class CategoryLeakReportData extends Data
{
    public function __construct(
        /** @var CategoryLeakItemData[] */
        public readonly array $categories,
        public readonly float $period_total,
        public readonly string $from,
        public readonly string $to,
    ) {}
}
```

- [ ] **Create `app/Data/Report/ContributionMemberData.php`**

```php
<?php

namespace App\Data\Report;

use Spatie\LaravelData\Data;

class ContributionMemberData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly float $contributed,
        public readonly float $percentage,
    ) {}
}
```

- [ ] **Create `app/Data/Report/ContributionSplitData.php`**

```php
<?php

namespace App\Data\Report;

use Spatie\LaravelData\Data;

class ContributionSplitData extends Data
{
    public function __construct(
        public readonly bool $is_joint,
        /** @var ContributionMemberData[] */
        public readonly array $members,
        public readonly float $total,
        public readonly string $from,
        public readonly string $to,
    ) {}
}
```

- [ ] **Create `app/Data/Report/CreditUtilizationData.php`**

```php
<?php

namespace App\Data\Report;

use App\Enums\AlertLevel;
use Spatie\LaravelData\Data;

class CreditUtilizationData extends Data
{
    public function __construct(
        public readonly float $limit,
        public readonly float $used,
        public readonly float $available,
        public readonly float $utilization_pct,
        public readonly AlertLevel $alert_level,
    ) {}
}
```

- [ ] **Create `app/Data/Report/FixedVariableData.php`**

```php
<?php

namespace App\Data\Report;

use Spatie\LaravelData\Data;

class FixedVariableData extends Data
{
    public function __construct(
        public readonly float $fixed_total,
        public readonly float $variable_total,
        public readonly float $fixed_pct,
        public readonly float $variable_pct,
        public readonly float $safety_margin,
        public readonly string $from,
        public readonly string $to,
    ) {}
}
```

- [ ] **Generate TypeScript types**

```bash
composer generate:ts
```

Expected: `resources/js/wayfinder/` updated with all new `Report/` DTO types and `AlertLevel` enum types. Verify `App/Enums/AlertLevel.ts` is generated.

---

## Task 3: Cache Invalidation Listener

- [ ] **Create `app/Listeners/InvalidateAccountReportCache.php`**

```bash
php artisan make:listener InvalidateAccountReportCache --no-interaction
```

```php
<?php

namespace App\Listeners;

use App\Events\TransactionDeleted;
use App\Events\TransactionSaved;
use Illuminate\Support\Facades\Cache;

class InvalidateAccountReportCache
{
    /**
     * Handle the event.
     * Flushes all cached report entries for the affected account using tag-based invalidation.
     * Works with both Redis and the database cache driver (both support tags in Laravel 11+).
     */
    public function handle(TransactionSaved|TransactionDeleted $event): void
    {
        Cache::tags(['account:' . $event->transaction->account_id])->flush();
    }
}
```

- [ ] **Register the listener in `app/Providers/EventServiceProvider.php`**

Open the existing `EventServiceProvider` (or create one if it does not exist — Laravel 13 may use `AppServiceProvider` for event discovery instead). Add the listener alongside the existing `InvalidateAccountBalanceCache`:

```php
use App\Events\TransactionDeleted;
use App\Events\TransactionSaved;
use App\Listeners\InvalidateAccountBalanceCache;
use App\Listeners\InvalidateAccountReportCache;

// Inside the $listen array or boot() method:
TransactionSaved::class  => [
    InvalidateAccountBalanceCache::class,
    InvalidateAccountReportCache::class,
],
TransactionDeleted::class => [
    InvalidateAccountBalanceCache::class,
    InvalidateAccountReportCache::class,
],
```

> **Note:** If the project uses automatic event discovery (Laravel 13 default), verify `InvalidateAccountReportCache` implements `ShouldHandleEventsAfterCommit` or simply that it is discovered. Check `app/Providers/AppServiceProvider.php` for `Event::listen()` calls or a `withEvents()` call. If manual registration is used, add both event-to-listener bindings there.

---

## Task 4: ReportService

- [ ] **Create `app/Services/ReportService.php`**

```bash
php artisan make:class Services/ReportService --no-interaction
```

```php
<?php

namespace App\Services;

use App\Data\Report\CategoryLeakItemData;
use App\Data\Report\CategoryLeakReportData;
use App\Data\Report\ContributionMemberData;
use App\Data\Report\ContributionSplitData;
use App\Data\Report\CreditUtilizationData;
use App\Data\Report\FixedVariableData;
use App\Data\Report\TrendMonthData;
use App\Data\Report\TrendReportData;
use App\Enums\AccountAccessType;
use App\Enums\AlertLevel;
use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Income vs Expense Trend — last N months of monthly income/expense/net rows.
     * Cached per month: past months permanently, current month for 5 minutes.
     * Credit utilization is always live and is not cached here.
     */
    public function trend(Account $account, int $months = 6): TrendReportData
    {
        $from = Carbon::now()->startOfMonth()->subMonths($months - 1);

        $rows = [];
        $cursor = $from->copy();

        while ($cursor->lte(Carbon::now()->startOfMonth())) {
            $year  = $cursor->year;
            $month = $cursor->month;

            $cacheKey = "reports:{$account->id}:trend:{$year}:{$month}";
            $isCurrentMonth = $cursor->isSameMonth(Carbon::now());
            $ttl = $isCurrentMonth ? now()->addMinutes(5) : null;

            /** @var TrendMonthData $row */
            $row = Cache::tags(['account:' . $account->id])->remember(
                $cacheKey,
                $ttl,
                function () use ($account, $year, $month): TrendMonthData {
                    $result = DB::table('transactions')
                        ->selectRaw('
                            SUM(CASE WHEN type IN (?, ?) THEN amount ELSE 0 END) AS total_income,
                            SUM(CASE WHEN type IN (?, ?) THEN amount ELSE 0 END) AS total_expense
                        ', ['income', 'transfer_in', 'expense', 'fee'])
                        ->where('account_id', $account->id)
                        ->whereNull('deleted_at')
                        ->whereYear('transaction_date', $year)
                        ->whereMonth('transaction_date', $month)
                        ->first();

                    $income  = (float) ($result->total_income  ?? 0);
                    $expense = (float) ($result->total_expense ?? 0);
                    $net     = $income - $expense;
                    $surplusRate = $income > 0
                        ? round(($net / $income) * 100, 2)
                        : 0.0;

                    return new TrendMonthData(
                        year:         $year,
                        month:        $month,
                        income:       $income,
                        expense:      $expense,
                        net:          $net,
                        surplus_rate: $surplusRate,
                    );
                }
            );

            $rows[] = $row;
            $cursor->addMonth();
        }

        return new TrendReportData(
            months:       $rows,
            months_count: count($rows),
        );
    }

    /**
     * Category Leak — expense + fee totals ranked by category for a given period.
     * Cached per (from, to) window — past months permanently, current month for 5 minutes.
     */
    public function categoryLeak(Account $account, Carbon $from, Carbon $to): CategoryLeakReportData
    {
        $year  = $from->year;
        $month = $from->month;
        $cacheKey = "reports:{$account->id}:category-leak:{$year}:{$month}";
        $isCurrentMonth = $from->isSameMonth(Carbon::now());
        $ttl = $isCurrentMonth ? now()->addMinutes(5) : null;

        return Cache::tags(['account:' . $account->id])->remember(
            $cacheKey,
            $ttl,
            function () use ($account, $from, $to): CategoryLeakReportData {
                // Compute the period total first (used to calculate percentages inside the DB)
                $periodTotal = (float) DB::table('transactions')
                    ->where('account_id', $account->id)
                    ->whereIn('type', ['expense', 'fee'])
                    ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
                    ->whereNull('deleted_at')
                    ->sum('amount');

                if ($periodTotal <= 0) {
                    return new CategoryLeakReportData(
                        categories:   [],
                        period_total: 0.0,
                        from:         $from->toDateString(),
                        to:           $to->toDateString(),
                    );
                }

                $rows = DB::table('transactions as t')
                    ->join('categories as c', 'c.id', '=', 't.category_id')
                    ->selectRaw('
                        c.name,
                        c.color,
                        c.icon,
                        SUM(t.amount) AS total,
                        ROUND(SUM(t.amount) / ? * 100, 2) AS percentage
                    ', [$periodTotal])
                    ->where('t.account_id', $account->id)
                    ->whereIn('t.type', ['expense', 'fee'])
                    ->whereBetween('t.transaction_date', [$from->toDateString(), $to->toDateString()])
                    ->whereNull('t.deleted_at')
                    ->groupBy('t.category_id', 'c.name', 'c.color', 'c.icon')
                    ->orderByDesc('total')
                    ->get();

                $categories = $rows->map(fn (object $r) => new CategoryLeakItemData(
                    name:       $r->name,
                    color:      $r->color,
                    icon:       $r->icon,
                    total:      (float) $r->total,
                    percentage: (float) $r->percentage,
                ))->all();

                return new CategoryLeakReportData(
                    categories:   $categories,
                    period_total: $periodTotal,
                    from:         $from->toDateString(),
                    to:           $to->toDateString(),
                );
            }
        );
    }

    /**
     * Joint Contribution Split — income share by household member.
     * Returns is_joint: false with empty members for personal accounts (not an error).
     * Cached per (from, to) window — past months permanently, current month for 5 minutes.
     */
    public function contributionSplit(Account $account, Carbon $from, Carbon $to): ContributionSplitData
    {
        if ($account->access_type !== AccountAccessType::Joint) {
            return new ContributionSplitData(
                is_joint: false,
                members:  [],
                total:    0.0,
                from:     $from->toDateString(),
                to:       $to->toDateString(),
            );
        }

        $year  = $from->year;
        $month = $from->month;
        $cacheKey = "reports:{$account->id}:contribution-split:{$year}:{$month}";
        $isCurrentMonth = $from->isSameMonth(Carbon::now());
        $ttl = $isCurrentMonth ? now()->addMinutes(5) : null;

        return Cache::tags(['account:' . $account->id])->remember(
            $cacheKey,
            $ttl,
            function () use ($account, $from, $to): ContributionSplitData {
                $total = (float) DB::table('transactions')
                    ->where('account_id', $account->id)
                    ->where('type', 'income')
                    ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
                    ->whereNull('deleted_at')
                    ->sum('amount');

                if ($total <= 0) {
                    return new ContributionSplitData(
                        is_joint: true,
                        members:  [],
                        total:    0.0,
                        from:     $from->toDateString(),
                        to:       $to->toDateString(),
                    );
                }

                $rows = DB::table('transactions as t')
                    ->join('users as u', 'u.id', '=', 't.created_by')
                    ->selectRaw('u.name, SUM(t.amount) AS contributed')
                    ->where('t.account_id', $account->id)
                    ->where('t.type', 'income')
                    ->whereBetween('t.transaction_date', [$from->toDateString(), $to->toDateString()])
                    ->whereNull('t.deleted_at')
                    ->groupBy('t.created_by', 'u.name')
                    ->get();

                $members = $rows->map(fn (object $r) => new ContributionMemberData(
                    name:        $r->name,
                    contributed: (float) $r->contributed,
                    percentage:  round((float) $r->contributed / $total * 100, 2),
                ))->all();

                return new ContributionSplitData(
                    is_joint: true,
                    members:  $members,
                    total:    $total,
                    from:     $from->toDateString(),
                    to:       $to->toDateString(),
                );
            }
        );
    }

    /**
     * Credit Utilization — always live, never cached.
     * Uses BalanceService::forAccount() from the Ledger spec.
     * Only meaningful for credit card accounts (credit_card_limit must be set).
     */
    public function creditUtilization(Account $account): CreditUtilizationData
    {
        $limit   = (float) ($account->credit_card_limit ?? 0);
        $balance = app(BalanceService::class)->forAccount($account);

        // For credit cards: balance starts at limit and decreases as spending happens.
        // used = limit - balance (money spent against the credit line)
        $used      = max(0.0, $limit - (float) $balance);
        $available = max(0.0, (float) $balance);

        $utilizationPct = $limit > 0
            ? round($used / $limit * 100, 2)
            : 0.0;

        $alertLevel = match (true) {
            $utilizationPct >= 70 => AlertLevel::HighRisk,
            $utilizationPct >= 30 => AlertLevel::Warning,
            default               => AlertLevel::Normal,
        };

        return new CreditUtilizationData(
            limit:           $limit,
            used:            $used,
            available:       $available,
            utilization_pct: $utilizationPct,
            alert_level:     $alertLevel,
        );
    }

    /**
     * Fixed vs Variable — compares fixed-cost category spending vs variable for a period.
     * Cached per (from, to) window — past months permanently, current month for 5 minutes.
     */
    public function fixedVsVariable(Account $account, Carbon $from, Carbon $to): FixedVariableData
    {
        $year  = $from->year;
        $month = $from->month;
        $cacheKey = "reports:{$account->id}:fixed-vs-variable:{$year}:{$month}";
        $isCurrentMonth = $from->isSameMonth(Carbon::now());
        $ttl = $isCurrentMonth ? now()->addMinutes(5) : null;

        return Cache::tags(['account:' . $account->id])->remember(
            $cacheKey,
            $ttl,
            function () use ($account, $from, $to): FixedVariableData {
                $rows = DB::table('transactions as t')
                    ->join('categories as c', 'c.id', '=', 't.category_id')
                    ->selectRaw('c.is_fixed_cost, SUM(t.amount) AS total')
                    ->where('t.account_id', $account->id)
                    ->whereIn('t.type', ['expense', 'fee'])
                    ->whereBetween('t.transaction_date', [$from->toDateString(), $to->toDateString()])
                    ->whereNull('t.deleted_at')
                    ->groupBy('c.is_fixed_cost')
                    ->get()
                    ->keyBy('is_fixed_cost');

                $fixedTotal    = (float) ($rows->get(1)?->total ?? $rows->get(true)?->total ?? 0);
                $variableTotal = (float) ($rows->get(0)?->total ?? $rows->get(false)?->total ?? 0);
                $grandTotal    = $fixedTotal + $variableTotal;

                $fixedPct    = $grandTotal > 0 ? round($fixedTotal    / $grandTotal * 100, 2) : 0.0;
                $variablePct = $grandTotal > 0 ? round($variableTotal / $grandTotal * 100, 2) : 0.0;

                // Safety margin: percentage of total spend that is non-discretionary (fixed).
                // A lower fixed% means more flexibility — so safety margin = variable %.
                $safetyMargin = $variablePct;

                return new FixedVariableData(
                    fixed_total:    $fixedTotal,
                    variable_total: $variableTotal,
                    fixed_pct:      $fixedPct,
                    variable_pct:   $variablePct,
                    safety_margin:  $safetyMargin,
                    from:           $from->toDateString(),
                    to:             $to->toDateString(),
                );
            }
        );
    }
}
```

---

## Task 5: ReportsController

- [ ] **Create `app/Http/Controllers/ReportsController.php`**

```bash
php artisan make:controller ReportsController --no-interaction
```

```php
<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Models\Account;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    /**
     * Reports dashboard — trend + category leak summary for the account.
     */
    public function index(Request $request, Account $account): Response
    {
        $this->authorize('view', $account);

        [$from, $to] = $this->parseDateRange($request);

        $trend        = $this->reportService->trend($account, 6);
        $categoryLeak = $this->reportService->categoryLeak($account, $from, $to);

        return Inertia::render('reports/index', [
            'account'       => $account,
            'trend'         => $trend,
            'category_leak' => $categoryLeak,
            'from'          => $from->toDateString(),
            'to'            => $to->toDateString(),
        ]);
    }

    /**
     * Income vs Expense Trend — configurable number of months.
     */
    public function trend(Request $request, Account $account): Response
    {
        $this->authorize('view', $account);

        $months = (int) $request->query('months', 6);
        $months = max(1, min(24, $months)); // clamp: 1–24

        $trend = $this->reportService->trend($account, $months);

        return Inertia::render('reports/trend', [
            'account' => $account,
            'trend'   => $trend,
            'months'  => $months,
        ]);
    }

    /**
     * Category Leak — expense share by category for the selected period.
     */
    public function categoryLeak(Request $request, Account $account): Response
    {
        $this->authorize('view', $account);

        [$from, $to] = $this->parseDateRange($request);

        $data = $this->reportService->categoryLeak($account, $from, $to);

        return Inertia::render('reports/category-leak', [
            'account'       => $account,
            'category_leak' => $data,
            'from'          => $from->toDateString(),
            'to'            => $to->toDateString(),
        ]);
    }

    /**
     * Joint Contribution Split — only meaningful for joint accounts.
     * Personal accounts receive is_joint: false and an empty-state page.
     */
    public function contributionSplit(Request $request, Account $account): Response
    {
        $this->authorize('view', $account);

        [$from, $to] = $this->parseDateRange($request);

        $data = $this->reportService->contributionSplit($account, $from, $to);

        return Inertia::render('reports/contribution-split', [
            'account'            => $account,
            'contribution_split' => $data,
            'from'               => $from->toDateString(),
            'to'                 => $to->toDateString(),
        ]);
    }

    /**
     * Credit Utilization — always live, only relevant for credit_card accounts.
     */
    public function creditUtilization(Account $account): Response
    {
        $this->authorize('view', $account);

        abort_unless(
            $account->type === AccountType::CreditCard,
            422,
            'Credit utilization is only available for credit card accounts.'
        );

        $data = $this->reportService->creditUtilization($account);

        return Inertia::render('reports/credit-utilization', [
            'account'             => $account,
            'credit_utilization'  => $data,
        ]);
    }

    /**
     * Fixed vs Variable — expense split by is_fixed_cost for the selected period.
     */
    public function fixedVsVariable(Request $request, Account $account): Response
    {
        $this->authorize('view', $account);

        [$from, $to] = $this->parseDateRange($request);

        $data = $this->reportService->fixedVsVariable($account, $from, $to);

        return Inertia::render('reports/fixed-vs-variable', [
            'account'          => $account,
            'fixed_vs_variable' => $data,
            'from'              => $from->toDateString(),
            'to'                => $to->toDateString(),
        ]);
    }

    /**
     * Parse `from` / `to` query params. Defaults to current calendar month.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function parseDateRange(Request $request): array
    {
        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : Carbon::now()->startOfMonth();

        $to = $request->query('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : Carbon::now()->endOfMonth();

        return [$from, $to];
    }
}
```

---

## Task 6: Routes

- [ ] **Update `routes/web.php` — add report routes inside the existing `auth` + `verified` middleware group**

Add the import at the top of the file alongside existing controller imports:

```php
use App\Http\Controllers\ReportsController;
```

Add these routes inside the existing `Route::middleware(['auth', 'verified:auth.verification.notice'])->group()` block, after the accounts routes:

```php
// Reports (read-only — all GET)
Route::get('accounts/{account}/reports', [ReportsController::class, 'index'])->name('reports.index');
Route::get('accounts/{account}/reports/trend', [ReportsController::class, 'trend'])->name('reports.trend');
Route::get('accounts/{account}/reports/category-leak', [ReportsController::class, 'categoryLeak'])->name('reports.category-leak');
Route::get('accounts/{account}/reports/contribution-split', [ReportsController::class, 'contributionSplit'])->name('reports.contribution-split');
Route::get('accounts/{account}/reports/credit-utilization', [ReportsController::class, 'creditUtilization'])->name('reports.credit-utilization');
Route::get('accounts/{account}/reports/fixed-vs-variable', [ReportsController::class, 'fixedVsVariable'])->name('reports.fixed-vs-variable');
```

- [ ] **Regenerate Wayfinder**

```bash
php artisan wayfinder:generate --no-interaction
```

Expected: `resources/js/wayfinder/App/Http/Controllers/ReportsController.ts` generated with `index`, `trend`, `categoryLeak`, `contributionSplit`, `creditUtilization`, and `fixedVsVariable` action functions.

---

## Task 7: Feature Tests

- [ ] **Create `tests/Feature/ReportTest.php`**

```bash
php artisan make:test ReportTest --pest --no-interaction
```

```php
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
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function createUserWithAccount(array $accountAttributes = []): array
{
    $user      = User::factory()->create();
    $household = Household::factory()->create(['created_by' => $user->id]);
    HouseholdMember::factory()->owner()->create([
        'household_id' => $household->id,
        'user_id'      => $user->id,
    ]);
    $account = Account::factory()->create(array_merge([
        'owner_id'     => $user->id,
        'household_id' => $household->id,
    ], $accountAttributes));

    return [$user, $household, $account];
}

// ---------------------------------------------------------------------------
// ReportsController HTTP tests
// ---------------------------------------------------------------------------

it('reports index renders for account owner', function (): void {
    [$user, , $account] = createUserWithAccount();

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
    [, , $account] = createUserWithAccount(['access_type' => AccountAccessType::Personal->value]);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('reports.index', $account))
        ->assertForbidden();
});

it('trend endpoint returns trend data', function (): void {
    [$user, , $account] = createUserWithAccount();

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
    [$user, , $debitAccount] = createUserWithAccount(['type' => AccountType::DebitAccount->value]);

    $this->actingAs($user)
        ->get(route('reports.credit-utilization', $debitAccount))
        ->assertStatus(422);
});

it('credit utilization endpoint renders for credit card accounts', function (): void {
    [$user, , $account] = createUserWithAccount([
        'type'               => AccountType::CreditCard->value,
        'credit_card_limit'  => 10_000_000,
        'initial_balance'    => 10_000_000,
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
    [$user, , $account] = createUserWithAccount(['access_type' => AccountAccessType::Personal->value]);

    $this->actingAs($user)
        ->get(route('reports.contribution-split', $account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('contribution_split.is_joint', false)
        );
});

it('fixed vs variable endpoint renders', function (): void {
    [$user, , $account] = createUserWithAccount();

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
    [$user, , $account] = createUserWithAccount();
    $category = Category::factory()->create(['user_id' => $user->id]);

    Transaction::factory()->create([
        'account_id'       => $account->id,
        'created_by'       => $user->id,
        'category_id'      => $category->id,
        'type'             => 'income',
        'amount'           => 5_000_000,
        'transaction_date' => Carbon::now()->startOfMonth(),
    ]);

    Transaction::factory()->create([
        'account_id'       => $account->id,
        'created_by'       => $user->id,
        'category_id'      => $category->id,
        'type'             => 'expense',
        'amount'           => 2_000_000,
        'transaction_date' => Carbon::now()->startOfMonth(),
    ]);

    $service = app(ReportService::class);
    $result  = $service->trend($account, 1);

    expect($result)->toBeInstanceOf(TrendReportData::class);
    expect($result->months)->toHaveCount(1);

    $month = $result->months[0];
    expect($month->income)->toBe(5_000_000.0);
    expect($month->expense)->toBe(2_000_000.0);
    expect($month->net)->toBe(3_000_000.0);
    expect($month->surplus_rate)->toBe(60.0);
});

it('category leak report returns ranked categories by spend', function (): void {
    [$user, , $account] = createUserWithAccount();
    $catA = Category::factory()->create(['user_id' => $user->id, 'name' => 'Food']);
    $catB = Category::factory()->create(['user_id' => $user->id, 'name' => 'Transport']);

    Transaction::factory()->create([
        'account_id' => $account->id, 'created_by' => $user->id,
        'category_id' => $catA->id, 'type' => 'expense',
        'amount' => 3_000_000, 'transaction_date' => Carbon::now()->startOfMonth(),
    ]);
    Transaction::factory()->create([
        'account_id' => $account->id, 'created_by' => $user->id,
        'category_id' => $catB->id, 'type' => 'expense',
        'amount' => 1_000_000, 'transaction_date' => Carbon::now()->startOfMonth(),
    ]);

    $service = app(ReportService::class);
    $result  = $service->categoryLeak($account, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());

    expect($result)->toBeInstanceOf(CategoryLeakReportData::class);
    expect($result->categories)->toHaveCount(2);
    expect($result->categories[0]->name)->toBe('Food');
    expect($result->categories[0]->percentage)->toBe(75.0);
    expect($result->period_total)->toBe(4_000_000.0);
});

it('contribution split returns empty state for personal account without error', function (): void {
    [$user, , $account] = createUserWithAccount(['access_type' => AccountAccessType::Personal->value]);

    $service = app(ReportService::class);
    $result  = $service->contributionSplit($account, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());

    expect($result)->toBeInstanceOf(ContributionSplitData::class);
    expect($result->is_joint)->toBeFalse();
    expect($result->members)->toBeEmpty();
});

it('contribution split returns member shares for joint accounts', function (): void {
    $owner   = User::factory()->create();
    $partner = User::factory()->create();
    $household = Household::factory()->create(['created_by' => $owner->id]);
    HouseholdMember::factory()->owner()->create(['household_id' => $household->id, 'user_id' => $owner->id]);
    HouseholdMember::factory()->create(['household_id' => $household->id, 'user_id' => $partner->id]);

    $account = Account::factory()->joint()->create([
        'owner_id'     => $owner->id,
        'household_id' => $household->id,
    ]);
    $category = Category::factory()->create(['user_id' => $owner->id]);

    Transaction::factory()->create([
        'account_id' => $account->id, 'created_by' => $owner->id,
        'category_id' => $category->id, 'type' => 'income',
        'amount' => 6_000_000, 'transaction_date' => Carbon::now()->startOfMonth(),
    ]);
    Transaction::factory()->create([
        'account_id' => $account->id, 'created_by' => $partner->id,
        'category_id' => $category->id, 'type' => 'income',
        'amount' => 4_000_000, 'transaction_date' => Carbon::now()->startOfMonth(),
    ]);

    $service = app(ReportService::class);
    $result  = $service->contributionSplit($account, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());

    expect($result->is_joint)->toBeTrue();
    expect($result->members)->toHaveCount(2);
    expect($result->total)->toBe(10_000_000.0);
    // Owner contributed 60%
    $ownerRow = collect($result->members)->firstWhere('name', $owner->name);
    expect($ownerRow?->percentage)->toBe(60.0);
});

it('credit utilization calculates alert levels correctly', function (): void {
    [$user, , $account] = createUserWithAccount([
        'type'              => AccountType::CreditCard->value,
        'credit_card_limit' => 10_000_000,
        'initial_balance'   => 10_000_000,
    ]);
    $category = Category::factory()->create(['user_id' => $user->id]);

    // Spend 8M → 80% utilization → high_risk
    Transaction::factory()->create([
        'account_id'       => $account->id,
        'created_by'       => $user->id,
        'category_id'      => $category->id,
        'type'             => 'expense',
        'amount'           => 8_000_000,
        'transaction_date' => Carbon::now()->startOfMonth(),
    ]);

    $service = app(ReportService::class);
    $result  = $service->creditUtilization($account);

    expect($result)->toBeInstanceOf(CreditUtilizationData::class);
    expect($result->utilization_pct)->toBe(80.0);
    expect($result->alert_level)->toBe(AlertLevel::HighRisk);
});

it('fixed vs variable splits expenses by is_fixed_cost', function (): void {
    [$user, , $account] = createUserWithAccount();
    $fixed    = Category::factory()->fixed()->create(['user_id' => $user->id]);
    $variable = Category::factory()->create(['user_id' => $user->id]); // is_fixed_cost = false

    Transaction::factory()->create([
        'account_id' => $account->id, 'created_by' => $user->id,
        'category_id' => $fixed->id, 'type' => 'expense',
        'amount' => 3_000_000, 'transaction_date' => Carbon::now()->startOfMonth(),
    ]);
    Transaction::factory()->create([
        'account_id' => $account->id, 'created_by' => $user->id,
        'category_id' => $variable->id, 'type' => 'expense',
        'amount' => 1_000_000, 'transaction_date' => Carbon::now()->startOfMonth(),
    ]);

    $service = app(ReportService::class);
    $result  = $service->fixedVsVariable($account, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());

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
    [$user, , $account] = createUserWithAccount();

    // Warm the cache with a dummy value
    Cache::tags(['account:' . $account->id])->put(
        "reports:{$account->id}:trend:2026:06",
        'cached_value',
        60
    );

    expect(Cache::tags(['account:' . $account->id])->get("reports:{$account->id}:trend:2026:06"))
        ->toBe('cached_value');

    $category    = Category::factory()->create(['user_id' => $user->id]);
    $transaction = Transaction::factory()->create([
        'account_id'  => $account->id,
        'created_by'  => $user->id,
        'category_id' => $category->id,
        'type'        => 'expense',
        'amount'      => 500_000,
    ]);

    // Fire event (Ledger spec fires this on transaction save)
    TransactionSaved::dispatch($transaction);

    expect(Cache::tags(['account:' . $account->id])->get("reports:{$account->id}:trend:2026:06"))
        ->toBeNull();
});
```

---

## Task 8: PHP Formatting

- [ ] **Run Pint formatter on all modified PHP files**

```bash
vendor/bin/pint --dirty --format agent
```

Expected: All new PHP files reformatted to project style with no errors.

---

## Task 9: Commit

- [ ] **Stage all new and modified files**

```bash
git add app/Enums/AlertLevel.php app/Data/Report/ app/Listeners/InvalidateAccountReportCache.php app/Services/ReportService.php app/Http/Controllers/ReportsController.php routes/web.php tests/Feature/ReportTest.php resources/js/wayfinder/ resources/js/types/generated.d.ts
```

Also stage any modifications to `app/Providers/EventServiceProvider.php` or `app/Providers/AppServiceProvider.php`.

- [ ] **Commit**

```bash
git commit -m "$(cat <<'EOF'
feat(insights): add ReportService, ReportsController, and cache invalidation

Five SQL-aggregate report methods (trend, category leak, contribution split,
credit utilization, fixed vs variable). Tag-based Redis/database cache with
permanent TTL for past months and 5-minute TTL for the current month.
InvalidateAccountReportCache listener wired to TransactionSaved/Deleted.

Co-Authored-By: Claude Sonnet 4.6 (1M context) <noreply@anthropic.com>
EOF
)"
```
