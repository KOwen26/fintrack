# Ledger — Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the complete backend for Transactions and Budgets — every migration, enum, model, policy, form request, service, event/listener, DTO, controller, and route the Ledger spec defines.

**Architecture:** Service pattern throughout — controllers are thin dispatchers, all business logic lives in `app/Services/`. Events fire after writes; listeners handle cache invalidation. Balance and budget spend are computed exclusively via SQL aggregates (`selectRaw`/`SUM`) — never PHP loops. No DB enums — string columns + PHP-backed enum casts. `BudgetStatusData` is the only DTO (it combines model data with a computed percentage); transactions and budgets pass directly as models.

**Tech Stack:** PHP 8.4, Laravel 13, Spatie Laravel Data, Pest 4, Inertia v3

**Depends on:** Foundation spec fully implemented.

---

## File Map

```
database/migrations/
  *_create_transactions_table.php
  *_create_budgets_table.php

app/Enums/
  TransactionType.php

app/Models/
  Transaction.php
  Budget.php

database/factories/
  TransactionFactory.php
  BudgetFactory.php

app/Policies/
  TransactionPolicy.php
  BudgetPolicy.php

app/Http/Requests/
  StoreTransactionRequest.php
  UpdateTransactionRequest.php
  StoreBudgetRequest.php
  UpdateBudgetRequest.php

app/Services/
  TransactionService.php
  BudgetService.php
  BalanceService.php

app/Events/
  TransactionSaved.php
  TransactionDeleted.php

app/Listeners/
  InvalidateAccountBalanceCache.php
  InvalidateAccountReportCache.php

app/Data/
  BudgetStatusData.php

app/Http/Controllers/
  TransactionsController.php
  BudgetsController.php

routes/web.php                      (modify: add ledger routes inside auth group)

tests/Feature/
  TransactionTest.php
  BudgetTest.php
  BalanceServiceTest.php
```

---

## Task 1: Migrations

- [ ] **Generate both migration files**

```bash
php artisan make:migration create_transactions_table --no-interaction
php artisan make:migration create_budgets_table --no-interaction
```

- [ ] **Fill in `create_transactions_table`**

Column order: id → FKs (account_id, category_id, created_by) → data (amount, type, transfer_link_id, transaction_date, description) → deleted_at → timestamps. No DB enum — `$table->string('type')`. Indexes match the spec.

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
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->decimal('amount', 15, 2);
            $table->string('type');            // PHP enum: TransactionType
            $table->uuid('transfer_link_id')->nullable();
            $table->date('transaction_date');
            $table->string('description')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('account_id');
            $table->index('category_id');
            $table->index('created_by');
            $table->index(['account_id', 'transaction_date']);
            $table->index('transfer_link_id');
            $table->index(['account_id', 'type', 'transaction_date']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
```

- [ ] **Fill in `create_budgets_table`**

Column order: id → FKs (account_id, category_id) → data (limit_amount, year, month) → deleted_at → timestamps. Unique constraint on `(account_id, category_id, year, month)`.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->decimal('limit_amount', 15, 2);
            $table->smallInteger('year')->unsigned();
            $table->tinyInteger('month')->unsigned();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['account_id', 'category_id', 'year', 'month']);
            $table->index(['account_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
```

- [ ] **Run migrations**

```bash
php artisan migrate --no-interaction
```

Expected: 2 new migrations applied with no errors.

---

## Task 2: PHP Enum — TransactionType

- [ ] **Create `app/Enums/TransactionType.php`**

Five cases. TitleCase keys. No `direction` — direction is unambiguous from `type`.

```php
<?php

namespace App\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Fee = 'fee';

    /**
     * Types that increase the account balance (inflows).
     *
     * @return array<string>
     */
    public static function inflows(): array
    {
        return [self::Income->value, self::TransferIn->value];
    }

    /**
     * Types that decrease the account balance (outflows).
     *
     * @return array<string>
     */
    public static function outflows(): array
    {
        return [self::Expense->value, self::TransferOut->value, self::Fee->value];
    }

    /**
     * Types that count toward budget spend.
     *
     * @return array<string>
     */
    public static function spendTypes(): array
    {
        return [self::Expense->value, self::Fee->value];
    }
}
```

---

## Task 3: Models + Factories

- [ ] **Create `app/Models/Transaction.php`**

```bash
php artisan make:model Transaction -f --no-interaction
```

```php
<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    protected $casts = [
        'type'             => TransactionType::class,
        'amount'           => 'decimal:2',
        'transaction_date' => 'date',
    ];

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
}
```

- [ ] **Fill `database/factories/TransactionFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'account_id'       => Account::factory(),
            'category_id'      => null,
            'created_by'       => User::factory(),
            'amount'           => fake()->randomFloat(2, 1, 10_000_000),
            'type'             => TransactionType::Expense->value,
            'transfer_link_id' => null,
            'transaction_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'description'      => fake()->optional(0.6)->sentence(),
        ];
    }

    public function income(): static
    {
        return $this->state(['type' => TransactionType::Income->value]);
    }

    public function expense(): static
    {
        return $this->state(['type' => TransactionType::Expense->value]);
    }

    public function transferOut(string $linkId): static
    {
        return $this->state([
            'type'             => TransactionType::TransferOut->value,
            'transfer_link_id' => $linkId,
        ]);
    }

    public function transferIn(string $linkId): static
    {
        return $this->state([
            'type'             => TransactionType::TransferIn->value,
            'transfer_link_id' => $linkId,
        ]);
    }

    public function fee(string $linkId): static
    {
        return $this->state([
            'type'             => TransactionType::Fee->value,
            'transfer_link_id' => $linkId,
        ]);
    }

    public function forCategory(int $categoryId): static
    {
        return $this->state(['category_id' => $categoryId]);
    }
}
```

- [ ] **Create `app/Models/Budget.php`**

```bash
php artisan make:model Budget -f --no-interaction
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Budget extends Model
{
    use SoftDeletes;

    protected $casts = [
        'limit_amount' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
```

- [ ] **Fill `database/factories/BudgetFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'account_id'   => Account::factory(),
            'category_id'  => Category::factory(),
            'limit_amount' => fake()->randomFloat(2, 100_000, 5_000_000),
            'year'         => (int) $date->format('Y'),
            'month'        => (int) $date->format('n'),
        ];
    }

    public function forPeriod(int $year, int $month): static
    {
        return $this->state(['year' => $year, 'month' => $month]);
    }
}
```

---

## Task 4: Policies

- [ ] **Create `app/Policies/TransactionPolicy.php`**

A transaction belongs to an account. The account's `AccountPolicy::view` check determines access. Transaction writes require account ownership.

```bash
php artisan make:policy TransactionPolicy --model=Transaction --no-interaction
```

```php
<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function viewAny(User $user, Account $account): bool
    {
        return $user->can('view', $account);
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->can('view', $transaction->account);
    }

    public function create(User $user, Account $account): bool
    {
        return $user->can('view', $account);
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $transaction->account->owner_id === $user->id;
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $transaction->account->owner_id === $user->id;
    }
}
```

- [ ] **Create `app/Policies/BudgetPolicy.php`**

```bash
php artisan make:policy BudgetPolicy --model=Budget --no-interaction
```

```php
<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    public function viewAny(User $user, Account $account): bool
    {
        return $user->can('view', $account);
    }

    public function create(User $user, Account $account): bool
    {
        return $account->owner_id === $user->id;
    }

    public function update(User $user, Budget $budget): bool
    {
        return $budget->account->owner_id === $user->id;
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $budget->account->owner_id === $user->id;
    }
}
```

---

## Task 5: Form Requests

- [ ] **Create `app/Http/Requests/StoreTransactionRequest.php`**

The user submits `type=transfer` for the UI convenience; the service maps this to `transfer_out`/`transfer_in` internally. Validation accepts the five enum values **plus** `transfer` as a UI alias — the service converts it.

```bash
php artisan make:request StoreTransactionRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // 'transfer' is a UI alias; service converts it to transfer_out + transfer_in
        $typeValues = array_merge(
            array_column(TransactionType::cases(), 'value'),
            ['transfer']
        );

        return [
            'type'                   => ['required', 'string', Rule::in($typeValues)],
            'amount'                 => ['required', 'numeric', 'min:0.01'],
            'transaction_date'       => ['required', 'date', 'before_or_equal:today'],
            'category_id'            => ['nullable', 'integer', 'exists:categories,id'],
            'description'            => ['nullable', 'string', 'max:500'],
            'destination_account_id' => [
                Rule::requiredIf(fn () => $this->input('type') === 'transfer'),
                'nullable',
                'integer',
                'exists:accounts,id',
                'different:account_id',
            ],
            'fee_amount'             => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}
```

- [ ] **Create `app/Http/Requests/UpdateTransactionRequest.php`**

Update does not allow changing the type to or from `transfer` — transfers are immutable as a group.

```bash
php artisan make:request UpdateTransactionRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'category_id'      => ['nullable', 'integer', 'exists:categories,id'],
            'description'      => ['nullable', 'string', 'max:500'],
        ];
    }
}
```

- [ ] **Create `app/Http/Requests/StoreBudgetRequest.php`**

```bash
php artisan make:request StoreBudgetRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id'  => ['required', 'integer', 'exists:categories,id'],
            'limit_amount' => ['required', 'numeric', 'min:0.01'],
            'year'         => ['required', 'integer', 'min:2000', 'max:2100'],
            'month'        => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }
}
```

- [ ] **Create `app/Http/Requests/UpdateBudgetRequest.php`**

```bash
php artisan make:request UpdateBudgetRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'limit_amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
```

---

## Task 6: Services

- [ ] **Create `app/Services/BalanceService.php`**

Cache-aware, single SQL aggregate entry point. Cache key: `balance:account:{id}`. Tagged: `account:{id}`.

```bash
php artisan make:class Services/BalanceService --no-interaction
```

```php
<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BalanceService
{
    /**
     * Return the current balance for the given account.
     *
     * Balance = initial_balance + SUM(inflows) - SUM(outflows).
     * Result is cached per-account and invalidated by TransactionSaved / TransactionDeleted listeners.
     */
    public function forAccount(Account $account): string
    {
        $cacheKey = "balance:account:{$account->id}";

        return Cache::tags(["account:{$account->id}"])->rememberForever($cacheKey, function () use ($account): string {
            $balance = DB::table('accounts')
                ->selectRaw(
                    'accounts.initial_balance + COALESCE(SUM(CASE
                        WHEN t.type IN (?, ?) THEN t.amount
                        WHEN t.type IN (?, ?, ?) THEN -t.amount
                        ELSE 0
                    END), 0) AS balance',
                    ['income', 'transfer_in', 'expense', 'transfer_out', 'fee']
                )
                ->leftJoin('transactions as t', function ($join) use ($account): void {
                    $join->on('t.account_id', '=', 'accounts.id')
                        ->whereNull('t.deleted_at');
                })
                ->where('accounts.id', $account->id)
                ->groupBy('accounts.id', 'accounts.initial_balance')
                ->value('balance');

            return (string) ($balance ?? $account->initial_balance);
        });
    }
}
```

- [ ] **Create `app/Services/TransactionService.php`**

Fires `TransactionSaved` / `TransactionDeleted` events — never touches cache directly.

```bash
php artisan make:class Services/TransactionService --no-interaction
```

```php
<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Events\TransactionDeleted;
use App\Events\TransactionSaved;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionService
{
    public function create(Account $account, User $creator, array $data): Transaction
    {
        $transaction = Transaction::create([
            'account_id'       => $account->id,
            'created_by'       => $creator->id,
            'amount'           => $data['amount'],
            'type'             => $data['type'],
            'transfer_link_id' => $data['transfer_link_id'] ?? null,
            'transaction_date' => $data['transaction_date'],
            'category_id'      => $data['category_id'] ?? null,
            'description'      => $data['description'] ?? null,
        ]);

        TransactionSaved::dispatch($transaction);

        return $transaction;
    }

    public function update(Transaction $transaction, array $data): Transaction
    {
        $transaction->update([
            'amount'           => $data['amount'],
            'transaction_date' => $data['transaction_date'],
            'category_id'      => $data['category_id'] ?? null,
            'description'      => $data['description'] ?? null,
        ]);

        TransactionSaved::dispatch($transaction->fresh());

        return $transaction->fresh();
    }

    public function softDelete(Transaction $transaction): void
    {
        // If this is part of a transfer group, soft-delete all rows sharing the link
        if ($transaction->transfer_link_id) {
            $linked = Transaction::where('transfer_link_id', $transaction->transfer_link_id)->get();

            foreach ($linked as $linked_tx) {
                $linked_tx->delete();
                TransactionDeleted::dispatch($linked_tx);
            }

            return;
        }

        $transaction->delete();
        TransactionDeleted::dispatch($transaction);
    }

    /**
     * Create a transfer — always 2 or 3 rows in a single DB transaction.
     * 1. transfer_out on source account
     * 2. transfer_in on destination account
     * 3. fee on source account (only if fee_amount > 0)
     *
     * All rows share the same transfer_link_id.
     */
    public function createTransfer(
        Account $sourceAccount,
        Account $destinationAccount,
        User $creator,
        float $amount,
        string $transactionDate,
        ?float $feeAmount,
        ?string $description
    ): Transaction {
        $linkId = (string) Str::uuid();

        return DB::transaction(function () use (
            $sourceAccount,
            $destinationAccount,
            $creator,
            $amount,
            $transactionDate,
            $feeAmount,
            $description,
            $linkId,
        ): Transaction {
            $outflow = $this->create($sourceAccount, $creator, [
                'amount'           => $amount,
                'type'             => TransactionType::TransferOut->value,
                'transfer_link_id' => $linkId,
                'transaction_date' => $transactionDate,
                'description'      => $description,
            ]);

            $this->create($destinationAccount, $creator, [
                'amount'           => $amount,
                'type'             => TransactionType::TransferIn->value,
                'transfer_link_id' => $linkId,
                'transaction_date' => $transactionDate,
                'description'      => $description,
            ]);

            if ($feeAmount !== null && $feeAmount > 0) {
                $this->create($sourceAccount, $creator, [
                    'amount'           => $feeAmount,
                    'type'             => TransactionType::Fee->value,
                    'transfer_link_id' => $linkId,
                    'transaction_date' => $transactionDate,
                    'description'      => 'Transfer fee',
                ]);
            }

            return $outflow;
        });
    }
}
```

- [ ] **Create `app/Services/BudgetService.php`**

`calculateStatus` runs a single SQL aggregate — never fetches a collection.

```bash
php artisan make:class Services/BudgetService --no-interaction
```

```php
<?php

namespace App\Services;

use App\Data\BudgetStatusData;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    /**
     * Create or update a budget for the given account + category + period.
     * Unique constraint on (account_id, category_id, year, month) — use upsert.
     */
    public function upsert(Account $account, array $data): Budget
    {
        $budget = Budget::withTrashed()->updateOrCreate(
            [
                'account_id'  => $account->id,
                'category_id' => $data['category_id'],
                'year'        => $data['year'],
                'month'       => $data['month'],
            ],
            [
                'limit_amount' => $data['limit_amount'],
                'deleted_at'   => null,
            ]
        );

        return $budget->fresh();
    }

    public function update(Budget $budget, array $data): Budget
    {
        $budget->update(['limit_amount' => $data['limit_amount']]);

        return $budget->fresh();
    }

    public function softDelete(Budget $budget): void
    {
        $budget->delete();
    }

    /**
     * Compute budget status for a given account + category + period.
     * Spend is a single SQL SUM — never a PHP loop.
     *
     * @return BudgetStatusData
     */
    public function calculateStatus(Account $account, Category $category, int $year, int $month): BudgetStatusData
    {
        $budget = Budget::where('account_id', $account->id)
            ->where('category_id', $category->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $limitAmount = $budget ? (float) $budget->limit_amount : 0;

        $spend = (float) DB::table('transactions')
            ->selectRaw('COALESCE(SUM(amount), 0) AS spend')
            ->where('account_id', $account->id)
            ->where('category_id', $category->id)
            ->whereIn('type', ['expense', 'fee'])
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->whereNull('deleted_at')
            ->value('spend');

        $percentage = $limitAmount > 0 ? round(($spend / $limitAmount) * 100, 2) : 0;

        $status = match (true) {
            $percentage >= 100 => 'over_budget',
            $percentage >= 80  => 'at_risk',
            default            => 'on_track',
        };

        return new BudgetStatusData(
            limit_amount: (string) $limitAmount,
            spend: (string) $spend,
            percentage: $percentage,
            status: $status,
        );
    }
}
```

---

## Task 7: Events & Listeners

- [ ] **Create `app/Events/TransactionSaved.php`**

```bash
php artisan make:event TransactionSaved --no-interaction
```

```php
<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionSaved
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Transaction $transaction) {}
}
```

- [ ] **Create `app/Events/TransactionDeleted.php`**

```bash
php artisan make:event TransactionDeleted --no-interaction
```

```php
<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Transaction $transaction) {}
}
```

- [ ] **Create `app/Listeners/InvalidateAccountBalanceCache.php`**

Handles both `TransactionSaved` and `TransactionDeleted`. Flushes the entire `account:{id}` tag so balance and any future tagged queries are all invalidated.

```bash
php artisan make:listener InvalidateAccountBalanceCache --no-interaction
```

```php
<?php

namespace App\Listeners;

use App\Events\TransactionDeleted;
use App\Events\TransactionSaved;
use Illuminate\Support\Facades\Cache;

class InvalidateAccountBalanceCache
{
    public function handle(TransactionSaved|TransactionDeleted $event): void
    {
        Cache::tags(["account:{$event->transaction->account_id}"])->flush();
    }
}
```

- [ ] **Create `app/Listeners/InvalidateAccountReportCache.php`**

Reserved for future report caching. Registered now so the event → listener wiring is correct when reports are built.

```bash
php artisan make:listener InvalidateAccountReportCache --no-interaction
```

```php
<?php

namespace App\Listeners;

use App\Events\TransactionDeleted;
use App\Events\TransactionSaved;

class InvalidateAccountReportCache
{
    public function handle(TransactionSaved|TransactionDeleted $event): void
    {
        // Reserved: flush report cache tags when the reports spec is implemented.
        // Cache::tags(["reports:account:{$event->transaction->account_id}"])->flush();
    }
}
```

- [ ] **Register event → listener bindings in `app/Providers/AppServiceProvider.php`**

Add to the `boot()` method (or create event discovery via `Event::listen`):

```php
use App\Events\TransactionDeleted;
use App\Events\TransactionSaved;
use App\Listeners\InvalidateAccountBalanceCache;
use App\Listeners\InvalidateAccountReportCache;
use Illuminate\Support\Facades\Event;

// Inside boot():
Event::listen(TransactionSaved::class, InvalidateAccountBalanceCache::class);
Event::listen(TransactionDeleted::class, InvalidateAccountBalanceCache::class);
Event::listen(TransactionSaved::class, InvalidateAccountReportCache::class);
Event::listen(TransactionDeleted::class, InvalidateAccountReportCache::class);
```

---

## Task 8: Data Objects

Only one DTO is needed: `BudgetStatusData`. Transactions and budgets pass as models — Wayfinder generates their TypeScript types via `App.Models.*`.

- [ ] **Create `app/Data/BudgetStatusData.php`**

This is a DTO because it combines model data (`limit_amount`) with a computed value (`spend`, `percentage`, `status`) that cannot be expressed as a single Eloquent model instance.

```php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class BudgetStatusData extends Data
{
    public function __construct(
        public readonly string $limit_amount,
        public readonly string $spend,
        public readonly float $percentage,
        public readonly string $status, // 'on_track' | 'at_risk' | 'over_budget'
    ) {}
}
```

- [ ] **Generate TypeScript types**

```bash
composer generate:ts
```

Expected: `resources/js/wayfinder/` updated with `BudgetStatusData` type.

---

## Task 9: Controllers

- [ ] **Create `app/Http/Controllers/TransactionsController.php`**

`index` paginates transactions for the account. `create` passes the account + user's categories. `store` branches on `type=transfer` vs standard. `edit` passes the transaction + account context.

```bash
php artisan make:controller TransactionsController --no-interaction
```

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\BalanceService;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionsController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly BalanceService $balanceService,
    ) {}

    public function index(Request $request, Account $account): Response
    {
        $this->authorize('viewAny', [Transaction::class, $account]);

        $transactions = Transaction::query()
            ->where('account_id', $account->id)
            ->with('category')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(30);

        return Inertia::render('transactions/index', [
            'account'      => $account,
            'transactions' => $transactions,
            'balance'      => $this->balanceService->forAccount($account),
        ]);
    }

    public function create(Request $request, Account $account): Response
    {
        $this->authorize('create', [Transaction::class, $account]);

        return Inertia::render('transactions/create', [
            'account'    => $account,
            'categories' => $request->user()
                ->categories()
                ->whereNull('parent_id')
                ->with('children')
                ->get(),
            'accounts'   => Account::query()
                ->visibleTo($request->user())
                ->whereNull('archived_at')
                ->where('id', '!=', $account->id)
                ->get(),
        ]);
    }

    public function store(StoreTransactionRequest $request, Account $account): RedirectResponse
    {
        $this->authorize('create', [Transaction::class, $account]);

        $data = $request->validated();

        if ($data['type'] === 'transfer') {
            $destinationAccount = Account::findOrFail($data['destination_account_id']);

            $this->transactionService->createTransfer(
                sourceAccount:      $account,
                destinationAccount: $destinationAccount,
                creator:            $request->user(),
                amount:             (float) $data['amount'],
                transactionDate:    $data['transaction_date'],
                feeAmount:          isset($data['fee_amount']) ? (float) $data['fee_amount'] : null,
                description:        $data['description'] ?? null,
            );
        } else {
            $this->transactionService->create($account, $request->user(), $data);
        }

        return to_route('transactions.index', $account)->with('message', 'Transaction saved.');
    }

    public function edit(Request $request, Account $account, Transaction $transaction): Response
    {
        $this->authorize('update', $transaction);

        return Inertia::render('transactions/edit', [
            'account'     => $account,
            'transaction' => $transaction->load('category'),
            'categories'  => $request->user()
                ->categories()
                ->whereNull('parent_id')
                ->with('children')
                ->get(),
        ]);
    }

    public function update(UpdateTransactionRequest $request, Account $account, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $this->transactionService->update($transaction, $request->validated());

        return to_route('transactions.index', $account)->with('message', 'Transaction updated.');
    }

    public function destroy(Account $account, Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $this->transactionService->softDelete($transaction);

        return to_route('transactions.index', $account)->with('message', 'Transaction deleted.');
    }
}
```

- [ ] **Create `app/Http/Controllers/BudgetsController.php`**

`index` passes budgets with computed status for the current month. `store` calls `BudgetService::upsert`.

```bash
php artisan make:controller BudgetsController --no-interaction
```

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBudgetRequest;
use App\Http\Requests\UpdateBudgetRequest;
use App\Models\Account;
use App\Models\Budget;
use App\Services\BudgetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetsController extends Controller
{
    public function __construct(private readonly BudgetService $budgetService) {}

    public function index(Request $request, Account $account): Response
    {
        $this->authorize('viewAny', [Budget::class, $account]);

        $year  = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $budgets = Budget::query()
            ->where('account_id', $account->id)
            ->where('year', $year)
            ->where('month', $month)
            ->with('category')
            ->get();

        // Attach computed status to each budget — single SQL per budget (aggregate query in BudgetService)
        $budgetsWithStatus = $budgets->map(function (Budget $budget) use ($account, $year, $month) {
            return [
                'budget' => $budget,
                'status' => $this->budgetService->calculateStatus($account, $budget->category, $year, $month),
            ];
        });

        return Inertia::render('budgets/index', [
            'account'           => $account,
            'budgets_with_status' => $budgetsWithStatus,
            'year'              => $year,
            'month'             => $month,
            'categories'        => $request->user()
                ->categories()
                ->whereNull('parent_id')
                ->with('children')
                ->get(),
        ]);
    }

    public function store(StoreBudgetRequest $request, Account $account): RedirectResponse
    {
        $this->authorize('create', [Budget::class, $account]);

        $this->budgetService->upsert($account, $request->validated());

        return to_route('budgets.index', $account)->with('message', 'Budget saved.');
    }

    public function update(UpdateBudgetRequest $request, Account $account, Budget $budget): RedirectResponse
    {
        $this->authorize('update', $budget);

        $this->budgetService->update($budget, $request->validated());

        return to_route('budgets.index', $account)->with('message', 'Budget updated.');
    }

    public function destroy(Account $account, Budget $budget): RedirectResponse
    {
        $this->authorize('delete', $budget);

        $this->budgetService->softDelete($budget);

        return to_route('budgets.index', $account)->with('message', 'Budget deleted.');
    }
}
```

---

## Task 10: Routes

- [ ] **Update `routes/web.php` — add Ledger routes inside auth middleware group**

Add the transaction and budget route groups below the existing Foundation routes. Both are nested under `accounts/{account}`.

```php
use App\Http\Controllers\BudgetsController;
use App\Http\Controllers\TransactionsController;

// Inside the auth middleware group:

// Transactions (nested under account)
Route::get('accounts/{account}/transactions', [TransactionsController::class, 'index'])->name('transactions.index');
Route::get('accounts/{account}/transactions/create', [TransactionsController::class, 'create'])->name('transactions.create');
Route::post('accounts/{account}/transactions', [TransactionsController::class, 'store'])->name('transactions.store');
Route::get('accounts/{account}/transactions/{transaction}/edit', [TransactionsController::class, 'edit'])->name('transactions.edit');
Route::put('accounts/{account}/transactions/{transaction}', [TransactionsController::class, 'update'])->name('transactions.update');
Route::delete('accounts/{account}/transactions/{transaction}', [TransactionsController::class, 'destroy'])->name('transactions.destroy');

// Budgets (nested under account)
Route::get('accounts/{account}/budgets', [BudgetsController::class, 'index'])->name('budgets.index');
Route::post('accounts/{account}/budgets', [BudgetsController::class, 'store'])->name('budgets.store');
Route::put('accounts/{account}/budgets/{budget}', [BudgetsController::class, 'update'])->name('budgets.update');
Route::delete('accounts/{account}/budgets/{budget}', [BudgetsController::class, 'destroy'])->name('budgets.destroy');
```

- [ ] **Regenerate Wayfinder typed route functions**

```bash
php artisan wayfinder:generate --no-interaction
```

Expected: `resources/js/wayfinder/` updated with `TransactionsController`, `BudgetsController`, and `TransactionType` enum files.

---

## Task 11: Feature Tests

- [ ] **Create `tests/Feature/TransactionTest.php`**

```bash
php artisan make:test TransactionTest --pest --no-interaction
```

```php
<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Transaction;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createAccountForUser(): array
{
    $user = User::factory()->create();
    $household = Household::factory()->create(['created_by' => $user->id]);
    HouseholdMember::factory()->owner()->create(['household_id' => $household->id, 'user_id' => $user->id]);
    $account = Account::factory()->create(['owner_id' => $user->id, 'household_id' => $household->id]);

    return [$user, $account, $household];
}

it('lists transactions for an account', function (): void {
    [$user, $account] = createAccountForUser();
    Transaction::factory()->count(3)->create(['account_id' => $account->id, 'created_by' => $user->id]);

    $this->actingAs($user)->get(route('transactions.index', $account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('transactions/index')
            ->has('transactions.data', 3)
        );
});

it('stores an income transaction', function (): void {
    [$user, $account] = createAccountForUser();

    $this->actingAs($user)->post(route('transactions.store', $account), [
        'type'             => TransactionType::Income->value,
        'amount'           => 5_000_000,
        'transaction_date' => now()->toDateString(),
        'category_id'      => null,
        'description'      => 'Salary',
    ])->assertRedirect(route('transactions.index', $account));

    expect(Transaction::where('account_id', $account->id)->where('type', TransactionType::Income->value)->exists())->toBeTrue();
});

it('stores an expense transaction', function (): void {
    [$user, $account] = createAccountForUser();
    $category = Category::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('transactions.store', $account), [
        'type'             => TransactionType::Expense->value,
        'amount'           => 150_000,
        'transaction_date' => now()->toDateString(),
        'category_id'      => $category->id,
        'description'      => 'Groceries',
    ])->assertRedirect();

    expect(Transaction::where('account_id', $account->id)->where('type', TransactionType::Expense->value)->exists())->toBeTrue();
});

it('creates a transfer with 2 rows sharing the same transfer_link_id', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id, 'household_id' => $sourceAccount->household_id]);

    $this->actingAs($user)->post(route('transactions.store', $sourceAccount), [
        'type'                   => 'transfer',
        'amount'                 => 1_000_000,
        'transaction_date'       => now()->toDateString(),
        'destination_account_id' => $destAccount->id,
        'description'            => 'Savings move',
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
        'type'                   => 'transfer',
        'amount'                 => 500_000,
        'transaction_date'       => now()->toDateString(),
        'destination_account_id' => $destAccount->id,
        'fee_amount'             => 6_500,
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
        'type'                   => 'transfer',
        'amount'                 => 200_000,
        'transaction_date'       => now()->toDateString(),
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

    expect($transaction->fresh())->toBeNull();
    expect(Transaction::withTrashed()->find($transaction->id))->not->toBeNull();
});
```

- [ ] **Create `tests/Feature/BudgetTest.php`**

```bash
php artisan make:test BudgetTest --pest --no-interaction
```

```php
<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createBudgetSetup(): array
{
    $user = User::factory()->create();
    $household = Household::factory()->create(['created_by' => $user->id]);
    HouseholdMember::factory()->owner()->create(['household_id' => $household->id, 'user_id' => $user->id]);
    $account = Account::factory()->create(['owner_id' => $user->id, 'household_id' => $household->id]);
    $category = Category::factory()->create(['user_id' => $user->id]);

    return [$user, $account, $category];
}

it('lists budgets for an account and period', function (): void {
    [$user, $account, $category] = createBudgetSetup();
    Budget::factory()->forPeriod(2026, 6)->create(['account_id' => $account->id, 'category_id' => $category->id]);

    $this->actingAs($user)->get(route('budgets.index', ['account' => $account->id, 'year' => 2026, 'month' => 6]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('budgets/index')
            ->has('budgets_with_status', 1)
        );
});

it('creates a budget via store', function (): void {
    [$user, $account, $category] = createBudgetSetup();

    $this->actingAs($user)->post(route('budgets.store', $account), [
        'category_id'  => $category->id,
        'limit_amount' => 1_500_000,
        'year'         => 2026,
        'month'        => 6,
    ])->assertRedirect(route('budgets.index', $account));

    expect(Budget::where('account_id', $account->id)
        ->where('category_id', $category->id)
        ->where('year', 2026)
        ->where('month', 6)
        ->exists()
    )->toBeTrue();
});

it('upserts a budget when the same period already exists', function (): void {
    [$user, $account, $category] = createBudgetSetup();
    Budget::factory()->forPeriod(2026, 6)->create([
        'account_id'   => $account->id,
        'category_id'  => $category->id,
        'limit_amount' => 1_000_000,
    ]);

    $this->actingAs($user)->post(route('budgets.store', $account), [
        'category_id'  => $category->id,
        'limit_amount' => 2_000_000,
        'year'         => 2026,
        'month'        => 6,
    ])->assertRedirect();

    expect(Budget::where('account_id', $account->id)
        ->where('category_id', $category->id)
        ->where('year', 2026)
        ->where('month', 6)
        ->value('limit_amount')
    )->toBe('2000000.00');
});

it('soft-deletes a budget', function (): void {
    [$user, $account, $category] = createBudgetSetup();
    $budget = Budget::factory()->create(['account_id' => $account->id, 'category_id' => $category->id]);

    $this->actingAs($user)->delete(route('budgets.destroy', [$account, $budget]))
        ->assertRedirect();

    expect($budget->fresh())->toBeNull();
    expect(Budget::withTrashed()->find($budget->id))->not->toBeNull();
});

it('prevents another user from deleting a budget', function (): void {
    [$user, $account, $category] = createBudgetSetup();
    $budget = Budget::factory()->create(['account_id' => $account->id, 'category_id' => $category->id]);
    $other = User::factory()->create();

    $this->actingAs($other)->delete(route('budgets.destroy', [$account, $budget]))
        ->assertForbidden();
});
```

- [ ] **Create `tests/Feature/BalanceServiceTest.php`**

```bash
php artisan make:test BalanceServiceTest --pest --no-interaction
```

```php
<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BalanceService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function setupBalanceAccount(float $initialBalance = 0): array
{
    $user = User::factory()->create();
    $household = Household::factory()->create(['created_by' => $user->id]);
    HouseholdMember::factory()->owner()->create(['household_id' => $household->id, 'user_id' => $user->id]);
    $account = Account::factory()->create([
        'owner_id'        => $user->id,
        'household_id'    => $household->id,
        'initial_balance' => $initialBalance,
    ]);

    return [$user, $account];
}

it('returns initial_balance when there are no transactions', function (): void {
    [, $account] = setupBalanceAccount(1_000_000);
    $service = app(BalanceService::class);

    expect((float) $service->forAccount($account))->toBe(1_000_000.0);
});

it('adds income to initial balance', function (): void {
    [$user, $account] = setupBalanceAccount(1_000_000);
    Transaction::factory()->income()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount'     => 500_000,
    ]);

    $service = app(BalanceService::class);
    expect((float) $service->forAccount($account))->toBe(1_500_000.0);
});

it('subtracts expense from initial balance', function (): void {
    [$user, $account] = setupBalanceAccount(1_000_000);
    Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount'     => 250_000,
    ]);

    $service = app(BalanceService::class);
    expect((float) $service->forAccount($account))->toBe(750_000.0);
});

it('computes balance correctly across mixed transaction types', function (): void {
    [$user, $account] = setupBalanceAccount(500_000);

    // +1_000_000 income, -300_000 expense, -50_000 fee → net +650_000 → total 1_150_000
    Transaction::factory()->income()->create(['account_id' => $account->id, 'created_by' => $user->id, 'amount' => 1_000_000]);
    Transaction::factory()->expense()->create(['account_id' => $account->id, 'created_by' => $user->id, 'amount' => 300_000]);
    Transaction::factory()->fee(str(\Illuminate\Support\Str::uuid()))->create(['account_id' => $account->id, 'created_by' => $user->id, 'amount' => 50_000]);

    $service = app(BalanceService::class);
    expect((float) $service->forAccount($account))->toBe(1_150_000.0);
});

it('excludes soft-deleted transactions from balance', function (): void {
    [$user, $account] = setupBalanceAccount(1_000_000);
    $transaction = Transaction::factory()->income()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount'     => 500_000,
    ]);
    $transaction->delete();

    $service = app(BalanceService::class);
    // Cache may have been set before delete; clear it for this test
    \Illuminate\Support\Facades\Cache::flush();
    expect((float) $service->forAccount($account))->toBe(1_000_000.0);
});
```

- [ ] **Run feature tests**

```bash
php artisan test --compact --filter="TransactionTest|BudgetTest|BalanceServiceTest"
```

Expected: All tests pass. Fix any failures before proceeding.

---

## Task 12: PHP Formatting

- [ ] **Run Pint formatter on all modified PHP files**

```bash
vendor/bin/pint --dirty --format agent
```

Expected: Files reformatted to project style with no errors.

---

## Task 13: Commit

- [ ] **Stage all new and modified files**

```bash
git add database/migrations/ app/Enums/TransactionType.php app/Models/Transaction.php app/Models/Budget.php database/factories/TransactionFactory.php database/factories/BudgetFactory.php app/Policies/TransactionPolicy.php app/Policies/BudgetPolicy.php app/Http/Requests/ app/Services/TransactionService.php app/Services/BudgetService.php app/Services/BalanceService.php app/Events/ app/Listeners/ app/Data/BudgetStatusData.php app/Http/Controllers/TransactionsController.php app/Http/Controllers/BudgetsController.php app/Providers/AppServiceProvider.php routes/web.php tests/Feature/TransactionTest.php tests/Feature/BudgetTest.php tests/Feature/BalanceServiceTest.php resources/js/wayfinder/
```

- [ ] **Commit**

```bash
git commit -m "$(cat <<'EOF'
feat(ledger): add transactions and budgets backend

Implements TransactionType enum, Transaction + Budget models, policies,
form requests, TransactionService (with transfer flow), BudgetService,
BalanceService (SQL aggregate + cache), TransactionSaved/Deleted events,
InvalidateAccountBalanceCache listener, BudgetStatusData DTO, and
TransactionsController + BudgetsController with nested account routes.

Co-Authored-By: Claude Sonnet 4.6 (1M context) <noreply@anthropic.com>
EOF
)"
```
