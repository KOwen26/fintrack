# Transfer Aggregate (Approach B) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use better-executing-plans to implement this plan task-by-task — or better-parallel-subagents-executions if the plan splits into independent slices. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the mutual-pointer transfer linking with a first-class `transfers` aggregate table (member transactions reference it via `transfer_id`), split `type` into `type` + `flow`, split write endpoints into `/transactions` (plain) and `/transfers` (units), and rewire reports/dashboard to type-based semantics.

**Architecture:** A `Transfer` Eloquent model owns shared facts (amount, fee_amount, date, description); transaction rows become unit members carrying `(type, flow, transfer_id)`. `TransactionService` creates aggregate-then-rows in one DB transaction; unit edit = update aggregate + soft-delete/recreate member transactions; unit delete = aggregate + all member rows trashed together. Route model binding gives `/transfers/{transfer}` a real model — no anchor guards.

**Tech Stack:** Laravel 12 (PHP 8.4), Pest, Spatie Laravel Data, Inertia + Svelte 5, Wayfinder, Redis cache with tags.

**Workflow Mode:** `direct` — tests are written as part of each task but NOT run per-task; no verify/lint/commit steps anywhere in this plan. The user runs tests/format/commit themselves.

**Complexity:** Medium — every step is concrete code/commands; no exploratory tasks.

**Spec:** `docs/superpowers/specs/2026-09-11-transfer-aggregate-design.md` (authoritative). Decision record: `docs/superpowers/specs/2026-09-10-transaction-transfer-linking-design.md`.

**Post-execution revision (2026-09-11):** transfer lifecycle extracted from `TransactionService` into `TransferService` (`create` / `update` / `deleteUnit`, fee handling included); `TransactionController::destroy` dispatches unit members there; transfer edit page moved to `pages/transactions/edit-transfer.svelte` with the form in `components/module/transaction/` (no `transfers` frontend namespace). See spec §4 / §8.

**Note on the working tree:** there are pre-existing staged WIP changes (mutual-pointer work) in the files this plan rewrites. Work on top of them — the plan's file contents are the final state.

## Global Constraints

- PHP 8.4, curly braces on all control structures, explicit return types, constructor property promotion.
- Business logic lives in `app/Services/` — controllers are thin dispatchers calling one service method.
- No `$table->enum()` — `string` columns + PHP enum casts. No DB check constraints.
- Column order: `id`, relation keys, core data, status/notes/JSON, `softDeletes()`, `timestamps()`. Index every FK and every WHERE/ORDER BY column.
- `$table->decimal(15, 2)` for money; `$table->date()` for transaction dates (not datetime/timestamp).
- Models: `protected function casts(): array` method form; `$guarded = []`; `#[Scope]` attributes for scopes.
- Validation only via Form Requests in `app/Http/Requests/`.
- Data strategy: `migrate:fresh` + `DummyDataSeeder` — dev data is disposable; no data-migration code.
- Frontend: Svelte 5 runes only; kebab-case files; imports via `@wayfinder/*` (never hardcode URLs); run `php artisan wayfinder:generate` and `composer generate:ts` after backend changes (Task 11 does this).
- Reports filter "income"/"expense" on `type` (never `flow`); only balance math uses `flow`.
- Fee detection rule: `transfer_id !== NULL && type === expense`. Fee category: `Admin Fees` child ?? uncategorized.
- Relation naming: `Transfer::transactions()` (all member rows) with `sourceTransaction()` / `destinationTransaction()` / `feeTransaction()` accessors. Never "leg" terminology.
- Wayfinder controller import name is `TransferController` (singular, matching `TransactionController`), routes named `transfers.*`.

---

### Task 1: Migrations — `transfers` table + rewritten `transactions`

**Complexity:** Medium

**Files:**
- Create: `database/migrations/2026_06_16_161918_create_transfers_table.php`
- Modify: `database/migrations/2026_06_16_161919_create_transactions_table.php` (full rewrite)

**Interfaces:**
- Consumes: nothing (first task).
- Produces: `transfers` table (id, created_by, amount, fee_amount, transaction_date [date], description, timestamps, softDeletes, created_by index) and `transactions` columns `transfer_id` FK `cascadeOnDelete` + `flow` string + indexes `['transfer_id', 'flow']`, `['account_id', 'transaction_date']`, `['account_id', 'flow']`, `transfer_id`; `transfer_link_id` gone; `transaction_date` becomes `date`. **The transfers migration filename must sort BEFORE `2026_06_16_161919_` — transactions declares an FK to `transfers`, and `migrate:fresh` runs migrations in filename order.**

- [x] **Step 1: Create the transfers migration**

`database/migrations/2026_06_16_161918_create_transfers_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('fee_amount', 15, 2)->nullable();
            $table->date('transaction_date');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
```

- [x] **Step 2: Rewrite the transactions migration**

Replace the entire content of `database/migrations/2026_06_16_161919_create_transactions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('transfer_id')->nullable()->constrained('transfers')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('type');
            $table->string('flow');
            $table->date('transaction_date');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('account_id');
            $table->index('category_id');
            $table->index('created_by');
            $table->index('transfer_id');
            $table->index(['account_id', 'transaction_date']);
            $table->index(['account_id', 'flow']);
            $table->index(['transfer_id', 'flow']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
```

---

### Task 2: Enums — narrow `TransactionType`, add `TransactionFlow`

**Complexity:** Medium

**Files:**
- Modify: `app/Enums/TransactionType.php`
- Create: `app/Enums/TransactionFlow.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `TransactionType` with exactly 3 cases (`Income = 'income'`, `Expense = 'expense'`, `Transfer = 'transfer'`) and NO helper arrays; `TransactionFlow` with `Inflow = 'inflow'`, `Outflow = 'outflow'`. Deleting `inflows()`/`outflows()` breaks `DashboardController`, `TransactionObserver`, `BalanceService`, `TransactionService`, tests — Tasks 4, 5, 8, 9, 10 rewrite all of them. Do not run anything between Tasks 2 and 10.

- [x] **Step 1: Rewrite `app/Enums/TransactionType.php`**

```php
<?php

namespace App\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
}
```

- [x] **Step 2: Create `app/Enums/TransactionFlow.php`**

```php
<?php

namespace App\Enums;

enum TransactionFlow: string
{
    case Inflow = 'inflow';
    case Outflow = 'outflow';
}
```

---

### Task 3: Models — new `Transfer`, updated `Transaction`

**Complexity:** Medium

**Files:**
- Create: `app/Models/Transfer.php`
- Modify: `app/Models/Transaction.php`

**Interfaces:**
- Consumes: `TransactionType`, `TransactionFlow` (Task 2), tables (Task 1).
- Produces: `Transfer` with relations `transactions(): HasMany`, `sourceTransaction(): HasOne`, `destinationTransaction(): HasOne`, `feeTransaction(): HasOne`, `creator(): BelongsTo` and casts `amount/fee_amount => decimal:0`, `transaction_date => date`. `Transaction` casts `flow => TransactionFlow::class` plus existing `type` cast; relation `transfer(): BelongsTo`; the self-referential `relatedTransaction()` is DELETED.

- [x] **Step 1: Create `app/Models/Transfer.php`**

```php
<?php

namespace App\Models;

use App\Enums\TransactionFlow;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:0',
            'fee_amount' => 'decimal:0',
            'transaction_date' => 'date',
        ];
    }

    /** All unit member rows: source row, destination row, optional fee row. */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** The (transfer, outflow) row booked on the source account. */
    public function sourceTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class)
            ->where('type', TransactionType::Transfer->value)
            ->where('flow', TransactionFlow::Outflow->value);
    }

    /** The (transfer, inflow) row booked on the destination account. */
    public function destinationTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class)
            ->where('type', TransactionType::Transfer->value)
            ->where('flow', TransactionFlow::Inflow->value);
    }

    /** The (expense, outflow) fee row — may not exist. */
    public function feeTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class)
            ->where('type', TransactionType::Expense->value);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

- [x] **Step 2: Rewrite `app/Models/Transaction.php`**

```php
<?php

namespace App\Models;

use App\Enums\TransactionFlow;
use App\Enums\TransactionType;
use App\Observers\TransactionObserver;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([TransactionObserver::class])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'flow' => TransactionFlow::class,
            'amount' => 'decimal:0',
            'transaction_date' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The transfer unit this row belongs to — null for plain rows. */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }
}
```

---

### Task 4: Observer — flow-based direction + original-account reversal fix

**Complexity:** Medium

**Files:**
- Modify: `app/Observers/TransactionObserver.php` (full rewrite)
- Test: `tests/Feature/TransactionObserverTest.php` (adapt + add)

**Interfaces:**
- Consumes: `TransactionFlow` (Task 2), `flow` column (Task 1), `Transaction` casts (Task 3).
- Produces: observer semantics — direction from `$transaction->flow`; `updated()` reverses the OLD impact against the ORIGINAL account (`getOriginal('account_id')`) using `TransactionFlow::from($transaction->getOriginal('flow'))`.

- [x] **Step 1: Adapt the transfer-row observer test**

In `tests/Feature/TransactionObserverTest.php`, replace the `transfer_in/transfer_out` test (lines ~48–70) with a flow-based version:

```php
it('adjusts balance correctly for transfer rows by flow', function (): void {
    [$user, $source] = createBalanceAccount();
    $dest = Account::factory()->create(['owner_id' => $user->id, 'initial_balance' => 0]);

    Transaction::factory()->create([
        'account_id' => $source->id,
        'created_by' => $user->id,
        'amount' => 200_000,
        'type' => 'transfer',
        'flow' => 'outflow',
    ]);

    Transaction::factory()->create([
        'account_id' => $dest->id,
        'created_by' => $user->id,
        'amount' => 200_000,
        'type' => 'transfer',
        'flow' => 'inflow',
    ]);

    expect($source->fresh()->current_balance)->toEqual(-200_000.0);
    expect($dest->fresh()->current_balance)->toEqual(200_000.0);
});
```

- [x] **Step 2: Add the account-change regression test**

Append to the `updated` section of the same file:

```php
it('reverses the old impact on the original account when the account changes', function (): void {
    [$user, $source] = createBalanceAccount();
    $dest = Account::factory()->create(['owner_id' => $user->id, 'initial_balance' => 0]);

    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $source->id,
        'created_by' => $user->id,
        'amount' => 250_000,
    ]);

    expect($source->fresh()->current_balance)->toEqual(-250_000.0);

    $transaction->update(['account_id' => $dest->id]);

    expect($source->fresh()->current_balance)->toEqual(0.0);
    expect($dest->fresh()->current_balance)->toEqual(-250_000.0);
});
```

- [x] **Step 3: Rewrite the observer**

Replace the entire content of `app/Observers/TransactionObserver.php`:

```php
<?php

namespace App\Observers;

use App\Enums\TransactionFlow;
use App\Models\Account;
use App\Models\Transaction;

class TransactionObserver
{
    /**
     * The multiplier applied to the transaction amount based on its flow.
     * +1 for inflows (increase balance), -1 for outflows (decrease balance).
     */
    private static function directionMultiplier(Transaction $transaction): int
    {
        return $transaction->flow === TransactionFlow::Inflow ? 1 : -1;
    }

    /**
     * Adjust the account balance when a transaction is created.
     */
    public function created(Transaction $transaction): void
    {
        if ($transaction->account_id === null) {
            return;
        }

        $transaction->account()->increment('current_balance', self::directionMultiplier($transaction) * $transaction->amount);
    }

    /**
     * Adjust balances when a transaction is updated.
     *
     * The OLD impact is reversed against the ORIGINAL account (using
     * getOriginal) so that account changes move the impact instead of
     * duplicating it. The NEW impact lands on the current account.
     */
    public function updated(Transaction $transaction): void
    {
        $originalAccountId = $transaction->getOriginal('account_id');

        if ($originalAccountId !== null) {
            $originalAmount = (float) $transaction->getOriginal('amount');
            $originalFlow = TransactionFlow::from($transaction->getOriginal('flow'));
            $originalMultiplier = $originalFlow === TransactionFlow::Inflow ? 1 : -1;

            Account::whereKey($originalAccountId)
                ->decrement('current_balance', $originalMultiplier * $originalAmount);
        }

        if ($transaction->account_id !== null) {
            $transaction->account()->increment('current_balance', self::directionMultiplier($transaction) * $transaction->amount);
        }
    }

    /**
     * Reverse the account balance impact when a transaction is soft-deleted.
     */
    public function deleted(Transaction $transaction): void
    {
        if ($transaction->account_id === null) {
            return;
        }

        $transaction->account()->decrement('current_balance', self::directionMultiplier($transaction) * $transaction->amount);
    }

    /**
     * Re-apply the account balance impact when a soft-deleted transaction is restored.
     */
    public function restored(Transaction $transaction): void
    {
        $this->created($transaction);
    }
}
```

---

### Task 5: `BalanceService` — flow-based aggregate

**Complexity:** Medium

**Files:**
- Modify: `app/Services/BalanceService.php` (full rewrite)
- Test: `tests/Feature/BalanceServiceTest.php` (adapt)

**Interfaces:**
- Consumes: `flow` column (Task 1).
- Produces: `BalanceService::forAccount(Account): string` with identical cache key/tag behavior, SQL CASE keyed on `flow`.

- [x] **Step 1: Adapt the mixed-types balance test**

In `tests/Feature/BalanceServiceTest.php`, replace `computes balance correctly across mixed transaction types` (lines ~55–82):

```php
it('computes balance correctly across mixed types and flows', function (): void {
    [$user, $account] = setupBalanceAccount(500_000);

    Transaction::factory()->income()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 700_000,
    ]);
    Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 100_000,
    ]);
    Transaction::factory()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 200_000,
        'type' => 'transfer',
        'flow' => 'inflow',
    ]);
    Transaction::factory()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 150_000,
        'type' => 'transfer',
        'flow' => 'outflow',
    ]);

    $service = new BalanceService;

    expect((float) $service->forAccount($account))->toBe(1_150_000.0);
});
```

- [x] **Step 2: Rewrite `BalanceService`**

```php
<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BalanceService
{
    public function forAccount(Account $account): string
    {
        $cacheKey = "balance:account:{$account->id}";

        return Cache::tags(["account:{$account->id}"])->rememberForever($cacheKey, function () use ($account): string {
            $balance = DB::table('accounts')
                ->selectRaw(
                    "accounts.initial_balance + COALESCE(SUM(CASE
                        WHEN t.flow = 'inflow' THEN t.amount
                        ELSE -t.amount
                    END), 0) AS balance"
                )
                ->leftJoin('transactions as t', function ($join): void {
                    $join->on('t.account_id', '=', 'accounts.id')->whereNull('t.deleted_at');
                })
                ->where('accounts.id', $account->id)
                ->groupBy('accounts.id', 'accounts.initial_balance')
                ->value('balance');

            return (string) ($balance ?? $account->initial_balance);
        });
    }
}
```

---

### Task 6: Factories + seeder + fresh migrate

**Complexity:** Medium

**Files:**
- Create: `database/factories/TransferFactory.php`
- Modify: `database/factories/TransactionFactory.php` (full rewrite)
- Modify: `database/seeders/DummyDataSeeder.php` (transfer methods only)

**Interfaces:**
- Consumes: `Transfer` model (Task 3), enums (Task 2), migrations (Task 1).
- Produces: `TransferFactory` (definition + `withFee()` state); `TransactionFactory` states `income()` (flow inflow), `expense()` (flow outflow, the definition default), `transferOutflow(Transfer|int $transfer)`, `transferInflow(Transfer|int $transfer)`, `transferFee(Transfer|int $transfer)`; seeder books transfers as aggregate + member rows.

- [x] **Step 1: Create `database/factories/TransferFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transfer>
 */
class TransferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'amount' => fake()->randomFloat(2, 10_000, 5_000_000),
            'fee_amount' => null,
            'transaction_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'description' => fake()->optional(0.6)->sentence(),
        ];
    }

    public function withFee(): static
    {
        return $this->state(['fee_amount' => fake()->randomFloat(2, 1_000, 10_000)]);
    }
}
```

- [x] **Step 2: Rewrite `database/factories/TransactionFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Enums\TransactionFlow;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'category_id' => Category::factory(),
            'created_by' => User::factory(),
            'amount' => fake()->randomFloat(2, 1_000_000, 10_000_000),
            'type' => TransactionType::Expense,
            'flow' => TransactionFlow::Outflow,
            'transfer_id' => null,
            'transaction_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'description' => fake()->optional(0.6)->sentence(),
        ];
    }

    public function income(): static
    {
        return $this->state([
            'type' => TransactionType::Income,
            'flow' => TransactionFlow::Inflow,
        ]);
    }

    public function expense(): static
    {
        return $this->state([
            'type' => TransactionType::Expense,
            'flow' => TransactionFlow::Outflow,
        ]);
    }

    /** Outflow member row of a transfer unit — booked on the source account. */
    public function transferOutflow(Transfer | int $transfer): static
    {
        return $this->state([
            'type' => TransactionType::Transfer,
            'flow' => TransactionFlow::Outflow,
            'transfer_id' => $transfer instanceof Transfer ? $transfer->id : $transfer,
            'category_id' => null,
        ]);
    }

    /** Inflow member row of a transfer unit — booked on the destination account. */
    public function transferInflow(Transfer | int $transfer): static
    {
        return $this->state([
            'type' => TransactionType::Transfer,
            'flow' => TransactionFlow::Inflow,
            'transfer_id' => $transfer instanceof Transfer ? $transfer->id : $transfer,
            'category_id' => null,
        ]);
    }

    /** Fee member row of a transfer unit — an expense on the source account. */
    public function transferFee(Transfer | int $transfer): static
    {
        return $this->state([
            'type' => TransactionType::Expense,
            'flow' => TransactionFlow::Outflow,
            'transfer_id' => $transfer instanceof Transfer ? $transfer->id : $transfer,
            'category_id' => null,
        ]);
    }

    public function forCategory(int $categoryId): static
    {
        return $this->state(['category_id' => $categoryId]);
    }
}
```

- [x] **Step 3: Rework the seeder's transfer booking**

In `database/seeders/DummyDataSeeder.php`: add `use App\Models\Transfer;` to the imports, then replace `seedTransfer()` (lines ~183–213) and update the docblock above it:

```php
    /**
     * Book one transfer as an aggregate + member rows: a Transfer row with a
     * source row and destination row on distinct accounts of the same user
     * (same amount and date, uncategorized), plus a fee row ~30% of the time.
     */
    private function seedTransfer(User $user, array $accountIds, int $year, int $month): void
    {
        if (count($accountIds) < 2) {
            return;
        }

        $keys = (array) array_rand($accountIds, 2);
        [$fromAccountId, $toAccountId] = [$accountIds[$keys[0]], $accountIds[$keys[1]]];
        $amount = random_int(10, 500) * 1000;
        $date = $this->randomDateInMonth($year, $month)->toDateString();
        $withFee = fake()->boolean(30);

        $transfer = Transfer::factory()->create([
            'created_by' => $user->id,
            'amount' => $amount,
            'fee_amount' => $withFee ? random_int(1, 10) * 500 : null,
            'transaction_date' => $date,
            'description' => fake()->sentence(3),
        ]);

        Transaction::factory()->transferOutflow($transfer)->create([
            'account_id' => $fromAccountId,
            'created_by' => $user->id,
            'amount' => $amount,
            'transaction_date' => $date,
            'description' => $transfer->description,
        ]);

        Transaction::factory()->transferInflow($transfer)->create([
            'account_id' => $toAccountId,
            'created_by' => $user->id,
            'amount' => $amount,
            'transaction_date' => $date,
            'description' => $transfer->description,
        ]);

        if ($withFee) {
            Transaction::factory()->transferFee($transfer)->create([
                'account_id' => $fromAccountId,
                'created_by' => $user->id,
                'amount' => $transfer->fee_amount,
                'transaction_date' => $date,
                'description' => 'Transfer fee',
            ]);
        }
    }
```

Also delete the now-stale `'transfer_link_id' => null` lines in `seedMonthlyTransactions()`'s income/expense sequences (lines ~152, ~166).

- [x] **Step 4: Fresh-migrate and reseed**

Run: `php artisan migrate:fresh --seed --no-interaction`
Expected: migrations run in order (`...161918_create_transfers_table` before `...161919_create_transactions_table`), seeders complete. This also smoke-tests Tasks 1–5 together — if it errors, fix before continuing.

---

### Task 7: Write DTOs + Form Requests

**Complexity:** Medium

**Files:**
- Modify: `app/Data/Transaction/TransactionData.php` (full rewrite)
- Create: `app/Data/Transaction/TransferData.php`
- Modify: `app/Data/Transaction/TransactionListData.php` (full rewrite)
- Modify: `app/Data/Transaction/TransactionDetailData.php`
- Modify: `app/Http/Requests/SaveTransactionRequest.php` (full rewrite)
- Create: `app/Http/Requests/SaveTransferRequest.php`

**Interfaces:**
- Consumes: enums (Task 2), `Transfer` model (Task 3).
- Produces: `TransactionData(account_id: int, type: TransactionType, amount: float, transaction_date: string, category_id: ?int, description: ?string, flow: ?TransactionFlow, transfer_id: ?int)` with `derivedFlow(): TransactionFlow`; `TransferData(account_id: int, destination_account_id: int, amount: float, transaction_date: string, fee_amount: ?float, description: ?string)`; `TransactionListData` props now include `flow: TransactionFlow`, `transfer_id: ?int` (replacing `transfer_link_id`) and `fromTransaction()` folds via `transfer.transactions`; `TransactionDetailData` gains `flow: TransactionFlow` and `?int $transfer_id` (after `type`); `SaveTransferRequest` rules per spec §7.2.

- [x] **Step 1: Rewrite `TransactionData`**

```php
<?php

namespace App\Data\Transaction;

use App\Enums\TransactionFlow;
use App\Enums\TransactionType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Write-side payload for creating or updating a PLAIN transaction (income or
 * expense) — transfer units use TransferData via the /transfers endpoints.
 * `flow` is derived server-side from `type`; `transfer_id` is set internally
 * for unit member rows. Neither is ever accepted from clients.
 */
#[TypeScript]
class TransactionData extends Data
{
    public function __construct(
        public int $account_id,

        public TransactionType $type,

        public float $amount,

        public string $transaction_date,

        /** Income/expense always carry one (form-enforced). */
        public ?int $category_id = null,

        public ?string $description = null,

        /** Service-internal only — derived from type, never client-sent. */
        public ?TransactionFlow $flow = null,

        /** Service-internal only — set for unit member rows, never client-sent. */
        public ?int $transfer_id = null,
    ) {}

    public function derivedFlow(): TransactionFlow
    {
        return $this->flow
            ?? ($this->type === TransactionType::Income ? TransactionFlow::Inflow : TransactionFlow::Outflow);
    }
}
```

- [x] **Step 2: Create `app/Data/Transaction/TransferData.php`**

```php
<?php

namespace App\Data\Transaction;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Write-side payload for creating or updating a transfer UNIT via the
 * /transfers endpoints. No type/flow — the endpoint implies them and the
 * service derives per-row values. The payload covers all member rows
 * including the optional fee.
 */
#[TypeScript]
class TransferData extends Data
{
    public function __construct(
        public int $account_id,

        public int $destination_account_id,

        public float $amount,

        public string $transaction_date,

        public ?float $fee_amount = null,

        public ?string $description = null,
    ) {}
}
```

- [x] **Step 3: Rewrite `TransactionListData`**

```php
<?php

namespace App\Data\Transaction;

use App\Enums\TransactionFlow;
use App\Enums\TransactionType;
use App\Helpers\TypeScript\Attributes\TypeScriptModel;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TransactionListData extends Data
{
    public function __construct(
        public int $id,

        public TransactionType $type,

        public TransactionFlow $flow,

        public float $amount,

        public ?string $description,

        public string $transaction_date,

        public ?int $category_id,

        public int $account_id,

        public ?int $transfer_id,

        public ?int $destination_account_id,

        #[TypeScriptModel(Transaction::class)]
        public ?Transaction $related_transaction,

        #[TypeScriptModel(Account::class)]
        public ?Account $account,

        #[TypeScriptModel(Category::class)]
        public ?Category $category,
    ) {}

    /**
     * Build from a transaction, folding the transfer counterpart through the
     * aggregate. Movement rows fold to their opposite-flow counterpart; fee
     * rows and plain rows fold to nothing (destination stays NULL). No DB
     * queries beyond loadMissing — eager-loaded callers skip it entirely.
     */
    public static function fromTransaction(Transaction $transaction): self
    {
        $transaction->loadMissing('transfer.transactions.account');

        $transfer = $transaction->getRelation('transfer');

        $counterpart = null;
        if ($transfer !== null && $transaction->type === TransactionType::Transfer) {
            $counterpart = $transfer->transactions
                ->first(fn (Transaction $member): bool => $member->id !== $transaction->id
                    && $member->flow !== $transaction->flow);
        }

        return new self(
            id: $transaction->id,
            type: $transaction->type,
            flow: $transaction->flow,
            amount: (float) $transaction->amount,
            description: $transaction->description,
            transaction_date: $transaction->transaction_date->toDateString(),
            category_id: $transaction->category_id,
            account_id: $transaction->account_id,
            transfer_id: $transaction->transfer_id,
            destination_account_id: $counterpart?->account_id,
            related_transaction: $counterpart,
            account: $transaction->relationLoaded('account') ? $transaction->account : null,
            category: $transaction->relationLoaded('category') ? $transaction->category : null,
        );
    }
}
```

- [x] **Step 4: Extend `TransactionDetailData`**

In `app/Data/Transaction/TransactionDetailData.php`, add two props right after `public TransactionType $type,`:

```php
        public TransactionFlow $flow,

        public ?int $transfer_id,
```

and add `use App\Enums\TransactionFlow;` to the imports. (`Data::from($transaction)` picks both up from the model — the `flow` cast exists since Task 3.)

- [x] **Step 5: Rewrite `SaveTransactionRequest`**

```php
<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Plain-row validation for POST/PUT /transactions. Transfer shapes
 * (destination, fee) are rejected outright — they belong to
 * SaveTransferRequest via the /transfers endpoints.
 */
class SaveTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'type' => ['required', 'string', Rule::in([TransactionType::Income->value, TransactionType::Expense->value])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'destination_account_id' => ['prohibited'],
            'fee_amount' => ['prohibited'],
        ];
    }
}
```

- [x] **Step 6: Create `app/Http/Requests/SaveTransferRequest.php`**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Transfer-unit validation for POST/PUT /transfers. Unconditional rules —
 * the endpoint implies the transfer type; no type field is accepted.
 */
class SaveTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'destination_account_id' => ['required', 'integer', 'exists:accounts,id', 'different:account_id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'fee_amount' => ['nullable', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['prohibited'],
            'category_id' => ['prohibited'],
        ];
    }
}
```

---

### Task 8: `TransactionService` rewrite + service-level tests

**Complexity:** Medium

**Files:**
- Modify: `app/Services/TransactionService.php` (full rewrite)
- Test: `tests/Feature/TransferServiceTest.php` (new)

**Interfaces:**
- Consumes: `TransactionData`/`TransferData` (Task 7), `Transfer` model (Task 3), factories (Task 6).
- Produces: `TransactionService::getTransactions(User): Collection` (global list, inflow rows hidden), `getAccountTransactions`, `getCategoryTransactions`, `create(User, TransactionData): Transaction`, `update(Transaction, TransactionData): Transaction` (plain only — guard lives in the controller), `createTransfer(User, TransferData): Transfer`, `updateTransfer(Transfer, TransferData): Transfer`, `softDelete(Transaction): void` (unit-aware: aggregate + all member rows together). Private: `createUnitTransactions(...)`, `resolveTransferFeeCategory(): ?int`.

- [x] **Step 1: Write the service-level tests**

Create `tests/Feature/TransferServiceTest.php`:

```php
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
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createTransferAccounts(): array
{
    $user = User::factory()->create();
    $source = Account::factory()->create(['owner_id' => $user->id]);
    $destination = Account::factory()->create(['owner_id' => $user->id]);

    return [$user, $source, $destination];
}

function unitData(Account $source, Account $destination, ?float $fee = null): TransferData
{
    return new TransferData(
        account_id: $source->id,
        destination_account_id: $destination->id,
        amount: 500_000,
        transaction_date: now()->toDateString(),
        fee_amount: $fee,
        description: 'Savings move',
    );
}

it('creates a transfer unit with an aggregate, source/destination rows, and an optional fee row', function (): void {
    [$user, $source, $destination] = createTransferAccounts();
    $parent = Category::factory()->create();
    Category::factory()->create(['name' => 'Admin Fees', 'parent_id' => $parent->id]);

    $transfer = app(TransactionService::class)->createTransfer($user, unitData($source, $destination, 6_500.0));

    expect((float) $transfer->fee_amount)->toEqual(6_500.0);

    $rows = $transfer->transactions()->get();
    expect($rows)->toHaveCount(3);

    $sourceRow = $rows->first(fn ($row) => $row->type === TransactionType::Transfer && $row->flow === TransactionFlow::Outflow);
    $destinationRow = $rows->first(fn ($row) => $row->type === TransactionType::Transfer && $row->flow === TransactionFlow::Inflow);
    $feeRow = $rows->first(fn ($row) => $row->type === TransactionType::Expense);

    expect($sourceRow->account_id)->toBe($source->id)
        ->and($destinationRow->account_id)->toBe($destination->id)
        ->and($feeRow->account_id)->toBe($source->id)
        ->and((float) $feeRow->amount)->toEqual(6_500.0)
        ->and($feeRow->category)->not->toBeNull();
});

it('books the fee row as uncategorized when no Admin Fees category exists', function (): void {
    [$user, $source, $destination] = createTransferAccounts();

    $transfer = app(TransactionService::class)->createTransfer($user, unitData($source, $destination, 6_500.0));

    $feeRow = $transfer->transactions()->get()->firstWhere('type', TransactionType::Expense);
    expect($feeRow->category_id)->toBeNull();
});

it('recreates member rows and keeps the transfer id stable on unit edit', function (): void {
    [$user, $source, $destination] = createTransferAccounts();
    $service = app(TransactionService::class);

    $transfer = $service->createTransfer($user, unitData($source, $destination));
    $originalRowIds = $transfer->transactions()->pluck('id');

    $edited = $service->updateTransfer($transfer, new TransferData(
        account_id: $source->id,
        destination_account_id: $destination->id,
        amount: 700_000,
        transaction_date: now()->toDateString(),
        fee_amount: null,
        description: 'Edited',
    ));

    expect($edited->id)->toBe($transfer->id)
        ->and((float) $edited->amount)->toEqual(700_000.0)
        ->and($edited->fee_amount)->toBeNull();

    $liveRows = $edited->transactions()->get();
    expect($liveRows)->toHaveCount(2);

    $liveRows->each(fn ($row) => expect($originalRowIds->notContains($row->id))->toBeTrue());
    expect(Transaction::withTrashed()->where('transfer_id', $edited->id)->count())->toBe(4);
});

it('soft-deletes the aggregate and every member row (fee included) from any member', function (): void {
    [$user, $source, $destination] = createTransferAccounts();
    $service = app(TransactionService::class);

    $transfer = $service->createTransfer($user, unitData($source, $destination, 2_500.0));
    $feeRow = $transfer->transactions()->get()->firstWhere('type', TransactionType::Expense);

    // Deleting the FEE cascades the whole unit — symmetric unit semantics.
    $service->softDelete($feeRow);

    expect($transfer->fresh()->trashed())->toBeTrue()
        ->and(Transaction::where('transfer_id', $transfer->id)->count())->toBe(0)
        ->and(Transaction::withTrashed()->where('transfer_id', $transfer->id)->count())->toBe(3);
});

it('folds destination_account_id from both movement rows but not the fee', function (): void {
    [$user, $source, $destination] = createTransferAccounts();
    $transfer = app(TransactionService::class)->createTransfer($user, unitData($source, $destination, 1_000.0));

    $rows = $transfer->transactions()->get();
    $sourceRow = $rows->first(fn ($row) => $row->type === TransactionType::Transfer && $row->flow === TransactionFlow::Outflow);
    $destinationRow = $rows->first(fn ($row) => $row->type === TransactionType::Transfer && $row->flow === TransactionFlow::Inflow);
    $feeRow = $rows->firstWhere('type', TransactionType::Expense);

    expect(TransactionListData::fromTransaction($sourceRow)->destination_account_id)->toBe($destination->id)
        ->and(TransactionListData::fromTransaction($destinationRow)->destination_account_id)->toBe($source->id)
        ->and(TransactionListData::fromTransaction($feeRow)->destination_account_id)->toBeNull();
});

it('hides inflow rows from the global list but shows fee and outflow rows', function (): void {
    [$user, $source, $destination] = createTransferAccounts();
    app(TransactionService::class)->createTransfer($user, unitData($source, $destination, 1_000.0));

    $global = TransactionService::getTransactions($user);

    expect($global->where('flow', TransactionFlow::Inflow))->toHaveCount(0)
        ->and($global)->toHaveCount(2); // outflow row + fee row
});
```

- [x] **Step 2: Rewrite `TransactionService`**

Replace the entire content of `app/Services/TransactionService.php`:

```php
<?php

namespace App\Services;

use App\Data\Transaction\TransactionData;
use App\Data\Transaction\TransferData;
use App\Enums\TransactionFlow;
use App\Enums\TransactionType;
use App\Events\TransactionDeleted;
use App\Events\TransactionSaved;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    /**
     * Global list: one row per transfer unit (the outflow row) plus plain
     * rows and fee rows — inflow rows are hidden.
     */
    public static function getTransactions(User $user): Collection
    {
        return Transaction::query()
            ->where('created_by', $user->id)
            ->whereNot(fn ($query) => $query->where('type', 'transfer')->where('flow', 'inflow'))
            ->with(['account', 'category', 'transfer.transactions.account'])
            ->latest('transaction_date')
            ->get();
    }

    public static function getAccountTransactions(Account $account): Collection
    {
        return Transaction::query()
            ->where('account_id', $account->id)
            ->with(['account', 'category', 'transfer.transactions.account'])
            ->latest('transaction_date')
            ->get();
    }

    public static function getCategoryTransactions(Category $category): Collection
    {
        return Transaction::query()
            ->where('category_id', $category->id)
            ->with(['account', 'category'])
            ->latest('transaction_date')
            ->get();
    }

    public function create(User $creator, TransactionData $data): Transaction
    {
        $transaction = Transaction::create([
            'account_id' => $data->account_id,
            'created_by' => $creator->id,
            'amount' => $data->amount,
            'type' => $data->type,
            'flow' => $data->derivedFlow(),
            'transfer_id' => $data->transfer_id,
            'transaction_date' => $data->transaction_date,
            'category_id' => $data->category_id,
            'description' => $data->description,
        ]);

        TransactionSaved::dispatch($transaction);

        return $transaction;
    }

    /**
     * Plain-row edit — the controller guarantees the row is not a transfer
     * unit member (unit members are rejected with 422 upstream).
     */
    public function update(Transaction $transaction, TransactionData $data): Transaction
    {
        $transaction->update([
            'account_id' => $data->account_id,
            'type' => $data->type,
            'flow' => $data->derivedFlow(),
            'amount' => $data->amount,
            'transaction_date' => $data->transaction_date,
            'category_id' => $data->category_id,
            'description' => $data->description,
        ]);

        TransactionSaved::dispatch($transaction->fresh());

        return $transaction->fresh();
    }

    public function createTransfer(User $creator, TransferData $data): Transfer
    {
        $sourceAccount = Account::findOrFail($data->account_id);
        $destinationAccount = Account::findOrFail($data->destination_account_id);

        return DB::transaction(function () use ($creator, $sourceAccount, $destinationAccount, $data): Transfer {
            $transfer = Transfer::create([
                'created_by' => $creator->id,
                'amount' => $data->amount,
                'fee_amount' => $data->fee_amount,
                'transaction_date' => $data->transaction_date,
                'description' => $data->description,
            ]);

            $this->createUnitTransactions($transfer, $creator, $sourceAccount, $destinationAccount, $data);

            return $transfer;
        });
    }

    /**
     * Unit edit = update the aggregate in place (stable id), then soft-delete
     * the member transactions (observers reverse balances) and re-create them
     * from the updated aggregate. The payload covers all rows including the fee.
     */
    public function updateTransfer(Transfer $transfer, TransferData $data): Transfer
    {
        $sourceAccount = Account::findOrFail($data->account_id);
        $destinationAccount = Account::findOrFail($data->destination_account_id);

        return DB::transaction(function () use ($transfer, $sourceAccount, $destinationAccount, $data): Transfer {
            $transfer->update([
                'amount' => $data->amount,
                'fee_amount' => $data->fee_amount,
                'transaction_date' => $data->transaction_date,
                'description' => $data->description,
            ]);

            $transfer->transactions()->get()->each(function (Transaction $member): void {
                $member->delete();
                TransactionDeleted::dispatch($member);
            });

            $this->createUnitTransactions($transfer, $transfer->creator, $sourceAccount, $destinationAccount, $data);

            return $transfer->fresh();
        });
    }

    /**
     * Deleting any unit member deletes the whole unit: aggregate + every
     * member row (fee included), symmetric. Soft deletes never fire the FK
     * cascade, so the pairing is app-enforced inside one transaction.
     */
    public function softDelete(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            $rows = $transaction->transfer_id !== null
                ? Transaction::where('transfer_id', $transaction->transfer_id)->get()
                : collect([$transaction]);

            $rows->each(function (Transaction $row): void {
                $row->delete();
                TransactionDeleted::dispatch($row);
            });

            $transaction->transfer?->delete();
        });
    }

    private function createUnitTransactions(Transfer $transfer, User $creator, Account $source, Account $destination, TransferData $data): void
    {
        $this->create($creator, new TransactionData(
            account_id: $source->id,
            type: TransactionType::Transfer,
            amount: $data->amount,
            transaction_date: $data->transaction_date,
            description: $data->description,
            flow: TransactionFlow::Outflow,
            transfer_id: $transfer->id,
        ));

        $this->create($creator, new TransactionData(
            account_id: $destination->id,
            type: TransactionType::Transfer,
            amount: $data->amount,
            transaction_date: $data->transaction_date,
            description: $data->description,
            flow: TransactionFlow::Inflow,
            transfer_id: $transfer->id,
        ));

        if ($data->fee_amount !== null && $data->fee_amount > 0) {
            $this->create($creator, new TransactionData(
                account_id: $source->id,
                type: TransactionType::Expense,
                amount: $data->fee_amount,
                transaction_date: $data->transaction_date,
                category_id: $this->resolveTransferFeeCategory(),
                description: 'Transfer fee',
                flow: TransactionFlow::Outflow,
                transfer_id: $transfer->id,
            ));
        }
    }

    /**
     * Resolve the Admin Fees child category for booking transfer fees.
     * Returns null when it does not exist — the fee then books as uncategorized.
     */
    private function resolveTransferFeeCategory(): ?int
    {
        return Category::query()
            ->where('name', 'Admin Fees')
            ->levelChildren()
            ->first()
            ?->id;
    }
}
```

---

### Task 9: HTTP layer — routes, `TransferController`, `TransferPolicy`, guards + HTTP tests

**Complexity:** Medium

**Files:**
- Modify: `routes/web.php`
- Create: `app/Http/Controllers/TransferController.php`
- Create: `app/Policies/TransferPolicy.php`
- Modify: `app/Http/Controllers/TransactionController.php`
- Test: `tests/Feature/TransactionTest.php` (rewrite transfer sections + new tests)

**Interfaces:**
- Consumes: service methods (Task 8), requests (Task 7), `Transfer` model (Task 3).
- Produces: routes `transfers.store` (POST /transfers), `transfers.edit` (GET /transfers/{transfer}/edit), `transfers.update` (PUT /transfers/{transfer}); `TransactionController::store` plain-only (no `isTransfer` branch); `TransactionController::update` rejects unit members with `abort_unless(..., 422)`; `TransactionController::edit` redirects unit members to `transfers.edit`; `TransferController::edit` renders `transfers/edit` with `['transfer', 'accounts']` props (transfer eager-loaded with `transactions.account`).

- [ ] **Step 1: Add the transfers routes**

In `routes/web.php`, add `use App\Http\Controllers\TransferController;` to the imports, add `Route::get('{transaction}/edit', [TransactionController::class, 'edit'])->name('edit');` inside the transactions group (after the `show` line — this route was missing at HEAD; `TransactionController::edit()` was unrouted dead code), and insert after the transactions group:

```php
    // Transfers (unit write surface — listing/show/delete stay on transactions)
    Route::prefix('transfers')->name('transfers.')->group(function (): void {
        Route::post('', [TransferController::class, 'store'])->name('store');
        Route::get('{transfer}/edit', [TransferController::class, 'edit'])->name('edit');
        Route::put('{transfer}', [TransferController::class, 'update'])->name('update');
    });
```

- [ ] **Step 2: Create `app/Policies/TransferPolicy.php`**

```php
<?php

namespace App\Policies;

use App\Models\Transfer;
use App\Models\User;

/**
 * Transfer-unit access is creator-based, mirroring TransactionPolicy:
 * writes are reserved to the creator alone, which keeps the unit cascade
 * creator-initiated (every member row shares the aggregate's creator).
 */
class TransferPolicy
{
    public function view(User $user, Transfer $transfer): bool
    {
        return $transfer->created_by === $user->id;
    }

    public function update(User $user, Transfer $transfer): bool
    {
        return $transfer->created_by === $user->id;
    }

    public function delete(User $user, Transfer $transfer): bool
    {
        return $transfer->created_by === $user->id;
    }
}
```

- [ ] **Step 3: Create `app/Http/Controllers/TransferController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Data\Transaction\TransferData;
use App\Http\Requests\SaveTransferRequest;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\AccountService;
use App\Services\TransactionService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly AccountService $accountService,
        #[CurrentUser] private readonly ?User $user
    ) {}

    public function store(SaveTransferRequest $request): RedirectResponse
    {
        $this->authorize('create', Transaction::class);

        $this->transactionService->createTransfer($this->user, TransferData::from($request->validated()));

        return to_route('transactions.index')->flash('Transfer saved.');
    }

    public function edit(Request $request, Transfer $transfer): Response
    {
        $this->authorize('update', $transfer);

        return Inertia::render('transfers/edit', [
            'transfer' => $transfer->load('transactions.account'),
            'accounts' => $this->accountService->getAccountsByUser($this->user),
        ]);
    }

    public function update(SaveTransferRequest $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('update', $transfer);

        $this->transactionService->updateTransfer($transfer, TransferData::from($request->validated()));

        return to_route('transactions.index')->flash('Transfer updated.');
    }
}
```

- [ ] **Step 4: Update `TransactionController`**

In `app/Http/Controllers/TransactionController.php`:

1. Delete the `isTransfer()` dispatch from `store()` — it becomes:

```php
    public function store(SaveTransactionRequest $request): RedirectResponse
    {
        $this->authorize('create', Transaction::class);

        $this->transactionService->create($this->user, TransactionData::from($request->validated()));

        return to_route('transactions.index')->flash('Transaction saved.');
    }
```

2. Add the unit-member guard to `update()` (after the authorize call):

```php
        abort_unless($transaction->transfer_id === null, 422, 'Transfer unit members must be edited via their transfer.');
```

3. Redirect unit members away from `edit()` (after the authorize call):

```php
        if ($transaction->transfer_id !== null) {
            return redirect()->route('transfers.edit', $transaction->transfer_id);
        }
```

- [ ] **Step 5: Rewrite the transfer sections of `TransactionTest`**

In `tests/Feature/TransactionTest.php`: add imports `use App\Enums\TransactionFlow; use App\Models\Transfer; use App\Services\TransactionService;` (`TransactionType` is already imported), then REPLACE the four tests `creates a transfer pair pointing at each other via transfer_link_id`, `books a transfer fee as an expense in the Admin Fees category`, `books a transfer fee as uncategorized when no Admin Fees category exists`, and `soft-deletes all transfer rows when one is deleted` (lines ~64–175) with:

```php
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

    $outflow = $rows->first(fn ($row) => $row->flow === TransactionFlow::Outflow && $row->type === TransactionType::Transfer);
    $inflow = $rows->first(fn ($row) => $row->flow === TransactionFlow::Inflow && $row->type === TransactionType::Transfer);

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

    $transfer = app(TransactionService::class)->createTransfer($user, new \App\Data\Transaction\TransferData(
        account_id: $sourceAccount->id,
        destination_account_id: $destAccount->id,
        amount: 250_000,
        transaction_date: now()->toDateString(),
    ));
    $outflow = $transfer->transactions()->get()->first(fn ($row) => $row->flow === TransactionFlow::Outflow);

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
    $inflow = $transfer->transactions()->get()->first(fn ($row) => $row->flow === TransactionFlow::Inflow);

    $this->actingAs($user)->delete(route('transactions.destroy', $inflow))->assertRedirect();

    expect($transfer->fresh()->trashed())->toBeTrue()
        ->and(Transaction::where('transfer_id', $transfer->id)->count())->toBe(0)
        ->and(Transaction::withTrashed()->where('transfer_id', $transfer->id)->count())->toBe(3);
});
```

Then REPLACE `resolves destination_account_id for both sides of a transfer pair` (lines ~194–211) with:

```php
it('resolves destination_account_id for both sides of a transfer unit', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id]);

    $transfer = app(TransactionService::class)->createTransfer($user, new \App\Data\Transaction\TransferData(
        account_id: $sourceAccount->id,
        destination_account_id: $destAccount->id,
        amount: 250_000,
        transaction_date: now()->toDateString(),
    ));
    $rows = $transfer->transactions()->get();

    $outflow = $rows->first(fn ($row) => $row->flow === TransactionFlow::Outflow);
    $inflow = $rows->first(fn ($row) => $row->flow === TransactionFlow::Inflow);

    expect(TransactionListData::fromTransaction($outflow)->destination_account_id)->toBe($destAccount->id)
        ->and(TransactionListData::fromTransaction($inflow)->destination_account_id)->toBe($sourceAccount->id);
});
```

---

### Task 10: Report semantics — type-based summaries

**Complexity:** Medium

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php:45-55`
- Modify: `app/Services/ReportService.php` (trend + expense literals)
- Modify: `app/Services/SpendingService.php:18-23,47-48`
- Test: `tests/Feature/TransferReportSemanticsTest.php` (new)

**Interfaces:**
- Consumes: 3-case `TransactionType` (Task 2).
- Produces: dashboard `monthly_income` = `type = income` only; `monthly_expenses` = `type = expense` only; `ReportService::trend()` income/expense by type; `SpendingService`/`ReportService` expense literals replaced with `TransactionType::Expense->value`.

- [ ] **Step 1: Write the semantics test**

Create `tests/Feature/TransferReportSemanticsTest.php`:

```php
<?php

use App\Data\Transaction\TransferData;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
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
    app(TransactionService::class)->createTransfer($user, new TransferData(
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
    if (! Cache::getStore() instanceof \Illuminate\Cache\TaggableStore) {
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
    app(TransactionService::class)->createTransfer($user, new TransferData(
        account_id: $account->id,
        destination_account_id: $other->id,
        amount: 500_000,
        transaction_date: $today,
    ));

    $report = app(\App\Services\ReportService::class)->trend($account, 1);

    expect($report->months[0]->income)->toEqual(1_000_000.0)
        ->and($report->months[0]->expense)->toEqual(200_000.0);
});
```

- [ ] **Step 2: Rewire `DashboardController`**

Replace lines 45–55 of `app/Http/Controllers/DashboardController.php` (the two `whereIn('type', ...)` queries):

```php
        $monthlyIncome = (float) Transaction::whereIn('account_id', $accountIds)
            ->where('type', TransactionType::Income->value)
            ->whereBetween('transaction_date', [$from, $to])
            ->whereNull('deleted_at')
            ->sum('amount');

        $monthlyExpenses = (float) Transaction::whereIn('account_id', $accountIds)
            ->where('type', TransactionType::Expense->value)
            ->whereBetween('transaction_date', [$from, $to])
            ->whereNull('deleted_at')
            ->sum('amount');
```

- [ ] **Step 3: Rewire `ReportService::trend()`**

In `app/Services/ReportService.php`, replace the `selectRaw` inside `trend()`'s closure (lines ~45–49):

```php
                    $result = DB::table('transactions')
                        ->selectRaw('
                            SUM(CASE WHEN type = ? THEN amount ELSE 0 END) AS total_income,
                            SUM(CASE WHEN type = ? THEN amount ELSE 0 END) AS total_expense
                        ', [\App\Enums\TransactionType::Income->value, \App\Enums\TransactionType::Expense->value])
```

(Use a proper `use App\Enums\TransactionType;` import at the top instead of the inline FQN.)

- [ ] **Step 4: Replace remaining expense literals**

- `app/Services/ReportService.php` — `categorySpending()` (~line 103) and `fixedVsVariable()` (~line 243): `whereIn('type', ['expense'])` → `where('type', TransactionType::Expense->value)`.
- `app/Services/SpendingService.php` — both occurrences (lines ~20, ~48): same replacement with `use App\Enums\TransactionType;` imported.

---

### Task 11: Codegen — Wayfinder + TypeScript types

**Complexity:** Low

**Files:**
- Generated: `resources/js/wayfinder/**` (routes, `App/Http/Controllers/TransferController`, `App/Enums/TransactionFlow`, narrowed `App/Enums/TransactionType`)
- Generated: `resources/js/types/generated.d.ts` (`Transfer` model, `Transaction.transfer_id`/`flow`, DTO changes)

**Interfaces:**
- Consumes: routes + `TransferController` (Task 9), DTOs (Task 7).
- Produces: frontend imports used by Tasks 12–13: `TransferController from '@wayfinder/App/Http/Controllers/TransferController'` (`.store.url()`, `.edit.url({ transfer })`, `.update.url({ transfer })`), `TransactionType from '@wayfinder/App/Enums/TransactionType'`, `TransactionFlow from '@wayfinder/App/Enums/TransactionFlow'`, `App.Models.Transfer`, `Transaction.flow`/`transfer_id`.

- [ ] **Step 1: Regenerate Wayfinder**

Run: `php artisan wayfinder:generate --no-interaction`

- [ ] **Step 2: Regenerate TypeScript types**

Run: `composer generate:ts`

- [ ] **Step 3: Sanity-check generated output**

Run: `php artisan route:list --name=transfers --no-interaction`
Expected: `transfers.store`, `transfers.edit`, `transfers.update` listed. Confirm `resources/js/wayfinder/App/Enums/TransactionFlow.ts` exists and `TransactionType.ts` contains exactly 3 cases.

---

### Task 12: Frontend — type/flow cleanup across components

**Complexity:** Medium

**Files:**
- Modify: `resources/js/schema/transaction.schema.ts` (delete kind machinery)
- Modify: `resources/js/components/module/transaction/transaction-type-badge.svelte`
- Modify: `resources/js/components/module/transaction/transaction-list-item.svelte`
- Modify: `resources/js/components/module/transaction/transaction-detail.svelte`
- Modify: `resources/js/pages/dashboard/dashboard.svelte`
- Modify: `resources/js/pages/accounts/show.svelte`
- Modify: `resources/js/pages/transactions/show.svelte` (isTransferRow only — edit links are Task 13)
- Modify: `resources/js/pages/transactions/edit.svelte` (isTransferRow only)
- Modify: `resources/js/components/module/transaction/transaction-list.svelte` (resolveKind → direct type — missed by initial §5.5 scan)
- Modify: `resources/js/components/module/transaction/transaction-card.svelte` (kind derived → transaction.type — same miss)
- Modify: `resources/js/components/module/transaction/transaction-list-filter.svelte` (option keys → 3 real types)
- Modify: `resources/js/app.ts` (layout switch gains `transfers` prefix — global layout rule)

**Interfaces:**
- Consumes: generated enums/controllers (Task 11), `TransactionListData.flow`, `TransactionDetailData.flow`.
- Produces: no `TransactionKind`/`resolveKind` anywhere; `TYPE_STYLE` keyed on `App.Enums.TransactionType` exported from `transaction-list-item.svelte`; sign derives from `flow`.

- [ ] **Step 1: Delete kind machinery from `transaction.schema.ts`**

In `resources/js/schema/transaction.schema.ts`: delete the `TransactionKind` type (line 6), the `KIND_BY_TYPE` map (lines 8–17), and `resolveKind` (lines 19–21). Everything else in the file stays.

- [ ] **Step 2: Re-key `transaction-type-badge.svelte`**

Replace `<script lang="ts">` content:

```svelte
<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { App } from '@wayfinder/types';

    import TransactionType from '@wayfinder/App/Enums/TransactionType';

    import Badge from '@components/ui/badge.svelte';

    let { type }: { type: App.Enums.TransactionType } = $props();

    const config: Record<App.Enums.TransactionType, { label: string; color: ColorVariant }> = {
        [TransactionType.Income]: { label: 'Income', color: 'success' },
        [TransactionType.Expense]: { label: 'Expense', color: 'error' },
        [TransactionType.Transfer]: { label: 'Transfer', color: 'info' },
    };

    const badge = $derived(config[type]);
</script>
```

- [ ] **Step 3: Re-key `transaction-list-item.svelte`**

In the module script, replace the `TransactionKind`-keyed `TYPE_STYLE` with the 3 real types:

```svelte
<script lang="ts" module>
    import type { App } from '@wayfinder/types';

    import TransactionType from '@wayfinder/App/Enums/TransactionType';

    export const TYPE_STYLE: Record<
        App.Enums.TransactionType,
        { label: string; color: string; bg: string; icon: string }
    > = {
        [TransactionType.Income]: {
            label: 'Income',
            color: 'var(--color-success)',
            bg: 'color-mix(in oklab, var(--color-success) 12%, transparent)',
            icon: 'solar--arrow-up-line-duotone',
        },
        [TransactionType.Expense]: {
            label: 'Expense',
            color: 'var(--color-error)',
            bg: 'color-mix(in oklab, var(--color-error) 12%, transparent)',
            icon: 'solar--arrow-down-line-duotone',
        },
        [TransactionType.Transfer]: {
            label: 'Transfer',
            color: 'var(--color-info)',
            bg: 'color-mix(in oklab, var(--color-info) 12%, transparent)',
            icon: 'solar--transfer-horizontal-bold-duotone',
        },
    };
</script>
```

In the instance script: delete the `resolveKind` import; replace the `typeConfig` derived with:

```ts
    const typeConfig = $derived(TYPE_STYLE[transaction.type]);

    const signIcon = $derived(
        transaction.flow === 'inflow' ? 'solar--add-bold-duotone' : 'solar--minus-bold-duotone'
    );
```

- [ ] **Step 4: Flow-based checks in `transaction-detail.svelte`**

Replace lines 18–22:

```ts
    const isInflow = $derived(transaction.flow === 'inflow');

    const isTransfer = $derived(transaction.type === 'transfer');
```

- [ ] **Step 5: De-duplicate `dashboard.svelte` TYPE_STYLE**

In `resources/js/pages/dashboard/dashboard.svelte`: delete the local `TYPE_STYLE` map (lines ~109–116) and its `TransactionKind` import; import instead:

```ts
    import { TYPE_STYLE } from '@components/module/transaction/transaction-list-item.svelte';
```

Change the usage (line ~341) from `TYPE_STYLE[resolveKind(tx.type)]` to `TYPE_STYLE[tx.type]`, and remove the `resolveKind` import.

- [ ] **Step 6: Type-based totals in `accounts/show.svelte`**

Replace lines 82–92:

```ts
    const incomeTotal = $derived(
        transactions.filter((t) => t.type === 'income').reduce((sum, t) => sum + Number(t.amount), 0)
    );

    const expenseTotal = $derived(
        transactions.filter((t) => t.type === 'expense').reduce((sum, t) => sum + Number(t.amount), 0)
    );
```

- [ ] **Step 7: Replace `resolveKind` in the transaction pages**

- `resources/js/pages/transactions/show.svelte` line 24: `const isTransferRow = $derived(transaction.type === 'transfer');` — delete the `resolveKind` import.
- `resources/js/pages/transactions/edit.svelte` line 34: same replacement — delete the `resolveKind` import.

---

### Task 13: Frontend — forms & pages endpoint routing

**Complexity:** Medium

**Files:**
- Modify: `resources/js/components/module/transaction/transaction-form.svelte`
- Create: `resources/js/components/module/transfer/transfer-form.svelte`
- Create: `resources/js/pages/transfers/edit.svelte`
- Modify: `resources/js/pages/transactions/show.svelte` (edit links)
- Modify: `resources/js/pages/transactions/edit.svelte` (delete copy)

**Interfaces:**
- Consumes: `TransferController` wayfinder URLs (Task 11), `transfers.edit` props `transfer` (with `transactions[].account`) + `accounts`.
- Produces: create tab posts transfer shape to `transfers.store`; unit members' edit actions route to `transfers.edit`; the transfer unit edits via `PUT transfers.update`.

- [ ] **Step 1: Route the create form by tab; add the fee field**

In `resources/js/components/module/transaction/transaction-form.svelte`:

1. Delete the `resolveKind` import; add `import TransferController from '@wayfinder/App/Http/Controllers/TransferController';`
2. Replace `resolvedType` (lines ~38–40):

```ts
    const resolvedType = $derived<string>(isEdit && transaction ? transaction.type : type);
```

3. Replace `action` (lines ~105–109):

```ts
    const action = $derived(
        isEdit && transaction
            ? TransactionController.update.url({ transaction: transaction.id })
            : resolvedType === 'transfer'
              ? TransferController.store.url()
              : TransactionController.store.url()
    );
```

4. Inside the existing `{#if typeConfig.showDestination}` region, add a fee block after the destination `AccountSelect` block:

```svelte
                    <div class="mx-5 border-t border-base-content/10"></div>

                    <div class="flex items-center px-5 py-3">
                        <div class="flex-1">
                            <span
                                class="text-[0.625rem] font-bold tracking-[0.09em] text-base-content/40 uppercase">
                                Biaya Transfer (opsional)
                            </span>
                            <input
                                class="input mt-0.5 w-full border-none bg-transparent px-0 font-mono text-sm font-medium placeholder:text-base-content/30"
                                inputmode="numeric"
                                placeholder="0"
                                type="text"
                                bind:value={form.fee_amount} />
                        </div>
                    </div>
```

- [ ] **Step 2: Create the transfer edit form component**

`resources/js/components/module/transfer/transfer-form.svelte`:

```svelte
<script lang="ts">
    import type { InertiaForm } from '@inertiajs/svelte';
    import type { App } from '@wayfinder/types';

    import { useForm } from '@inertiajs/svelte';
    import TransferController from '@wayfinder/App/Http/Controllers/TransferController';

    import AccountSelect from '@components/ui/forms/account-select.svelte';
    import DateInput from '@components/ui/forms/date-input.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import Form from '@components/ui/forms/form.svelte';

    interface Props {
        transfer: App.Models.Transfer & { transactions: App.Models.Transaction[] };
        accounts: App.Models.Account[];
        onCancel?: () => void;
    }

    let { transfer, accounts, onCancel }: Props = $props();

    const sourceTransaction = $derived(
        transfer.transactions.find((row) => row.type === 'transfer' && row.flow === 'outflow')
    );
    const destinationTransaction = $derived(
        transfer.transactions.find((row) => row.type === 'transfer' && row.flow === 'inflow')
    );

    let form: InertiaForm<any> = $state(
        useForm({
            account_id: sourceTransaction?.account_id ?? '',
            destination_account_id: destinationTransaction?.account_id ?? '',
            amount: Number(transfer.amount),
            fee_amount: transfer.fee_amount === null ? '' : Number(transfer.fee_amount),
            transaction_date: transfer.transaction_date,
            description: transfer.description ?? '',
        })
    );

    const action = $derived(TransferController.update.url({ transfer: transfer.id }));
    const defaultBack = () => window.history.back();

    let displayAmount = $state(Number(transfer.amount).toLocaleString('id-ID'));

    function handleAmountInput(e: Event): void {
        const input = e.target as HTMLInputElement;
        const raw = input.value.replace(/\D/g, '');
        const num = parseInt(raw, 10) || 0;
        form.amount = num;
        displayAmount = num ? num.toLocaleString('id-ID') : '';
    }
</script>

<div class="space-y-3">
    <div class="flex items-center justify-between px-1">
        <button
            class="btn btn-square btn-ghost btn-sm"
            aria-label="Kembali"
            onclick={onCancel ?? defaultBack}
            type="button">
            <i class="iconify size-5 solar--arrow-left-line-duotone"></i>
        </button>
        <span class="text-sm font-semibold tracking-tight">Edit Transfer</span>
        <div class="w-9"></div>
    </div>

    <Form id="transfer-form" {action} {form} method="put">
        <div class="card overflow-hidden rounded-2xl border border-base-content/15 bg-base-100">
            <div class="h-1 w-full bg-info"></div>
            <div class="px-5 py-4">
                <p class="text-[0.625rem] font-bold tracking-[0.09em] text-base-content/40 uppercase">
                    Nominal Transfer
                </p>
                <div class="mt-1 flex items-center gap-1.5">
                    <span class="font-mono text-sm font-medium text-base-content/40">Rp</span>
                    <input
                        class="w-full border-none bg-transparent font-mono text-[clamp(2rem,9vw,2.6rem)] leading-none font-medium tracking-tight outline-none text-info"
                        inputmode="numeric"
                        oninput={handleAmountInput}
                        placeholder="0"
                        type="text"
                        value={displayAmount} />
                </div>
            </div>
        </div>

        <div class="card overflow-hidden rounded-2xl border border-base-content/15 bg-base-100">
            <div class="flex flex-col px-5 py-3">
                <label
                    class="text-[0.625rem] font-bold tracking-[0.09em] text-base-content/40 uppercase"
                    for="tf-description">
                    Deskripsi Transfer
                </label>
                <input
                    id="tf-description"
                    class="input mt-0.5 w-full border-none bg-transparent px-0 text-sm font-medium placeholder:text-base-content/30"
                    placeholder="Contoh: Kirim uang bulanan"
                    type="text"
                    bind:value={form.description} />
            </div>

            <div class="mx-5 border-t border-base-content/10"></div>

            <div class="flex items-center px-5 py-3">
                <div class="flex-1">
                    <span class="text-[0.625rem] font-bold tracking-[0.09em] text-base-content/40 uppercase">
                        Akun Asal (Dari)
                    </span>
                    <div class="mt-0.5">
                        <AccountSelect {accounts} placeholder="Pilih akun" bind:value={form.account_id} />
                    </div>
                </div>
            </div>

            <div class="mx-5 border-t border-base-content/10"></div>

            <div class="flex items-center px-5 py-3">
                <div class="flex-1">
                    <span class="text-[0.625rem] font-bold tracking-[0.09em] text-base-content/40 uppercase">
                        Akun Tujuan
                    </span>
                    <div class="mt-0.5">
                        <AccountSelect
                            {accounts}
                            placeholder="Pilih tujuan"
                            bind:value={form.destination_account_id} />
                    </div>
                </div>
            </div>

            <div class="mx-5 border-t border-base-content/10"></div>

            <div class="flex items-center px-5 py-3">
                <div class="flex-1">
                    <span class="text-[0.625rem] font-bold tracking-[0.09em] text-base-content/40 uppercase">
                        Biaya Transfer (opsional)
                    </span>
                    <input
                        class="input mt-0.5 w-full border-none bg-transparent px-0 font-mono text-sm font-medium placeholder:text-base-content/30"
                        inputmode="numeric"
                        placeholder="0"
                        type="text"
                        bind:value={form.fee_amount} />
                </div>
            </div>

            <div class="mx-5 border-t border-base-content/10"></div>

            <div class="flex items-center px-5 py-3">
                <div class="flex-1">
                    <label
                        class="text-[0.625rem] font-bold tracking-[0.09em] text-base-content/40 uppercase"
                        for="tf-date">
                        Tanggal
                    </label>
                    <div class="mt-0.5">
                        <DateInput
                            id="tf-date"
                            class="input-sm"
                            placeholder="Pilih tanggal"
                            bind:value={form.transaction_date} />
                    </div>
                </div>
            </div>
        </div>
    </Form>

    <FormAction
        form={form as any}
        formId="transfer-form"
        labelCancel="Batal"
        labelSubmit="Simpan Perubahan"
        onCancel={onCancel ?? defaultBack} />
</div>
```

- [ ] **Step 3: Create the transfer edit page**

`resources/js/pages/transfers/edit.svelte`:

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import PageSection from '@components/layouts/page-section.svelte';
    import TransferForm from '@components/module/transfer/transfer-form.svelte';
    import DashboardPageHeader from '@components/navigation/dashboard-page-header.svelte';

    let {
        transfer,
        accounts,
    }: {
        transfer: App.Models.Transfer & { transactions: App.Models.Transaction[] };
        accounts: App.Models.Account[];
    } = $props();
</script>

<DashboardPageHeader title="Edit Transfer" />

<PageSection>
    <TransferForm
        {transfer}
        {accounts}
        onCancel={() => router.visit(TransactionController.index.url())} />
</PageSection>
```

- [ ] **Step 4: Wire edit links to the transfer page**

In `resources/js/pages/transactions/show.svelte`: add `import TransferController from '@wayfinder/App/Http/Controllers/TransferController';`, then below `isTransferRow` add:

```ts
    const editHref = $derived(
        transaction.transfer_id !== null
            ? TransferController.edit.url({ transfer: transaction.transfer_id })
            : TransactionController.edit.url({ transaction: transaction.id })
    );
```

and replace both commented-out Edit buttons' hrefs with `href={editHref}` (delete the `//` comment markers).

- [ ] **Step 5: Update the edit-page delete copy**

In `resources/js/pages/transactions/edit.svelte` line 43, change the copy to `'Transfer — edit the whole unit'` (the page only receives plain rows now; transfer units edit via the transfers page).

---

## Self-Review Results

- **Spec coverage:** §5.1–5.2 → Tasks 1–2. §5.3 → Tasks 2, 7, 9 (`isTransfer` branch), 12 (`resolveKind`). §5.4 → Tasks 4–5. §5.5 → Tasks 11–13. §5.6 → Task 10. §6 → Tasks 1, 3. §7 → Tasks 7, 9. §8 → Task 8 (+ 9 for endpoints). §8.3 soft-delete discipline → `softDelete` test (Task 8 Step 1). §9 edge cases → 422 test (Task 9), fee-survival reversal (Task 9 unit-delete test), FK cascade on hard delete (schema-level, covered by migration). §11 behavior changes → covered by respective tasks' tests. §14 checklist items map 1:1 to tasks.
- **Placeholders:** none — every step carries full code/commands.
- **Type consistency:** `TransactionData.derivedFlow()`, `TransferData` shape, `transferOutflow/transferInflow/transferFee` factory states, `Transfer::transactions()` + `sourceTransaction`/`destinationTransaction`/`feeTransaction` accessors, `TransferController` wayfinder import, `transfer.transactions.account` eager loads, and `transfer_id` prop names are used identically across tasks.
- **Workflow mode:** `direct` — no run/verify/commit steps anywhere, no final Verification task. Matches the user's standing profile (they run tests/pint/commit themselves).
- **Testing scope:** tests written for observer (behavior + regression fix), balance aggregate, service unit semantics + fold + list filter, HTTP endpoints incl. 422s + fee-survival reversal, report semantics. No tests for migrations/enums/models/factories/seeder/frontend/codegen — trivial wiring or covered indirectly by the feature suite, matching this project's Pest conventions.
- **Complexity:** all tasks Medium (fully concrete); overall **Medium**.

## Known Follow-ups (out of plan scope)

- The stale WIP in the git index (mutual-pointer code) is subsumed by these tasks — review the final diff before committing.
- Deferred per spec §12: unit-level restore if a restore route is ever added.
- Optional future ticket: a periodic job asserting `accounts.current_balance === BalanceService::forAccount()` (trial-balance-style corruption alarm).
