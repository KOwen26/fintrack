# Provider CSV Import — Staging & Lifecycle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use better-executing-plans to implement this plan task-by-task — or better-parallel-subagents-executions if this plan splits into independent slices, or superpowers:subagent-driven-development if workflow mode is `remote`. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The staging tables and import lifecycle — upload (parse → default → fingerprint → stage), review-time row editing, bulk approve into real transactions via the existing transaction/transfer services, discard, and the thin HTTP surface.

**Architecture:** Two staging tables (`import_batches`, `import_rows`) hold parsed rows outside the transaction core. `ImportService` orchestrates the lifecycle, resolving the account's parser through `ParserRegistry` (built in the parsing plan). Approval re-validates server-side and materializes through `TransactionService`/`TransferService` so balance observers fire exactly as for hand-entered rows. Spec: `docs/superpowers/specs/2026-10-30-provider-csv-import-design.md`. Companion plans: `2026-10-30-provider-csv-import-parsing.md` (prerequisite), `2026-10-30-provider-csv-import-frontend.md` (depends on this one).

**Tech Stack:** Laravel / PHP 8.4, Eloquent with enum casts, Inertia responses, Pest (integration + feature).

**Workflow Mode:** `direct` — tests are written as part of each task, but no task runs/verifies/commits. The human reviewer handles test runs, formatting, and commits as they choose. (Project AGENTS.md rules still apply to the executor while implementing: run `vendor/bin/pint --dirty --format agent` after PHP edits, `php artisan wayfinder:generate` after backend changes.)

**Complexity:** Medium (all tasks fully concrete; no exploratory steps)

**PREREQUISITE:** the parsing plan (`2026-10-30-provider-csv-import-parsing.md`) must be fully implemented — this plan consumes `ParserRegistry::supports/resolve`, `ParsedStatement`/`ParsedRow`, `ImportFileException`, and the fixtures in `tests/Fixtures/Imports/`. Do not start Task 3 before those exist (Tasks 1–2 are independent of it).

## Global Constraints

- PHP 8.4 style: `final` classes, `readonly` where possible, constructor property promotion, explicit return types, `#[UseFactory]` for model factories, curly braces on all control structures, PHPDoc over inline comments.
- Never `$table->enum()` — string columns + PHP-backed enum casts on models. Enums: backed strings, TitleCase cases (`case Staging = 'staging'`).
- All models use `protected $guarded = [];` and `protected function casts(): array` (method form).
- Migration column order: `id()` → relation FKs → core data columns → status/JSON columns → `timestamps()`. Explicit indexes on every FK and every column used in WHERE/ORDER BY.
- Business logic in `app/Services/` (thin controllers, one service call per action), validation in `app/Http/Requests/`, authorization via Policies + `$this->authorize()`.
- Amounts: `decimal(15, 2)` columns, whole-rupiah values in practice.
- Pest tests: create with `php artisan make:test --pest {Name}` (no directory in name), then move to the target directory; integration tests in `tests/Integration/`, feature tests in `tests/Feature/App/`; run narrowest set via `php artisan test --compact --filter=...`.
- Import rows never become "uncategorized": plain rows default to `Category::OtherIncome` / `Category::OtherExpenses` by flow; transfer-marked rows are the documented exception.

## File Structure

- Create: `app/Enums/ImportBatchStatus.php`
- Create: `database/migrations/2026_10_30_000001_create_import_batches_table.php`, `database/migrations/2026_10_30_000002_create_import_rows_table.php`
- Create: `app/Models/ImportBatch.php`, `app/Models/ImportRow.php`
- Create: `database/factories/ImportBatchFactory.php`, `database/factories/ImportRowFactory.php`
- Create: `app/Services/Import/ImportFingerprint.php`
- Create: `app/Services/ImportService.php`
- Create: `app/Http/Requests/ImportUploadRequest.php`, `app/Http/Requests/UpdateImportRowRequest.php`
- Create: `app/Http/Controllers/ImportController.php`
- Create: `app/Policies/ImportBatchPolicy.php`
- Modify: `routes/web.php`, `app/Http/Controllers/AccountController.php` (`show`), `app/Models/Account.php` (one relation)
- Create tests: `tests/Integration/Models/ImportRowCastsTest.php`, `tests/Unit/Services/Import/ImportFingerprintTest.php`, `tests/Integration/Services/ImportServiceTest.php`, `tests/Feature/App/ImportTest.php`

---

### Task 1: Staging schema — enum, migrations, models, factories

**Complexity:** Medium

**Files:**
- Create: `app/Enums/ImportBatchStatus.php`
- Create: `database/migrations/2026_10_30_000001_create_import_batches_table.php`
- Create: `database/migrations/2026_10_30_000002_create_import_rows_table.php`
- Create: `app/Models/ImportBatch.php`
- Create: `app/Models/ImportRow.php`
- Create: `database/factories/ImportBatchFactory.php`
- Create: `database/factories/ImportRowFactory.php`
- Test: `tests/Integration/Models/ImportRowCastsTest.php`

**Interfaces:**
- Consumes: `App\Enums\Cashflow`, `App\Enums\Category` (existing).
- Produces (used by Tasks 3–6 and the frontend plan):
  - `App\Enums\ImportBatchStatus` with cases `Staging = 'staging'`, `Approved = 'approved'`, `Discarded = 'discarded'`.
  - `App\Models\ImportBatch` with relations `account(): BelongsTo`, `provider(): BelongsTo`, `creator(): BelongsTo`, `rows(): HasMany`, cast `status → ImportBatchStatus`.
  - `App\Models\ImportRow` with relations `batch(): BelongsTo`, `transferDestination(): BelongsTo`, casts `transaction_date → datetime`, `amount → float`, `flow → Cashflow`, `category_id → Category`, `is_included → bool`, `is_duplicate → bool`, `parse_errors → array`, `raw → array`.
  - `Database\Factories\ImportBatchFactory` (states `approved()`, `discarded()`), `Database\Factories\ImportRowFactory` (states `excluded()`, `duplicate()`, `withErrors()`).

- [ ] **Step 1: Create the enum**

```php
<?php

namespace App\Enums;

enum ImportBatchStatus: string
{
    case Staging = 'staging';
    case Approved = 'approved';
    case Discarded = 'discarded';
}
```

(Check how `app/Enums/ProviderStatus.php` is written — if it carries a TypeScript attribute or helper methods, mirror that style.)

- [ ] **Step 2: Create the migrations**

```php
<?php

namespace Database\Migrations;

use App\Enums\ImportBatchStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('providers');
            $table->string('source_filename');
            $table->string('status')->default(ImportBatchStatus::Staging->value);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('discarded_at')->nullable();
            $table->timestamps();

            $table->index('account_id');
            $table->index('created_by');
            $table->index('status');
            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
```

```php
<?php

namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->dateTime('transaction_date')->nullable();
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('flow')->nullable();
            $table->string('category_id')->nullable();
            $table->boolean('is_included')->default(true);
            $table->boolean('is_duplicate')->default(false);
            $table->foreignId('transfer_destination_account_id')->nullable()->constrained('accounts');
            $table->string('fingerprint');
            $table->json('parse_errors')->nullable();
            $table->json('raw');
            $table->timestamps();

            $table->index('batch_id');
            $table->index('fingerprint');
            $table->index(['batch_id', 'is_included']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
```

- [ ] **Step 3: Create the models**

```php
<?php

namespace App\Models;

use App\Enums\ImportBatchStatus;
use Database\Factories\ImportBatchFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(ImportBatchFactory::class)]
final class ImportBatch extends Model
{
    /** @use HasFactory<ImportBatchFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ImportBatchStatus::class,
            'total_rows' => 'integer',
            'imported_rows' => 'integer',
            'approved_at' => 'datetime',
            'discarded_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }
}
```

```php
<?php

namespace App\Models;

use App\Enums\Cashflow;
use App\Enums\Category;
use Database\Factories\ImportRowFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(ImportRowFactory::class)]
final class ImportRow extends Model
{
    /** @use HasFactory<ImportRowFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'datetime',
            'amount' => 'float',
            'flow' => Cashflow::class,
            'category_id' => Category::class,
            'is_included' => 'boolean',
            'is_duplicate' => 'boolean',
            'parse_errors' => 'array',
            'raw' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function transferDestination(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'transfer_destination_account_id');
    }
}
```

- [ ] **Step 4: Create the factories**

```php
<?php

namespace Database\Factories;

use App\Enums\ImportBatchStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\ImportBatch> */
final class ImportBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'account_id' => Account::factory(),
            'provider_id' => Provider::factory(),
            'source_filename' => 'mutasi.csv',
            'status' => ImportBatchStatus::Staging,
            'total_rows' => 0,
            'imported_rows' => 0,
            'approved_at' => null,
            'discarded_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => ImportBatchStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function discarded(): static
    {
        return $this->state(fn (): array => [
            'status' => ImportBatchStatus::Discarded,
            'discarded_at' => now(),
        ]);
    }
}
```

```php
<?php

namespace Database\Factories;

use App\Enums\Cashflow;
use App\Enums\Category;
use App\Models\ImportBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\ImportRow> */
final class ImportRowFactory extends Factory
{
    public function definition(): array
    {
        $flow = fake()->randomElement(Cashflow::cases());

        return [
            'batch_id' => ImportBatch::factory(),
            'row_number' => fake()->unique()->numberBetween(1, 10000),
            'transaction_date' => fake()->dateTimeThisYear(),
            'description' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 1000, 1000000),
            'flow' => $flow,
            'category_id' => $flow === Cashflow::Inflow ? Category::OtherIncome : Category::OtherExpenses,
            'is_included' => true,
            'is_duplicate' => false,
            'transfer_destination_account_id' => null,
            'fingerprint' => fake()->sha1(),
            'parse_errors' => null,
            'raw' => [],
        ];
    }

    public function excluded(): static
    {
        return $this->state(fn (): array => ['is_included' => false]);
    }

    public function duplicate(): static
    {
        return $this->state(fn (): array => ['is_duplicate' => true, 'is_included' => false]);
    }

    /** @param array<string, string> $errors */
    public function withErrors(array $errors): static
    {
        return $this->state(fn (): array => ['parse_errors' => $errors, 'is_included' => false]);
    }
}
```

- [ ] **Step 5: Write the casts test**

`php artisan make:test --pest ImportRowCastsTest`, then move it to `tests/Integration/Models/ImportRowCastsTest.php` and write:

```php
<?php

use App\Enums\Cashflow;
use App\Enums\Category;
use App\Enums\ImportBatchStatus;
use App\Models\ImportRow;

test('casts row flow, category and boolean flags', function (): void {
    $row = ImportRow::factory()->create([
        'flow' => Cashflow::Inflow,
        'category_id' => Category::OtherIncome,
        'is_included' => false,
        'is_duplicate' => true,
        'parse_errors' => ['amount' => 'No amount column has a readable value.'],
    ]);

    expect($row->fresh())
        ->flow->toBe(Cashflow::Inflow)
        ->category_id->toBe(Category::OtherIncome)
        ->is_included->toBeFalse()
        ->is_duplicate->toBeTrue()
        ->parse_errors->toBe(['amount' => 'No amount column has a readable value.']);
});

test('casts batch status and datetimes', function (): void {
    $batch = App\Models\ImportBatch::factory()->approved()->create();

    expect($batch->fresh())
        ->status->toBe(ImportBatchStatus::Approved)
        ->approved_at->not->toBeNull();
});
```

---

### Task 2: Import fingerprint

**Complexity:** Low

**Files:**
- Create: `app/Services/Import/ImportFingerprint.php`
- Test: `tests/Unit/Services/Import/ImportFingerprintTest.php`

**Interfaces:**
- Produces: `App\Services\Import\ImportFingerprint::make(int $accountId, ?CarbonImmutable $date, ?float $amount, ?string $description): string` — a stable sha1 over account, date (day precision), whole-rupiah amount, and a normalized (lowercased, whitespace-collapsed, trimmed) description. Used by Task 3.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Services\Import\ImportFingerprint;
use Carbon\CarbonImmutable;

test('is stable across case and whitespace differences in the description', function (): void {
    $a = ImportFingerprint::make(1, CarbonImmutable::parse('2026-10-31'), 2500000.0, 'TRSF  E-Banking   cr BCA');
    $b = ImportFingerprint::make(1, CarbonImmutable::parse('2026-10-31 08:30:00'), 2500000.0, 'trsf e-banking cr bca');

    expect($a)->toBe($b);
});

test('differs across account, date, or amount', function (): void {
    $base = ImportFingerprint::make(1, CarbonImmutable::parse('2026-10-31'), 2500000.0, 'same');

    expect($base)
        ->not->toBe(ImportFingerprint::make(2, CarbonImmutable::parse('2026-10-31'), 2500000.0, 'same'))
        ->not->toBe(ImportFingerprint::make(1, CarbonImmutable::parse('2026-10-30'), 2500000.0, 'same'))
        ->not->toBe(ImportFingerprint::make(1, CarbonImmutable::parse('2026-10-31'), 2600000.0, 'same'));
});

test('handles null parts deterministically', function (): void {
    expect(ImportFingerprint::make(1, null, null, null))
        ->toBe(ImportFingerprint::make(1, null, null, null))
        ->not->toBe(ImportFingerprint::make(1, null, null, 'described'));
});
```

- [ ] **Step 2: Implement**

```php
<?php

namespace App\Services\Import;

use Carbon\CarbonImmutable;

/**
 * Duplicate identity for staged rows: same account + same day + same
 * whole-rupiah amount + normalized description = same fingerprint.
 */
final class ImportFingerprint
{
    public static function make(int $accountId, ?CarbonImmutable $date, ?float $amount, ?string $description): string
    {
        $normalizedDescription = mb_strtolower((string) preg_replace('/\s+/u', ' ', trim((string) $description)));

        return sha1(implode('|', [
            $accountId,
            $date?->toDateString() ?? 'no-date',
            (string) (int) round((float) $amount),
            $normalizedDescription,
        ]));
    }
}
```

---

### Task 3: ImportService::upload — parse, default, fingerprint, stage

**Complexity:** Medium

**Files:**
- Create: `app/Services/ImportService.php`
- Modify: `app/Models/Account.php` (add `importBatches()` relation)
- Test: `tests/Integration/Services/ImportServiceTest.php`

**Interfaces:**
- Consumes: `ParserRegistry::resolve/supports` and `ParsedStatement`/`ParsedRow` (parsing plan Tasks 1–2), `ImportFileException` (parsing plan), `ImportFingerprint::make` (Task 2), `ImportBatch`/`ImportRow` (Task 1).
- Produces: `App\Services\ImportService::upload(User $user, Account $account, UploadedFile $file): ImportBatch` — used by `ImportController::store` (Task 6).

- [ ] **Step 1: Write the failing tests**

`php artisan make:test --pest ImportServiceTest`, move to `tests/Integration/Services/ImportServiceTest.php`:

```php
<?php

use App\Data\Transaction\TransactionData;
use App\Enums\Cashflow;
use App\Enums\Category;
use App\Enums\ImportBatchStatus;
use App\Enums\ProviderType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Provider;
use App\Models\User;
use App\Services\ImportService;
use App\Services\TransactionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

function importableAccount(User $user): Account
{
    $provider = Provider::factory()->create([
        'name' => 'Mandiri',
        'slug' => 'mandiri',
        'type' => ProviderType::Bank,
    ]);

    return Account::factory()->create([
        'owner_id' => $user->id,
        'provider_id' => $provider->id,
    ]);
}

function statementFile(string $name = 'mandiri-basic.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'mutasi.csv',
        file_get_contents(base_path('tests/Fixtures/Imports/' . $name))
    );
}

test('uploads a statement and stages rows with category defaults', function (): void {
    $user = User::factory()->create();
    $account = importableAccount($user);

    $batch = app(ImportService::class)->upload($user, $account, statementFile());

    expect($batch->status)->toBe(ImportBatchStatus::Staging)
        ->and($batch->total_rows)->toBe(3)
        ->and($batch->rows)->toHaveCount(3);

    $inflow = $batch->rows->firstWhere('flow', Cashflow::Inflow);
    $outflow = $batch->rows->firstWhere('flow', Cashflow::Outflow);

    expect($inflow->category_id)->toBe(Category::OtherIncome)
        ->and($outflow->category_id)->toBe(Category::OtherExpenses)
        ->and($inflow->is_included)->toBeTrue()
        ->and($inflow->is_duplicate)->toBeFalse()
        ->and($inflow->raw)->not->toBeEmpty();
});

test('flags duplicates against existing transactions and within the file', function (): void {
    $user = User::factory()->create();
    $account = importableAccount($user);

    app(TransactionService::class)->create($user, new TransactionData(
        account_id: $account->id,
        type: TransactionType::Income,
        amount: 2500000,
        transaction_date: '2026-10-31 00:00:00',
        category_id: Category::OtherIncome,
        description: 'TRSF E-BANKING CR BCA',
    ));

    $batch = app(ImportService::class)->upload($user, $account, statementFile());

    $duplicate = $batch->rows->firstWhere('is_duplicate', true);

    expect($duplicate)->not->toBeNull()
        ->and($duplicate->description)->toBe('TRSF E-BANKING CR BCA')
        ->and($duplicate->is_included)->toBeFalse();
});

test('rejects upload when the provider has no parser', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]); // no provider

    app(ImportService::class)->upload($user, $account, statementFile());
})->throws(ValidationException::class);

test('maps parser file failures to validation errors and creates nothing', function (): void {
    $user = User::factory()->create();
    $account = importableAccount($user);

    try {
        app(ImportService::class)->upload($user, $account, statementFile('mandiri-unrecognized.csv'));
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('statement');
    }

    expect(\App\Models\ImportBatch::count())->toBe(0);
});
```

- [ ] **Step 2: Implement `ImportService::upload`**

`app/Services/ImportService.php`:

```php
<?php

namespace App\Services;

use App\Enums\Category;
use App\Enums\ImportBatchStatus;
use App\Models\Account;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Import\ImportFileException;
use App\Services\Import\ImportFingerprint;
use App\Services\Import\ParsedRow;
use App\Services\Import\ParsedStatement;
use App\Services\Import\ParserRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staging lifecycle for provider statement imports: upload (parse →
 * default → fingerprint → stage), row editing, approve (materialize via
 * the existing transaction/transfer services so balance observers stay
 * intact), and discard.
 */
final readonly class ImportService
{
    private const int INSERT_CHUNK = 250;

    public function __construct(
        private readonly ParserRegistry $registry,
        private readonly TransactionService $transactionService,
        private readonly TransferService $transferService,
    ) {}

    public function upload(User $user, Account $account, UploadedFile $file): ImportBatch
    {
        $provider = $account->provider;

        if ($provider === null || ! $this->registry->supports($provider)) {
            throw ValidationException::withMessages([
                'statement' => 'Statement import is not available for this provider yet.',
            ]);
        }

        try {
            $statement = $this->registry->resolve($provider)->parse($file->getContent());
        } catch (ImportFileException $exception) {
            throw ValidationException::withMessages([
                'statement' => $exception->getMessage(),
            ]);
        }

        return DB::transaction(function () use ($user, $account, $file, $statement): ImportBatch {
            $existing = $this->existingFingerprints($account, $statement);

            $batch = ImportBatch::create([
                'created_by' => $user->id,
                'account_id' => $account->id,
                'provider_id' => $account->provider_id,
                'source_filename' => $file->getClientOriginalName(),
                'status' => ImportBatchStatus::Staging,
                'total_rows' => count($statement->rows),
            ]);

            $seen = [];
            $rows = [];
            foreach ($statement->rows as $index => $parsed) {
                $fingerprint = ImportFingerprint::make($account->id, $parsed->date, $parsed->amount, $parsed->description);
                $isDuplicate = in_array($fingerprint, $existing, true) || isset($seen[$fingerprint]);
                $seen[$fingerprint] = true;

                $rows[] = [
                    'batch_id' => $batch->id,
                    'row_number' => $index + 1,
                    'transaction_date' => $parsed->date?->toDateTimeString(),
                    'description' => $parsed->description,
                    'amount' => $parsed->amount,
                    'flow' => $parsed->flow?->value,
                    'category_id' => $parsed->flow === null
                        ? null
                        : $parsed->flow->isInflow() ? Category::OtherIncome->value : Category::OtherExpenses->value,
                    'is_included' => $parsed->errors === [] && ! $isDuplicate,
                    'is_duplicate' => $isDuplicate,
                    'transfer_destination_account_id' => null,
                    'fingerprint' => $fingerprint,
                    'parse_errors' => $parsed->errors === [] ? null : $parsed->errors,
                    'raw' => $parsed->raw,
                ];
            }

            // insert() bypasses casts — enum values are pre-stringified above.
            collect($rows)
                ->chunk(self::INSERT_CHUNK)
                ->each(fn (Collection $chunk): bool => ImportRow::insert($chunk->all()));

            return $batch;
        });
    }

    /**
     * Fingerprints of the account's live transactions inside the file's date span.
     *
     * @return list<string>
     */
    private function existingFingerprints(Account $account, ParsedStatement $statement): array
    {
        $dates = collect($statement->rows)
            ->filter(fn (ParsedRow $row): bool => $row->date !== null)
            ->map(fn (ParsedRow $row): string => $row->date->toDateString());

        if ($dates->isEmpty()) {
            return [];
        }

        return Transaction::query()
            ->where('account_id', $account->id)
            ->whereBetween('transaction_date', [$dates->min() . ' 00:00:00', $dates->max() . ' 23:59:59'])
            ->get(['transaction_date', 'amount', 'description'])
            ->map(fn (Transaction $transaction): string => ImportFingerprint::make(
                $account->id,
                CarbonImmutable::parse($transaction->transaction_date),
                (float) $transaction->amount,
                $transaction->description,
            ))
            ->all();
    }
}
```

Also add to `app/Models/Account.php` (after `transactions()`):

```php
    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class);
    }
```

---

### Task 4: ImportService::updateRow — review-time editing

**Complexity:** Medium

**Files:**
- Modify: `app/Services/ImportService.php`
- Test: `tests/Integration/Services/ImportServiceTest.php` (append)

**Interfaces:**
- Consumes: Task 3's `ImportService`.
- Produces: `App\Services\ImportService::updateRow(ImportRow $row, array $data): ImportRow` — accepts any subset of `transaction_date`, `description`, `amount`, `flow`, `category_id`, `transfer_destination_account_id`, `is_included` (validated upstream by `UpdateImportRowRequest`, Task 6).

- [ ] **Step 1: Append the failing tests**

```php
test('updates editable fields on a staging row', function (): void {
    $user = User::factory()->create();
    $account = importableAccount($user);

    $batch = app(ImportService::class)->upload($user, $account, statementFile());
    $row = $batch->rows->first();

    app(ImportService::class)->updateRow($row, [
        'description' => 'Edited description',
        'amount' => 999999,
        'flow' => 'outflow',
        'category_id' => Category::Electricity->value,
        'is_included' => false,
    ]);

    expect($row->fresh())
        ->description->toBe('Edited description')
        ->amount->toBe(999999.0)
        ->flow->toBe(Cashflow::Outflow)
        ->category_id->toBe(Category::Electricity)
        ->is_included->toBeFalse();
});

test('rejects a transfer destination equal to the batch account', function (): void {
    $user = User::factory()->create();
    $account = importableAccount($user);

    $batch = app(ImportService::class)->upload($user, $account, statementFile());
    $row = $batch->rows->first();

    app(ImportService::class)->updateRow($row, [
        'transfer_destination_account_id' => $account->id,
    ]);
})->throws(ValidationException::class);

test('rejects editing rows of non-staging batches', function (): void {
    $batch = App\Models\ImportBatch::factory()->approved()->create();
    $row = App\Models\ImportRow::factory()->create(['batch_id' => $batch->id]);

    app(ImportService::class)->updateRow($row, ['description' => 'nope']);
})->throws(ValidationException::class);
```

- [ ] **Step 2: Implement**

Append to `ImportService` (add `use App\Enums\Cashflow;` to the imports):

```php
    /**
     * Review-time editing of one staged row. Only staging batches are
     * editable. `is_duplicate` stays an upload-time fact: an explicit edit
     * (or include toggle) is the user overriding it, so it is never
     * re-checked here.
     */
    public function updateRow(ImportRow $row, array $data): ImportRow
    {
        $this->assertStaging($row->batch);

        if (isset($data['transfer_destination_account_id'])
            && (int) $data['transfer_destination_account_id'] === $row->batch->account_id) {
            throw ValidationException::withMessages([
                'transfer_destination_account_id' => 'The transfer destination must be a different account.',
            ]);
        }

        $row->update([
            'transaction_date' => $data['transaction_date'] ?? $row->transaction_date?->toDateTimeString(),
            'description' => array_key_exists('description', $data) ? $data['description'] : $row->description,
            'amount' => $data['amount'] ?? $row->amount,
            'flow' => isset($data['flow']) ? Cashflow::from($data['flow']) : $row->flow,
            'category_id' => isset($data['category_id']) ? Category::from($data['category_id']) : $row->category_id,
            'transfer_destination_account_id' => array_key_exists('transfer_destination_account_id', $data)
                ? $data['transfer_destination_account_id']
                : $row->transfer_destination_account_id,
            'is_included' => $data['is_included'] ?? $row->is_included,
        ]);

        return $row->fresh();
    }

    private function assertStaging(ImportBatch $batch): void
    {
        if ($batch->status !== ImportBatchStatus::Staging) {
            throw ValidationException::withMessages([
                'batch' => 'Only staging batches can be modified.',
            ]);
        }
    }
```

---

### Task 5: ImportService::approve + discard — materialization

**Complexity:** Medium

**Files:**
- Modify: `app/Services/ImportService.php`
- Test: `tests/Integration/Services/ImportServiceTest.php` (append)

**Interfaces:**
- Consumes: `TransactionService::create(User, TransactionData)` and `TransferService::create(User, TransferData)` (existing), Tasks 1–4, parsing plan.
- Produces:
  - `App\Services\ImportService::approve(ImportBatch $batch): int` — returns imported count; batch → `approved`.
  - `App\Services\ImportService::discard(ImportBatch $batch): void` — batch → `discarded`.

- [ ] **Step 1: Append the failing tests**

```php
test('approves included rows into real transactions and updates the balance', function (): void {
    $user = User::factory()->create();
    $account = importableAccount($user);
    $balanceBefore = $account->current_balance;

    $batch = app(ImportService::class)->upload($user, $account, statementFile()); // 2 inflows + 1 outflow
    $imported = app(ImportService::class)->approve($batch);

    expect($imported)->toBe(3)
        ->and($batch->fresh())
        ->status->toBe(ImportBatchStatus::Approved)
        ->imported_rows->toBe(3)
        ->approved_at->not->toBeNull();

    // 2,500,000 + 25,000 inflows − 350,000 outflow = +2,175,000
    expect($account->fresh()->current_balance - $balanceBefore)->toBe(2175000);

    // staged rows survive as audit trail
    expect($batch->rows()->count())->toBe(3);
});

test('approve materializes a transfer-marked row as an aggregate with a mirrored member', function (): void {
    $user = User::factory()->create();
    $account = importableAccount($user);
    $destination = Account::factory()->create(['owner_id' => $user->id]);
    $destinationBefore = $destination->current_balance;

    $batch = app(ImportService::class)->upload($user, $account, statementFile());
    $row = $batch->rows->firstWhere('description', 'PEMBAYARAN LISTRIK PLN');

    app(ImportService::class)->updateRow($row, ['transfer_destination_account_id' => $destination->id]);
    app(ImportService::class)->approve($batch);

    $transfer = \App\Models\Transfer::query()->sole();
    $members = $transfer->transactions;

    expect($members)->toHaveCount(2)
        ->and($members->where('account_id', $account->id)->firstWhere('flow', Cashflow::Outflow))->not->toBeNull()
        ->and($members->where('account_id', $destination->id)->firstWhere('flow', Cashflow::Inflow))->not->toBeNull()
        ->and($destination->fresh()->current_balance - $destinationBefore)->toBe(350000);
});

test('approve re-validates and rejects error rows left included', function (): void {
    $user = User::factory()->create();
    $account = importableAccount($user);

    $batch = app(ImportService::class)->upload($user, $account, statementFile('mandiri-dirty.csv'));
    $errorRow = $batch->rows->firstWhere('is_included', false);

    app(ImportService::class)->updateRow($errorRow, ['is_included' => true]);

    app(ImportService::class)->approve($batch->fresh());
})->throws(ValidationException::class);

test('approve rejects zero included rows', function (): void {
    $user = User::factory()->create();
    $account = importableAccount($user);

    $batch = app(ImportService::class)->upload($user, $account, statementFile());
    $batch->rows()->update(['is_included' => false]);

    app(ImportService::class)->approve($batch->fresh());
})->throws(ValidationException::class);

test('discard keeps rows and flips status', function (): void {
    $user = User::factory()->create();
    $account = importableAccount($user);

    $batch = app(ImportService::class)->upload($user, $account, statementFile());
    app(ImportService::class)->discard($batch);

    expect($batch->fresh())
        ->status->toBe(ImportBatchStatus::Discarded)
        ->discarded_at->not->toBeNull()
        ->and($batch->rows()->count())->toBe(3);
});
```

- [ ] **Step 2: Implement**

Append to `ImportService` (imports to add: `App\Data\Transaction\TransactionData`, `App\Data\Transaction\TransferData`, `App\Enums\TransactionType`):

```php
    /**
     * Bulk-approve: re-validate every included row server-side (client
     * state is not trusted), then materialize through the existing
     * transaction/transfer services inside one DB transaction so balance
     * observers and events fire exactly as for hand-entered rows.
     */
    public function approve(ImportBatch $batch): int
    {
        $this->assertStaging($batch);

        /** @var Collection<int, ImportRow> $rows */
        $rows = $batch->rows()->where('is_included', true)->orderBy('row_number')->get();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'batch' => 'No rows are selected for import.',
            ]);
        }

        $rows->each(fn (ImportRow $row): null => $this->assertRowImportable($row));

        return DB::transaction(function () use ($batch, $rows): int {
            $count = 0;

            foreach ($rows as $row) {
                $row->transfer_destination_account_id !== null
                    ? $this->importTransferRow($batch, $row)
                    : $this->importPlainRow($batch, $row);
                $count++;
            }

            $batch->update([
                'status' => ImportBatchStatus::Approved,
                'imported_rows' => $count,
                'approved_at' => now(),
            ]);

            return $count;
        });
    }

    public function discard(ImportBatch $batch): void
    {
        $this->assertStaging($batch);

        $batch->update([
            'status' => ImportBatchStatus::Discarded,
            'discarded_at' => now(),
        ]);
    }

    private function assertRowImportable(ImportRow $row): void
    {
        if ($row->parse_errors !== null) {
            throw ValidationException::withMessages([
                'rows' => "Row {$row->row_number} still has parse errors.",
            ]);
        }

        if ($row->transaction_date === null || $row->amount === null || $row->amount <= 0 || $row->flow === null) {
            throw ValidationException::withMessages([
                'rows' => "Row {$row->row_number} is missing a date, amount, or flow.",
            ]);
        }

        $isTransfer = $row->transfer_destination_account_id !== null;

        if (! $isTransfer && $row->category_id === null) {
            throw ValidationException::withMessages([
                'rows' => "Row {$row->row_number} has no category.",
            ]);
        }

        if ($isTransfer) {
            if ((int) $row->transfer_destination_account_id === $row->batch->account_id) {
                throw ValidationException::withMessages([
                    'rows' => "Row {$row->row_number} transfers to its own account.",
                ]);
            }

            $destinationExists = Account::query()
                ->where('id', $row->transfer_destination_account_id)
                ->where('owner_id', $row->batch->created_by)
                ->exists();

            if (! $destinationExists) {
                throw ValidationException::withMessages([
                    'rows' => "Row {$row->row_number} has an invalid transfer destination.",
                ]);
            }
        }
    }

    private function importPlainRow(ImportBatch $batch, ImportRow $row): void
    {
        $this->transactionService->create($batch->creator, new TransactionData(
            account_id: $batch->account_id,
            type: $row->flow->isInflow() ? TransactionType::Income : TransactionType::Expense,
            amount: (float) $row->amount,
            transaction_date: $row->transaction_date->toDateTimeString(),
            category_id: $row->category_id,
            description: $row->description,
        ));
    }

    /** Direction follows the row's flow: outflow books this account as source; inflow books it as destination. */
    private function importTransferRow(ImportBatch $batch, ImportRow $row): void
    {
        $this->transferService->create($batch->creator, new TransferData(
            account_id: $row->flow->isOutflow()
                ? $batch->account_id
                : $row->transfer_destination_account_id,
            destination_account_id: $row->flow->isOutflow()
                ? $row->transfer_destination_account_id
                : $batch->account_id,
            amount: (float) $row->amount,
            transaction_date: $row->transaction_date->toDateTimeString(),
            description: $row->description,
        ));
    }
```

---

### Task 6: HTTP surface — policy, requests, controller, routes, Wayfinder regen

**Complexity:** Medium

**Files:**
- Create: `app/Policies/ImportBatchPolicy.php`
- Create: `app/Http/Requests/ImportUploadRequest.php`
- Create: `app/Http/Requests/UpdateImportRowRequest.php`
- Create: `app/Http/Controllers/ImportController.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/AccountController.php` (`show`)
- Test: `tests/Feature/App/ImportTest.php`

**Interfaces:**
- Consumes: `ImportService` (Tasks 3–5), `ParserRegistry` (parsing plan Task 2), `CategoryService::getCategories()`, `AccountService::getAccountsByUser(User)` (existing).
- Produces (used by the frontend plan): routes `imports.create` (GET `accounts/{account}/import`), `imports.store` (POST same), `imports.show` (GET `imports/{batch}`), `imports.rows.update` (PATCH `imports/{batch}/rows/{row}`), `imports.approve` (POST `imports/{batch}/approve`), `imports.discard` (POST `imports/{batch}/discard`) — controller `App\Http\Controllers\ImportController` (Wayfinder import path `@wayfinder/App/Http/Controllers/ImportController`), page props for `app/account/import` (`account`, `provider_name`) and `app/import/review` (`batch`, `rows`, `categories`, `accounts`), shared prop `import_supported` on `app/account/show`.

- [ ] **Step 1: Write the failing feature tests**

`php artisan make:test --pest ImportTest`, move to `tests/Feature/App/ImportTest.php`:

```php
<?php

use App\Enums\Category;
use App\Enums\ImportBatchStatus;
use App\Enums\ProviderType;
use App\Models\Account;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Provider;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Http\UploadedFile;

beforeEach(function (): void {
    $this->user = User::factory()->create();

    $this->provider = Provider::factory()->create([
        'name' => 'Mandiri',
        'slug' => 'mandiri',
        'type' => ProviderType::Bank,
    ]);

    $this->account = Account::factory()->create([
        'owner_id' => $this->user->id,
        'provider_id' => $this->provider->id,
    ]);
});

function stagedBatch(User $user, Account $account): ImportBatch
{
    $file = UploadedFile::fake()->createWithContent(
        'mutasi.csv',
        file_get_contents(base_path('tests/Fixtures/Imports/mandiri-basic.csv'))
    );

    return app(ImportService::class)->upload($user, $account, $file);
}

test('renders the upload page for the account owner', function (): void {
    $this->actingAs($this->user)
        ->get(route('imports.create', $this->account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/account/import')
            ->has('account')
            ->where('provider_name', 'Mandiri')
        );
});

test('forbids the upload page for non-owners even when shareable', function (): void {
    $shareable = Account::factory()->shareable()->create([
        'owner_id' => $this->user->id,
        'provider_id' => $this->provider->id,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('imports.create', $shareable))
        ->assertForbidden();
});

test('stores an upload and redirects to the review page', function (): void {
    $file = UploadedFile::fake()->createWithContent(
        'mutasi.csv',
        file_get_contents(base_path('tests/Fixtures/Imports/mandiri-basic.csv'))
    );

    $this->actingAs($this->user)
        ->post(route('imports.store', $this->account), ['statement' => $file])
        ->assertRedirectToRoute('imports.show', ['batch' => ImportBatch::query()->sole()->id]);

    expect(ImportRow::count())->toBe(3);
});

test('rejects non-csv uploads', function (): void {
    $file = UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf');

    $this->actingAs($this->user)
        ->post(route('imports.store', $this->account), ['statement' => $file])
        ->assertInvalid('statement');
});

test('renders the review page with rows, categories and accounts', function (): void {
    $batch = stagedBatch($this->user, $this->account);

    $this->actingAs($this->user)
        ->get(route('imports.show', $batch))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/import/review')
            ->has('batch')
            ->has('rows', 3)
            ->has('categories')
            ->has('accounts')
        );
});

test('updates a staged row via the row endpoint', function (): void {
    $batch = stagedBatch($this->user, $this->account);
    $row = $batch->rows->first();

    $this->actingAs($this->user)
        ->patch(route('imports.rows.update', ['batch' => $batch->id, 'row' => $row->id]), [
            'description' => 'Edited via HTTP',
            'category_id' => Category::Electricity->value,
        ])
        ->assertRedirectToRoute('imports.show', $batch);

    expect($row->fresh()->description)->toBe('Edited via HTTP');
});

test('rejects a row that does not belong to the batch', function (): void {
    $batch = stagedBatch($this->user, $this->account);
    $otherRow = ImportRow::factory()->create();

    $this->actingAs($this->user)
        ->patch(route('imports.rows.update', ['batch' => $batch->id, 'row' => $otherRow->id]), [
            'description' => 'hijack',
        ])
        ->assertNotFound();
});

test('approves a batch and redirects to the account', function (): void {
    $batch = stagedBatch($this->user, $this->account);

    $this->actingAs($this->user)
        ->post(route('imports.approve', $batch))
        ->assertRedirectToRoute('accounts.show', $this->account);

    expect($batch->fresh()->status)->toBe(ImportBatchStatus::Approved);
});

test('discards a batch', function (): void {
    $batch = stagedBatch($this->user, $this->account);

    $this->actingAs($this->user)
        ->post(route('imports.discard', $batch))
        ->assertRedirectToRoute('accounts.show', $this->account);

    expect($batch->fresh()->status)->toBe(ImportBatchStatus::Discarded);
});

test('forbids every surface for a stranger', function (): void {
    $batch = stagedBatch($this->user, $this->account);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('imports.show', $batch))->assertForbidden();
    $this->actingAs($stranger)->post(route('imports.approve', $batch))->assertForbidden();
    $this->actingAs($stranger)->post(route('imports.discard', $batch))->assertForbidden();
});

test('account show exposes import support', function (): void {
    $unsupported = Account::factory()->create(['owner_id' => $this->user->id]);

    $this->actingAs($this->user)->get(route('accounts.show', $this->account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('import_supported', true));

    $this->actingAs($this->user)->get(route('accounts.show', $unsupported))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('import_supported', false));
});
```

- [ ] **Step 2: Create the policy**

`app/Policies/ImportBatchPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\ImportBatch;
use App\Models\User;

/** Owner-only on the underlying account — importing mutates it, even when shareable. */
final class ImportBatchPolicy
{
    public function view(User $user, ImportBatch $batch): bool
    {
        return $this->canAccess($user, $batch);
    }

    public function update(User $user, ImportBatch $batch): bool
    {
        return $this->canAccess($user, $batch);
    }

    public function approve(User $user, ImportBatch $batch): bool
    {
        return $this->canAccess($user, $batch);
    }

    public function discard(User $user, ImportBatch $batch): bool
    {
        return $this->canAccess($user, $batch);
    }

    private function canAccess(User $user, ImportBatch $batch): bool
    {
        return $user->id === $batch->account->owner_id;
    }
}
```

- [ ] **Step 3: Create the form requests**

`app/Http/Requests/ImportUploadRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;

final class ImportUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('account');

        return $account instanceof Account && $this->user()->can('update', $account);
    }

    public function rules(): array
    {
        return [
            'statement' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ];
    }
}
```

`app/Http/Requests/UpdateImportRowRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Enums\Cashflow;
use App\Enums\Category;
use App\Models\ImportRow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateImportRowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $row = $this->route('row');

        return $row instanceof ImportRow && $this->user()->can('update', $row->batch);
    }

    public function rules(): array
    {
        return [
            'transaction_date' => ['sometimes', 'required', 'date', 'before_or_equal:now'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'flow' => ['sometimes', 'required', Rule::enum(Cashflow::class)],
            'category_id' => ['sometimes', 'required', Rule::enum(Category::class)->except(Category::InitialBalance)],
            'transfer_destination_account_id' => ['sometimes', 'nullable', 'integer', 'exists:accounts,id'],
            'is_included' => ['sometimes', 'boolean'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

`app/Http/Controllers/ImportController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportUploadRequest;
use App\Http\Requests\UpdateImportRowRequest;
use App\Models\Account;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\User;
use App\Services\AccountService;
use App\Services\CategoryService;
use App\Services\Import\ParserRegistry;
use App\Services\ImportService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ImportController extends Controller
{
    public function __construct(
        private readonly ImportService $importService,
        private readonly ParserRegistry $registry,
        private readonly AccountService $accountService,
        #[CurrentUser] private readonly ?User $user,
    ) {}

    public function create(Request $request, Account $account): Response
    {
        $this->authorize('update', $account);

        return Inertia::render('app/account/import', [
            'account' => $account->load('provider'),
            'provider_name' => $account->provider?->name ?? 'Statement',
        ]);
    }

    public function store(ImportUploadRequest $request, Account $account): RedirectResponse
    {
        $batch = $this->importService->upload($request->user(), $account, $request->file('statement'));

        return to_route('imports.show', $batch)->flash('Statement staged for review.');
    }

    public function show(Request $request, ImportBatch $batch): Response
    {
        $this->authorize('view', $batch);

        return Inertia::render('app/import/review', [
            'batch' => $batch->load(['account.provider', 'provider']),
            'rows' => $batch->rows()->orderBy('row_number')->get(),
            'categories' => CategoryService::getCategories(),
            'accounts' => $this->accountService->getAccountsByUser($this->user),
        ]);
    }

    public function updateRow(UpdateImportRowRequest $request, ImportBatch $batch, ImportRow $row): RedirectResponse
    {
        abort_unless($row->batch_id === $batch->id, 404);

        $this->importService->updateRow($row, $request->validated());

        return to_route('imports.show', $batch)->flash('Row updated.');
    }

    public function approve(Request $request, ImportBatch $batch): RedirectResponse
    {
        $this->authorize('approve', $batch);

        $count = $this->importService->approve($batch);

        return to_route('accounts.show', $batch->account_id)
            ->flash("Imported {$count} transactions.");
    }

    public function discard(Request $request, ImportBatch $batch): RedirectResponse
    {
        $this->authorize('discard', $batch);

        $this->importService->discard($batch);

        return to_route('accounts.show', $batch->account_id)->flash('Import discarded.');
    }
}
```

- [ ] **Step 5: Register routes**

In `routes/web.php`, add `use App\Http\Controllers\ImportController;` with the other controller imports, then inside the authenticated group, after the Transfers block:

```php
    // Statement imports (per-account upload; staging review; bulk approve)
    Route::controller(ImportController::class)->group(function (): void {
        Route::get('accounts/{account}/import', 'create')->name('imports.create');
        Route::post('accounts/{account}/import', 'store')->name('imports.store');
        Route::get('imports/{batch}', 'show')->name('imports.show');
        Route::patch('imports/{batch}/rows/{row}', 'updateRow')->name('imports.rows.update');
        Route::post('imports/{batch}/approve', 'approve')->name('imports.approve');
        Route::post('imports/{batch}/discard', 'discard')->name('imports.discard');
    });
```

- [ ] **Step 6: Expose import support on the account page**

In `app/Http/Controllers/AccountController.php`, add `ParserRegistry` to the constructor and one prop in `show()`:

```php
    public function __construct(
        #[CurrentUser] private readonly ?User $user,
        private readonly AccountService $accountService,
        private readonly ParserRegistry $parserRegistry,
    ) {}
```

```php
        return Inertia::render('app/account/show', [
            'account' => $account,
            'transactions' => $transactions,
            'categorySpending' => $categorySpending,
            'import_supported' => $this->parserRegistry->supports($account->provider),
        ]);
```

with `use App\Services\Import\ParserRegistry;` at the top.

- [ ] **Step 7: Regenerate Wayfinder types (staging plan exit gate)**

Run: `php artisan wayfinder:generate`
Expected: `resources/js/wayfinder/App/Http/Controllers/ImportController.php` exists and `App.Models.ImportBatch` / `App.Models.ImportRow` are present in `resources/js/wayfinder/types`. The frontend plan consumes these — this step is its prerequisite.

---

## Post-Plan Notes (not tasks)

- **Known v1 limitations** (documented in the spec): cross-batch duplicate overlap, queue offloading for very large files, transfer fees on imported transfers, PDF/XLSX formats. (XLSX: if the parsing plan's optional laravel-excel task is taken, extend `ImportUploadRequest`'s mime rule to `'mimes:csv,txt,xlsx'` and the frontend upload input's `accept` to `.csv,.xlsx`.)
