# Automation — Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the complete backend for Transaction Presets ("templates") and Recurring Presets — every migration, model, policy, service, event, listener, Artisan command, controller, and route that the Automation spec defines.

**Architecture:** Service pattern throughout — `TransactionPresetService` and `RecurringPresetService` own all logic; controllers are thin dispatchers. `RecurringPresetExecuted` event fires after each auto-generated transaction; `InvalidateAccountBalanceCache` listener handles cache invalidation. The daily Artisan command `presets:run-recurring` calls `RecurringPresetService::runDue()`, which wraps each preset execution in a try/catch so one failure never blocks the rest. No DB enums — string columns + PHP-backed enum casts on both models.

**Tech Stack:** PHP 8.4, Laravel 13, Pest 4, Inertia v3

**Depends on:** Foundation and Ledger specs fully implemented. (`TransactionService::create()` must exist — called by `RecurringPresetService::runDue()`.)

---

## File Map

```
database/migrations/
  *_create_transaction_presets_table.php
  *_create_transaction_recurring_presets_table.php

app/Enums/
  TransactionPresetType.php
  RecurringFrequency.php

app/Models/
  TransactionPreset.php
  TransactionRecurringPreset.php

database/factories/
  TransactionPresetFactory.php
  TransactionRecurringPresetFactory.php

app/Policies/
  TransactionPresetPolicy.php
  RecurringPresetPolicy.php

app/Http/Requests/
  StoreTransactionPresetRequest.php
  UpdateTransactionPresetRequest.php
  StoreRecurringPresetRequest.php
  UpdateRecurringPresetRequest.php

app/Services/
  TransactionPresetService.php
  RecurringPresetService.php

app/Events/
  RecurringPresetExecuted.php

app/Listeners/
  InvalidateAccountBalanceCache.php

app/Console/Commands/
  RunRecurringPresets.php

app/Http/Controllers/
  TransactionPresetsController.php
  RecurringPresetsController.php

routes/web.php             (modify: add automation routes)
routes/console.php         (modify: register daily schedule)

tests/Feature/
  TransactionPresetTest.php
  RecurringPresetTest.php
  RunRecurringPresetsCommandTest.php
```

---

## Task 1: Migrations

- [ ] **Generate both migration files**

```bash
php artisan make:migration create_transaction_presets_table --no-interaction
php artisan make:migration create_transaction_recurring_presets_table --no-interaction
```

- [ ] **Fill in `create_transaction_presets_table`**

Add the enum import at the top of the migration class:

```php
use App\Enums\TransactionPresetType;
```

```php
public function up(): void
{
    Schema::create('transaction_presets', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->foreignId('default_category_id')->nullable()->constrained('categories')->nullOnDelete();
        $table->foreignId('default_source_account_id')->nullable()->constrained('accounts')->nullOnDelete();
        $table->foreignId('default_destination_account_id')->nullable()->constrained('accounts')->nullOnDelete();
        $table->string('name');
        $table->string('type')->default(TransactionPresetType::Expense->value);
        $table->decimal('default_amount', 15, 2)->nullable();
        $table->string('default_description')->nullable();
        $table->decimal('default_transfer_fee', 15, 2)->nullable();
        $table->softDeletes();
        $table->timestamps();

        $table->index('user_id');
        $table->index('deleted_at');
    });
}

public function down(): void
{
    Schema::dropIfExists('transaction_presets');
}
```

- [ ] **Fill in `create_transaction_recurring_presets_table`**

Add the enum import at the top of the migration class:

```php
use App\Enums\RecurringFrequency;
```

```php
public function up(): void
{
    Schema::create('transaction_recurring_presets', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('account_id')->constrained()->cascadeOnDelete();
        $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
        $table->foreignId('created_by')->constrained('users');
        $table->string('name');
        $table->string('type');                    // PHP enum: income, expense
        $table->string('frequency');               // PHP enum: daily, weekly, fortnightly, monthly, yearly
        $table->decimal('amount', 15, 2);
        $table->string('description')->nullable();
        $table->date('next_run_date');
        $table->date('recurrence_end_date')->nullable();
        $table->date('last_run_date')->nullable();
        $table->boolean('is_active')->default(true);
        $table->softDeletes();
        $table->timestamps();

        $table->index('account_id');
        $table->index('created_by');
        $table->index('deleted_at');
        $table->index(['next_run_date', 'is_active']); // critical for daily command query
    });
}

public function down(): void
{
    Schema::dropIfExists('transaction_recurring_presets');
}
```

- [ ] **Run migrations**

```bash
php artisan migrate --no-interaction
```

Expected: 2 new migrations applied with no errors.

---

## Task 2: PHP Enums

- [ ] **Create `app/Enums/TransactionPresetType.php`**

```php
<?php

namespace App\Enums;

enum TransactionPresetType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
}
```

- [ ] **Create `app/Enums/RecurringFrequency.php`**

```php
<?php

namespace App\Enums;

enum RecurringFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Fortnightly = 'fortnightly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
```

---

## Task 3: Models + Factories

- [ ] **Create `app/Models/TransactionPreset.php`**

```bash
php artisan make:model TransactionPreset -f --no-interaction
```

```php
<?php

namespace App\Models;

use App\Enums\TransactionPresetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionPreset extends Model
{
    use SoftDeletes;

    protected $casts = [
        'type' => TransactionPresetType::class,
        'default_amount' => 'decimal:2',
        'default_transfer_fee' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function defaultCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'default_category_id');
    }

    public function defaultSourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_source_account_id');
    }

    public function defaultDestinationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_destination_account_id');
    }
}
```

- [ ] **Fill `database/factories/TransactionPresetFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Enums\TransactionPresetType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionPresetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'default_category_id' => null,
            'default_source_account_id' => null,
            'default_destination_account_id' => null,
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(TransactionPresetType::cases())->value,
            'default_amount' => fake()->randomFloat(2, 10_000, 1_000_000),
            'default_description' => fake()->optional()->sentence(),
            'default_transfer_fee' => null,
        ];
    }

    public function income(): static
    {
        return $this->state(['type' => TransactionPresetType::Income->value]);
    }

    public function expense(): static
    {
        return $this->state(['type' => TransactionPresetType::Expense->value]);
    }

    public function transfer(): static
    {
        return $this->state([
            'type' => TransactionPresetType::Transfer->value,
            'default_transfer_fee' => fake()->randomFloat(2, 0, 50_000),
        ]);
    }
}
```

- [ ] **Create `app/Models/TransactionRecurringPreset.php`**

```bash
php artisan make:model TransactionRecurringPreset -f --no-interaction
```

```php
<?php

namespace App\Models;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionPresetType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class TransactionRecurringPreset extends Model
{
    use SoftDeletes;

    protected $casts = [
        'type' => TransactionPresetType::class,
        'frequency' => RecurringFrequency::class,
        'amount' => 'decimal:2',
        'next_run_date' => 'date',
        'recurrence_end_date' => 'date',
        'last_run_date' => 'date',
        'is_active' => 'boolean',
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

    /**
     * Advance next_run_date from the given date based on frequency.
     */
    public function advanceNextRunDate(Carbon $from): Carbon
    {
        return match ($this->frequency) {
            RecurringFrequency::Daily       => $from->copy()->addDay(),
            RecurringFrequency::Weekly      => $from->copy()->addWeek(),
            RecurringFrequency::Fortnightly => $from->copy()->addWeeks(2),
            RecurringFrequency::Monthly     => $from->copy()->addMonthNoOverflow(),
            RecurringFrequency::Yearly      => $from->copy()->addYear(),
        };
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->where('next_run_date', '<=', today())
            ->where('is_active', true)
            ->whereNull('deleted_at');
    }
}
```

- [ ] **Fill `database/factories/TransactionRecurringPresetFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionPresetType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionRecurringPresetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'category_id' => null,
            'created_by' => User::factory(),
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement([
                TransactionPresetType::Income->value,
                TransactionPresetType::Expense->value,
            ]),
            'frequency' => fake()->randomElement(RecurringFrequency::cases())->value,
            'amount' => fake()->randomFloat(2, 10_000, 5_000_000),
            'description' => fake()->optional()->sentence(),
            'next_run_date' => today()->addDays(fake()->numberBetween(1, 30)),
            'recurrence_end_date' => null,
            'last_run_date' => null,
            'is_active' => true,
        ];
    }

    public function due(): static
    {
        return $this->state(['next_run_date' => today()]);
    }

    public function overdue(): static
    {
        return $this->state(['next_run_date' => today()->subDays(3)]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withEndDate(string $date): static
    {
        return $this->state(['recurrence_end_date' => $date]);
    }

    public function monthly(): static
    {
        return $this->state(['frequency' => RecurringFrequency::Monthly->value]);
    }
}
```

---

## Task 4: Policies

- [ ] **Create `app/Policies/TransactionPresetPolicy.php`**

```bash
php artisan make:policy TransactionPresetPolicy --model=TransactionPreset --no-interaction
```

```php
<?php

namespace App\Policies;

use App\Models\TransactionPreset;
use App\Models\User;

class TransactionPresetPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TransactionPreset $preset): bool
    {
        return $preset->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TransactionPreset $preset): bool
    {
        return $preset->user_id === $user->id;
    }

    public function delete(User $user, TransactionPreset $preset): bool
    {
        return $preset->user_id === $user->id;
    }
}
```

- [ ] **Create `app/Policies/RecurringPresetPolicy.php`**

```bash
php artisan make:policy RecurringPresetPolicy --model=TransactionRecurringPreset --no-interaction
```

```php
<?php

namespace App\Policies;

use App\Models\TransactionRecurringPreset;
use App\Models\User;

class RecurringPresetPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TransactionRecurringPreset $preset): bool
    {
        return $preset->created_by === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TransactionRecurringPreset $preset): bool
    {
        return $preset->created_by === $user->id;
    }

    public function delete(User $user, TransactionRecurringPreset $preset): bool
    {
        return $preset->created_by === $user->id;
    }

    public function toggle(User $user, TransactionRecurringPreset $preset): bool
    {
        return $preset->created_by === $user->id;
    }
}
```

---

## Task 5: Form Requests

- [ ] **Create `app/Http/Requests/StoreTransactionPresetRequest.php`**

```bash
php artisan make:request StoreTransactionPresetRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use App\Enums\TransactionPresetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(TransactionPresetType::class)],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'default_description' => ['nullable', 'string', 'max:255'],
            'default_category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'default_source_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'default_destination_account_id' => [
                'nullable',
                'integer',
                'exists:accounts,id',
                Rule::requiredIf(fn () => $this->input('type') === TransactionPresetType::Transfer->value),
            ],
            'default_transfer_fee' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
```

- [ ] **Create `app/Http/Requests/UpdateTransactionPresetRequest.php`**

```bash
php artisan make:request UpdateTransactionPresetRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use App\Enums\TransactionPresetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(TransactionPresetType::class)],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'default_description' => ['nullable', 'string', 'max:255'],
            'default_category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'default_source_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'default_destination_account_id' => [
                'nullable',
                'integer',
                'exists:accounts,id',
                Rule::requiredIf(fn () => $this->input('type') === TransactionPresetType::Transfer->value),
            ],
            'default_transfer_fee' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
```

- [ ] **Create `app/Http/Requests/StoreRecurringPresetRequest.php`**

```bash
php artisan make:request StoreRecurringPresetRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionPresetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => [
                'required',
                'string',
                Rule::in([TransactionPresetType::Income->value, TransactionPresetType::Expense->value]),
            ],
            'frequency' => ['required', 'string', Rule::enum(RecurringFrequency::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'next_run_date' => ['required', 'date', 'after_or_equal:today'],
            'recurrence_end_date' => ['nullable', 'date', 'after:next_run_date'],
        ];
    }
}
```

- [ ] **Create `app/Http/Requests/UpdateRecurringPresetRequest.php`**

```bash
php artisan make:request UpdateRecurringPresetRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionPresetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecurringPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => [
                'required',
                'string',
                Rule::in([TransactionPresetType::Income->value, TransactionPresetType::Expense->value]),
            ],
            'frequency' => ['required', 'string', Rule::enum(RecurringFrequency::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'next_run_date' => ['required', 'date'],
            'recurrence_end_date' => ['nullable', 'date', 'after:next_run_date'],
        ];
    }
}
```

---

## Task 6: Services

- [ ] **Create `app/Services/TransactionPresetService.php`**

```bash
php artisan make:class Services/TransactionPresetService --no-interaction
```

```php
<?php

namespace App\Services;

use App\Models\TransactionPreset;
use App\Models\User;

class TransactionPresetService
{
    public function create(User $user, array $data): TransactionPreset
    {
        return TransactionPreset::create([
            ...$data,
            'user_id' => $user->id,
        ]);
    }

    public function update(TransactionPreset $preset, array $data): TransactionPreset
    {
        $preset->update($data);

        return $preset->fresh();
    }

    public function softDelete(TransactionPreset $preset): void
    {
        $preset->delete();
    }
}
```

- [ ] **Create `app/Services/RecurringPresetService.php`**

```bash
php artisan make:class Services/RecurringPresetService --no-interaction
```

```php
<?php

namespace App\Services;

use App\Events\RecurringPresetExecuted;
use App\Models\TransactionRecurringPreset;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecurringPresetService
{
    public function __construct(private readonly TransactionService $transactionService) {}

    public function create(User $user, array $data): TransactionRecurringPreset
    {
        return TransactionRecurringPreset::create([
            ...$data,
            'created_by' => $user->id,
        ]);
    }

    public function update(TransactionRecurringPreset $preset, array $data): TransactionRecurringPreset
    {
        $preset->update($data);

        return $preset->fresh();
    }

    public function softDelete(TransactionRecurringPreset $preset): void
    {
        $preset->delete();
    }

    public function toggle(TransactionRecurringPreset $preset, bool $active): TransactionRecurringPreset
    {
        $preset->update(['is_active' => $active]);

        return $preset->fresh();
    }

    /**
     * Execute all due recurring presets. Called by the daily Artisan command.
     * Each preset is wrapped in its own DB transaction + try/catch so a single
     * failure never blocks the rest.
     *
     * If the command runs after a missed date (e.g. server was down), exactly
     * ONE transaction is generated and next_run_date advances from today — no backfill.
     *
     * @return array{executed: int, failed: int}
     */
    public function runDue(): array
    {
        $executed = 0;
        $failed = 0;

        $duePresets = TransactionRecurringPreset::due()->with('account')->get();

        foreach ($duePresets as $preset) {
            try {
                DB::transaction(function () use ($preset): void {
                    $today = today();

                    // Create the transaction via the Ledger TransactionService
                    $transaction = $this->transactionService->create([
                        'account_id' => $preset->account_id,
                        'category_id' => $preset->category_id,
                        'user_id' => $preset->created_by,
                        'type' => $preset->type->value,
                        'amount' => $preset->amount,
                        'description' => $preset->description ?? $preset->name,
                        'date' => $today,
                    ]);

                    // Advance the schedule from today (no backfill)
                    $newNextRunDate = $preset->advanceNextRunDate($today);

                    $updates = [
                        'last_run_date' => $today,
                        'next_run_date' => $newNextRunDate,
                    ];

                    // Deactivate if we've passed the end date
                    if ($preset->recurrence_end_date !== null && $newNextRunDate->gt($preset->recurrence_end_date)) {
                        $updates['is_active'] = false;
                    }

                    $preset->update($updates);

                    RecurringPresetExecuted::dispatch($preset, $transaction);
                });

                $executed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('RecurringPresetService::runDue failed for preset', [
                    'preset_id' => $preset->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['executed' => $executed, 'failed' => $failed];
    }
}
```

---

## Task 7: Events & Listeners

- [ ] **Create `app/Events/RecurringPresetExecuted.php`**

```bash
php artisan make:event RecurringPresetExecuted --no-interaction
```

```php
<?php

namespace App\Events;

use App\Models\Transaction;
use App\Models\TransactionRecurringPreset;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RecurringPresetExecuted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly TransactionRecurringPreset $preset,
        public readonly Transaction $transaction,
    ) {}
}
```

- [ ] **Create `app/Listeners/InvalidateAccountBalanceCache.php`**

Check first whether the Ledger spec already created this listener (`app/Listeners/InvalidateAccountBalanceCache.php`). If it exists, add a new `handle` overload or update it to also handle `RecurringPresetExecuted`. If it does not yet exist, create it:

```bash
php artisan make:listener InvalidateAccountBalanceCache --no-interaction
```

The listener must handle both the Ledger `TransactionSaved` event and the Automation `RecurringPresetExecuted` event. Write it to extract the `account_id` from whichever event it receives:

```php
<?php

namespace App\Listeners;

use App\Events\RecurringPresetExecuted;
use Illuminate\Support\Facades\Cache;

class InvalidateAccountBalanceCache
{
    /**
     * Handle RecurringPresetExecuted — invalidate the account balance cache
     * for the account that just received a new auto-generated transaction.
     */
    public function handle(RecurringPresetExecuted $event): void
    {
        Cache::tags(['account:' . $event->preset->account_id])->flush();
    }
}
```

> **Note:** If the Ledger spec's `TransactionSaved` listener already exists with the same class name, merge the logic: add a second `handle` method for `RecurringPresetExecuted`, or register a separate listener in `AppServiceProvider`. The key invariant is that `balance:account:{id}` cache is flushed after every auto-generated transaction.

- [ ] **Register the listener in `app/Providers/AppServiceProvider.php`**

```php
use App\Events\RecurringPresetExecuted;
use App\Listeners\InvalidateAccountBalanceCache;
use Illuminate\Support\Facades\Event;

// Inside the boot() method:
Event::listen(RecurringPresetExecuted::class, InvalidateAccountBalanceCache::class);
```

---

## Task 8: Artisan Command

- [ ] **Create `app/Console/Commands/RunRecurringPresets.php`**

```bash
php artisan make:command RunRecurringPresets --no-interaction
```

```php
<?php

namespace App\Console\Commands;

use App\Services\RecurringPresetService;
use Illuminate\Console\Command;

class RunRecurringPresets extends Command
{
    protected $signature = 'presets:run-recurring';

    protected $description = 'Execute all due recurring presets and generate their transactions.';

    public function __construct(private readonly RecurringPresetService $recurringPresetService) {}

    public function handle(): int
    {
        $this->info('Running due recurring presets...');

        $result = $this->recurringPresetService->runDue();

        $this->info("Executed: {$result['executed']}  Failed: {$result['failed']}");

        if ($result['failed'] > 0) {
            $this->warn('Some presets failed. Check the application log for details.');
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
```

- [ ] **Register the daily schedule in `routes/console.php`**

Add to the existing schedule registrations:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('presets:run-recurring')->dailyAt('00:05');
```

---

## Task 9: Controllers

- [ ] **Create `app/Http/Controllers/TransactionPresetsController.php`**

```bash
php artisan make:controller TransactionPresetsController --no-interaction
```

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionPresetRequest;
use App\Http\Requests\UpdateTransactionPresetRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\TransactionPreset;
use App\Services\TransactionPresetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionPresetsController extends Controller
{
    public function __construct(private readonly TransactionPresetService $presetService) {}

    public function index(Request $request): Response
    {
        $presets = TransactionPreset::query()
            ->where('user_id', $request->user()->id)
            ->with(['defaultCategory', 'defaultSourceAccount', 'defaultDestinationAccount'])
            ->get();

        $accounts = Account::query()
            ->visibleTo($request->user())
            ->whereNull('archived_at')
            ->get();

        $categories = Category::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('parent_id')
            ->with('children')
            ->get();

        return Inertia::render('transaction-presets/index', [
            'presets' => $presets,
            'accounts' => $accounts,
            'categories' => $categories,
        ]);
    }

    public function store(StoreTransactionPresetRequest $request): RedirectResponse
    {
        $this->presetService->create($request->user(), $request->validated());

        return back()->with('message', 'Template created.');
    }

    public function update(UpdateTransactionPresetRequest $request, TransactionPreset $preset): RedirectResponse
    {
        $this->authorize('update', $preset);
        $this->presetService->update($preset, $request->validated());

        return back()->with('message', 'Template updated.');
    }

    public function destroy(TransactionPreset $preset): RedirectResponse
    {
        $this->authorize('delete', $preset);
        $this->presetService->softDelete($preset);

        return back()->with('message', 'Template deleted.');
    }
}
```

- [ ] **Create `app/Http/Controllers/RecurringPresetsController.php`**

```bash
php artisan make:controller RecurringPresetsController --no-interaction
```

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecurringPresetRequest;
use App\Http\Requests\UpdateRecurringPresetRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\TransactionRecurringPreset;
use App\Services\RecurringPresetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecurringPresetsController extends Controller
{
    public function __construct(private readonly RecurringPresetService $recurringPresetService) {}

    public function index(Request $request): Response
    {
        $presets = TransactionRecurringPreset::query()
            ->where('created_by', $request->user()->id)
            ->with(['account', 'category'])
            ->orderBy('is_active', 'desc')
            ->orderBy('next_run_date')
            ->get();

        $accounts = Account::query()
            ->visibleTo($request->user())
            ->whereNull('archived_at')
            ->get();

        $categories = Category::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('parent_id')
            ->with('children')
            ->get();

        return Inertia::render('recurring-presets/index', [
            'presets' => $presets,
            'accounts' => $accounts,
            'categories' => $categories,
        ]);
    }

    public function store(StoreRecurringPresetRequest $request): RedirectResponse
    {
        $this->recurringPresetService->create($request->user(), $request->validated());

        return back()->with('message', 'Recurring rule created.');
    }

    public function update(UpdateRecurringPresetRequest $request, TransactionRecurringPreset $preset): RedirectResponse
    {
        $this->authorize('update', $preset);
        $this->recurringPresetService->update($preset, $request->validated());

        return back()->with('message', 'Recurring rule updated.');
    }

    public function destroy(TransactionRecurringPreset $preset): RedirectResponse
    {
        $this->authorize('delete', $preset);
        $this->recurringPresetService->softDelete($preset);

        return back()->with('message', 'Recurring rule deleted.');
    }

    public function toggle(Request $request, TransactionRecurringPreset $preset): RedirectResponse
    {
        $this->authorize('toggle', $preset);
        $this->recurringPresetService->toggle($preset, ! $preset->is_active);

        $message = $preset->fresh()->is_active ? 'Recurring rule activated.' : 'Recurring rule paused.';

        return back()->with('message', $message);
    }
}
```

---

## Task 10: Routes

- [ ] **Update `routes/web.php` — add Automation routes inside the existing `auth` middleware group**

```php
use App\Http\Controllers\RecurringPresetsController;
use App\Http\Controllers\TransactionPresetsController;

// Inside Route::middleware(['auth', 'verified:auth.verification.notice'])->group():

// Transaction Presets (templates)
Route::get('transaction-presets', [TransactionPresetsController::class, 'index'])->name('transaction-presets.index');
Route::post('transaction-presets', [TransactionPresetsController::class, 'store'])->name('transaction-presets.store');
Route::put('transaction-presets/{preset}', [TransactionPresetsController::class, 'update'])->name('transaction-presets.update');
Route::delete('transaction-presets/{preset}', [TransactionPresetsController::class, 'destroy'])->name('transaction-presets.destroy');

// Recurring Presets
Route::get('recurring-presets', [RecurringPresetsController::class, 'index'])->name('recurring-presets.index');
Route::post('recurring-presets', [RecurringPresetsController::class, 'store'])->name('recurring-presets.store');
Route::put('recurring-presets/{preset}', [RecurringPresetsController::class, 'update'])->name('recurring-presets.update');
Route::delete('recurring-presets/{preset}', [RecurringPresetsController::class, 'destroy'])->name('recurring-presets.destroy');
Route::post('recurring-presets/{preset}/toggle', [RecurringPresetsController::class, 'toggle'])->name('recurring-presets.toggle');
```

- [ ] **Regenerate Wayfinder typed route functions**

```bash
php artisan wayfinder:generate --no-interaction
```

Expected: `resources/js/wayfinder/App/Http/Controllers/TransactionPresetsController.ts`, `RecurringPresetsController.ts`, `App/Enums/TransactionPresetType.ts`, and `App/Enums/RecurringFrequency.ts` all created.

---

## Task 11: Feature Tests

- [ ] **Create `tests/Feature/TransactionPresetTest.php`**

```bash
php artisan make:test TransactionPresetTest --pest --no-interaction
```

```php
<?php

use App\Enums\TransactionPresetType;
use App\Models\TransactionPreset;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('lists only the authenticated user presets', function (): void {
    $user = User::factory()->create();
    $mine = TransactionPreset::factory()->create(['user_id' => $user->id]);
    TransactionPreset::factory()->create(); // another user

    $this->actingAs($user)->get(route('transaction-presets.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('transaction-presets/index')
            ->has('presets', 1)
            ->where('presets.0.id', $mine->id)
        );
});

it('stores a new expense preset', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('transaction-presets.store'), [
        'name' => 'Morning Coffee',
        'type' => TransactionPresetType::Expense->value,
        'default_amount' => 30000,
        'default_description' => null,
        'default_category_id' => null,
        'default_source_account_id' => null,
        'default_destination_account_id' => null,
        'default_transfer_fee' => null,
    ])->assertRedirect();

    expect(TransactionPreset::where('name', 'Morning Coffee')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('updates own preset', function (): void {
    $user = User::factory()->create();
    $preset = TransactionPreset::factory()->expense()->create(['user_id' => $user->id]);

    $this->actingAs($user)->put(route('transaction-presets.update', $preset), [
        'name' => 'Updated Name',
        'type' => TransactionPresetType::Expense->value,
        'default_amount' => 50000,
        'default_description' => null,
        'default_category_id' => null,
        'default_source_account_id' => null,
        'default_destination_account_id' => null,
        'default_transfer_fee' => null,
    ])->assertRedirect();

    expect($preset->fresh()->name)->toBe('Updated Name');
});

it('prevents updating another user preset', function (): void {
    $user = User::factory()->create();
    $other = TransactionPreset::factory()->create();

    $this->actingAs($user)->put(route('transaction-presets.update', $other), [
        'name' => 'Hacked',
        'type' => TransactionPresetType::Expense->value,
        'default_amount' => null,
        'default_description' => null,
        'default_category_id' => null,
        'default_source_account_id' => null,
        'default_destination_account_id' => null,
        'default_transfer_fee' => null,
    ])->assertForbidden();
});

it('soft-deletes own preset', function (): void {
    $user = User::factory()->create();
    $preset = TransactionPreset::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->delete(route('transaction-presets.destroy', $preset))
        ->assertRedirect();

    expect($preset->fresh())->toBeNull();
    expect(TransactionPreset::withTrashed()->find($preset->id))->not->toBeNull();
});

it('prevents deleting another user preset', function (): void {
    $user = User::factory()->create();
    $other = TransactionPreset::factory()->create();

    $this->actingAs($user)->delete(route('transaction-presets.destroy', $other))
        ->assertForbidden();
});
```

- [ ] **Create `tests/Feature/RecurringPresetTest.php`**

```bash
php artisan make:test RecurringPresetTest --pest --no-interaction
```

```php
<?php

use App\Enums\RecurringFrequency;
use App\Enums\TransactionPresetType;
use App\Models\Account;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\TransactionRecurringPreset;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createUserWithAccount(): array
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

it('lists only the authenticated user recurring presets', function (): void {
    [$user, $account] = createUserWithAccount();
    $mine = TransactionRecurringPreset::factory()->create([
        'created_by' => $user->id,
        'account_id' => $account->id,
    ]);
    TransactionRecurringPreset::factory()->create(); // another user

    $this->actingAs($user)->get(route('recurring-presets.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('recurring-presets/index')
            ->has('presets', 1)
            ->where('presets.0.id', $mine->id)
        );
});

it('stores a new recurring preset', function (): void {
    [$user, $account] = createUserWithAccount();

    $this->actingAs($user)->post(route('recurring-presets.store'), [
        'account_id' => $account->id,
        'category_id' => null,
        'name' => 'Monthly Rent',
        'type' => TransactionPresetType::Expense->value,
        'frequency' => RecurringFrequency::Monthly->value,
        'amount' => 3_000_000,
        'description' => null,
        'next_run_date' => today()->addDay()->toDateString(),
        'recurrence_end_date' => null,
    ])->assertRedirect();

    expect(TransactionRecurringPreset::where('name', 'Monthly Rent')->where('created_by', $user->id)->exists())->toBeTrue();
});

it('toggles a recurring preset on and off', function (): void {
    [$user, $account] = createUserWithAccount();
    $preset = TransactionRecurringPreset::factory()->create([
        'created_by' => $user->id,
        'account_id' => $account->id,
        'is_active' => true,
    ]);

    // Toggle off
    $this->actingAs($user)->post(route('recurring-presets.toggle', $preset))
        ->assertRedirect();
    expect($preset->fresh()->is_active)->toBeFalse();

    // Toggle on
    $this->actingAs($user)->post(route('recurring-presets.toggle', $preset))
        ->assertRedirect();
    expect($preset->fresh()->is_active)->toBeTrue();
});

it('prevents toggling another user recurring preset', function (): void {
    $user = User::factory()->create();
    $other = TransactionRecurringPreset::factory()->create();

    $this->actingAs($user)->post(route('recurring-presets.toggle', $other))
        ->assertForbidden();
});

it('soft-deletes own recurring preset', function (): void {
    [$user, $account] = createUserWithAccount();
    $preset = TransactionRecurringPreset::factory()->create([
        'created_by' => $user->id,
        'account_id' => $account->id,
    ]);

    $this->actingAs($user)->delete(route('recurring-presets.destroy', $preset))
        ->assertRedirect();

    expect($preset->fresh())->toBeNull();
    expect(TransactionRecurringPreset::withTrashed()->find($preset->id))->not->toBeNull();
});
```

- [ ] **Create `tests/Feature/RunRecurringPresetsCommandTest.php`**

```bash
php artisan make:test RunRecurringPresetsCommandTest --pest --no-interaction
```

```php
<?php

use App\Enums\RecurringFrequency;
use App\Enums\TransactionPresetType;
use App\Events\RecurringPresetExecuted;
use App\Models\Account;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Transaction;
use App\Models\TransactionRecurringPreset;
use App\Models\User;
use Illuminate\Support\Facades\Event;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createPresetForUser(array $overrides = []): TransactionRecurringPreset
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

    return TransactionRecurringPreset::factory()->monthly()->create(array_merge([
        'created_by' => $user->id,
        'account_id' => $account->id,
        'type' => TransactionPresetType::Expense->value,
        'amount' => 500_000,
        'next_run_date' => today(),
        'is_active' => true,
    ], $overrides));
}

it('generates a transaction and advances next_run_date for a due monthly preset', function (): void {
    $preset = createPresetForUser(['next_run_date' => today()]);

    $this->artisan('presets:run-recurring')->assertSuccessful();

    expect(Transaction::where('account_id', $preset->account_id)->count())->toBe(1);

    $preset->refresh();
    expect($preset->last_run_date->toDateString())->toBe(today()->toDateString());
    expect($preset->next_run_date->toDateString())->toBe(today()->addMonthNoOverflow()->toDateString());
});

it('does not generate a transaction for a future preset', function (): void {
    createPresetForUser(['next_run_date' => today()->addDay()]);

    $this->artisan('presets:run-recurring')->assertSuccessful();

    expect(Transaction::count())->toBe(0);
});

it('does not generate a transaction for an inactive preset', function (): void {
    createPresetForUser(['next_run_date' => today(), 'is_active' => false]);

    $this->artisan('presets:run-recurring')->assertSuccessful();

    expect(Transaction::count())->toBe(0);
});

it('deactivates preset when next_run_date exceeds recurrence_end_date', function (): void {
    $endDate = today()->addMonthNoOverflow()->subDay(); // next run would be after end
    $preset = createPresetForUser([
        'next_run_date' => today(),
        'recurrence_end_date' => $endDate->toDateString(),
        'frequency' => RecurringFrequency::Monthly->value,
    ]);

    $this->artisan('presets:run-recurring')->assertSuccessful();

    $preset->refresh();
    expect($preset->is_active)->toBeFalse();
});

it('dispatches RecurringPresetExecuted event after execution', function (): void {
    Event::fake([RecurringPresetExecuted::class]);
    createPresetForUser(['next_run_date' => today()]);

    $this->artisan('presets:run-recurring')->assertSuccessful();

    Event::assertDispatched(RecurringPresetExecuted::class);
});

it('continues processing other presets when one fails', function (): void {
    // Create one that will succeed and one with an invalid account_id that will fail
    $good = createPresetForUser(['next_run_date' => today()]);
    $bad = createPresetForUser(['next_run_date' => today(), 'account_id' => 99999]);

    // We expect the command to complete (not throw) even though one preset fails
    $this->artisan('presets:run-recurring');

    // The good preset should have been executed regardless
    expect(Transaction::where('account_id', $good->account_id)->count())->toBe(1);
});

it('advances weekly preset correctly', function (): void {
    $preset = createPresetForUser([
        'next_run_date' => today(),
        'frequency' => RecurringFrequency::Weekly->value,
    ]);

    $this->artisan('presets:run-recurring')->assertSuccessful();

    $preset->refresh();
    expect($preset->next_run_date->toDateString())->toBe(today()->addWeek()->toDateString());
});

it('advances daily preset correctly', function (): void {
    $preset = createPresetForUser([
        'next_run_date' => today(),
        'frequency' => RecurringFrequency::Daily->value,
    ]);

    $this->artisan('presets:run-recurring')->assertSuccessful();

    $preset->refresh();
    expect($preset->next_run_date->toDateString())->toBe(today()->addDay()->toDateString());
});
```

- [ ] **Run all feature tests**

```bash
php artisan test --compact --filter="TransactionPresetTest|RecurringPresetTest|RunRecurringPresetsCommandTest"
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
git add database/migrations/ app/Enums/TransactionPresetType.php app/Enums/RecurringFrequency.php app/Models/TransactionPreset.php app/Models/TransactionRecurringPreset.php database/factories/TransactionPresetFactory.php database/factories/TransactionRecurringPresetFactory.php app/Policies/TransactionPresetPolicy.php app/Policies/RecurringPresetPolicy.php app/Http/Requests/StoreTransactionPresetRequest.php app/Http/Requests/UpdateTransactionPresetRequest.php app/Http/Requests/StoreRecurringPresetRequest.php app/Http/Requests/UpdateRecurringPresetRequest.php app/Services/TransactionPresetService.php app/Services/RecurringPresetService.php app/Events/RecurringPresetExecuted.php app/Listeners/InvalidateAccountBalanceCache.php app/Providers/AppServiceProvider.php app/Console/Commands/RunRecurringPresets.php app/Http/Controllers/TransactionPresetsController.php app/Http/Controllers/RecurringPresetsController.php routes/web.php routes/console.php tests/Feature/TransactionPresetTest.php tests/Feature/RecurringPresetTest.php tests/Feature/RunRecurringPresetsCommandTest.php resources/js/wayfinder/
```

- [ ] **Commit**

```bash
git commit -m "$(cat <<'EOF'
feat(automation): add transaction presets and recurring preset backend

Implements transaction_presets and transaction_recurring_presets tables,
TransactionPresetType and RecurringFrequency enums, both services, the
RecurringPresetExecuted event, InvalidateAccountBalanceCache listener, the
presets:run-recurring Artisan command (scheduled daily at 00:05), and both
CRUD controllers with policies. Feature tests cover all CRUD actions,
toggle, frequency advancement, end-date deactivation, and fault isolation.

Co-Authored-By: Claude Sonnet 4.6 (1M context) <noreply@anthropic.com>
EOF
)"
```
