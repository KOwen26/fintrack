# Import Suggestion Agent Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use better-executing-plans to implement this plan task-by-task — or better-parallel-subagents-executions if the plan splits into independent slices, or superpowers:subagent-driven-development if workflow mode is `remote`. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Auto-categorize staged statement-import rows via a Laravel AI SDK structured-output agent on Ollama Cloud, applied asynchronously after upload with graceful degradation.

**Architecture:** Upload stages rows as today, then `defer()`s an `ImportSuggestion` service that chunks rows, prompts the `ImportSuggestions` agent (instructions + schema built from the `Category` enum catalog), validates every returned slug/type, and applies guarded updates. Failure at any level leaves the batch fully reviewable with dumb defaults. Spec: `docs/superpowers/specs/2026-11-05-import-suggestion-agent-design.md`.

**Tech Stack:** Laravel AI SDK (`laravel/ai`) via `openai-compatible` driver → Ollama Cloud (`https://ollama.com/v1`), Pest (Integration + Feature suites), Svelte 5 + Inertia.

**Workflow Mode:** `direct` — no per-task verify/commit steps; the human runs tests/format/commits when they choose.

**Complexity:** Medium

## Global Constraints

- PHP 8.4, project rules in `.ai/rules/` apply per path (read them before editing unfamiliar areas).
- Terminology: `suggestion_*` everywhere — never `ai_*` names.
- Execution is `defer()`, never the queue. The agent's provider name is the literal string `'ollama-cloud'`.
- Categories are the `App\Enums\Category` catalog (slug-backed enum with `label()`, `description()`, `group()`, `group()->flow()`); `category_id` columns store slug strings. Never query a categories table — none exists.
- Enum storage: string columns + PHP enum casts; never `$table->enum()`. App classes are `final` (architecture suite enforces).
- Services: static reads, instance writes (`ImportSuggestion::isEnabled()` static; `suggestCategories()`/`retry()`/`failIfStale()` instance). Controllers: authorize → service call → `to_route()->flash()`; `#[CurrentUser]` injection; `$this->authorize()` only for actions without a Form Request.
- Tests: `tests/Integration` = booted app + DB, direct class invocation, `it()` style; `tests/Feature/App` = HTTP with `test()` style; `tests/Unit` = pure logic, `it()` style, no booted app. Factories only, never seeders; `expect()` + re-query, never `assertDatabaseHas`. No network in tests — always `ImportSuggestions::fake()`.
- Frontend: Svelte 5 runes, kebab-case files, `solar--`/`tabler--` icons only, hardcoded UI strings (no i18n), Wayfinder imports (`@wayfinder/...`), never hand-edit `resources/js/wayfinder/**`.
- Windows: `composer require "pkg:^x"` mangles the caret — edit `composer.json` directly, then `composer update <pkg>`.
- Project rule (deferred to the human in `direct` mode): `vendor/bin/pint --dirty --format agent` after PHP edits.

---

### Task 1: Provider config, feature gate, env

**Complexity:** Low

> Prerequisite handled by you outside this plan: `laravel/ai` required via Composer and `config/ai.php` published (`php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"`), SDK migrations applied. This task only covers application configuration.

**Files:**
- Modify: `config/ai.php`
- Create: `config/feature.php`
- Modify: `.env.example` (append block at end)

**Interfaces:**
- Consumes: nothing
- Produces: `config('ai.providers.ollama-cloud')` entry (driver `openai-compatible`, url/key/models); `config('feature.import-suggestions')` = `['enabled' => bool, 'chunk_size' => 50, 'timeout' => 120]` — read by `ImportSuggestion::isEnabled()` (Task 3) and `suggestCategories()` (Task 5). These config files are the single source of the defaults — code never re-declares them.

- [ ] **Step 1: Add the Ollama Cloud provider to `config/ai.php`**

Inside the `providers` array (e.g. between `mistral` and `openai`):

```php
'ollama-cloud' => [
    'driver'  => 'openai-compatible',
    'url'     => env('OLLAMA_CLOUD_URL', 'https://ollama.com/v1'),
    'key'     => env('OLLAMA_CLOUD_API_KEY'),
    'models'  => ['text' => ['default' => env('OLLAMA_CLOUD_MODEL', 'gpt-oss:120b')]],
],
```

- [ ] **Step 2: Create `config/feature.php`**

```php
<?php

return [
    'import-suggestions' => [
        'enabled'    => env('IMPORT_SUGGESTIONS_ENABLED', true),
        'chunk_size' => 50,
        'timeout'    => 120,
    ],
];
```

- [ ] **Step 3: Append env block to `.env.example`**

```ini
# AI — Ollama Cloud (statement import suggestions)
OLLAMA_CLOUD_URL=https://ollama.com/v1
OLLAMA_CLOUD_API_KEY=
OLLAMA_CLOUD_MODEL=gpt-oss:120b
IMPORT_SUGGESTIONS_ENABLED=true
```

(Adding a real `OLLAMA_CLOUD_API_KEY` to the local `.env` is the human's step when trying it live.)

---

### Task 2: Schema columns, status enum, model casts

**Complexity:** Low

**Files:**
- Modify: `database/migrations/2026_10_30_000001_create_import_batches_table.php`
- Modify: `database/migrations/2026_10_30_000002_create_import_rows_table.php`
- Create: `app/Enums/ImportSuggestionStatus.php`
- Modify: `app/Models/ImportBatch.php` (casts)
- Modify: `app/Models/ImportRow.php` (casts)
- Test: `tests/Integration/Models/ImportRowCastsTest.php` (extend existing)

**Interfaces:**
- Consumes: `App\Enums\TransactionType` (exists), `App\Enums\ImportBatchStatus` (style reference)
- Produces: `ImportBatch::suggestion_status` cast to `ImportSuggestionStatus|null`, `suggested_at` datetime; `ImportRow::transaction_type` cast to `TransactionType|null`; enum `ImportSuggestionStatus::{Pending, Processing, Completed, Failed}` with values `pending|processing|completed|failed`

- [ ] **Step 1: Write the failing cast test**

Append to `tests/Integration/Models/ImportRowCastsTest.php` (match its existing `it()` style and imports):

```php
it('casts transaction_type to the TransactionType enum', function (): void {
    $row = ImportRow::factory()->create(['transaction_type' => 'transfer']);

    expect($row->transaction_type)->toBeInstanceOf(TransactionType::class)
        ->and($row->transaction_type)->toBe(TransactionType::Transfer);
});
```

Add `use App\Enums\TransactionType;` to the imports if absent.

- [ ] **Step 2: Add the status enum**

`app/Enums/ImportSuggestionStatus.php` (match `ImportBatchStatus` house style):

```php
<?php

namespace App\Enums;

enum ImportSuggestionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
```

- [ ] **Step 3: Extend the two create-table migrations in place**

In `2026_10_30_000001_create_import_batches_table.php`, after `$table->unsignedInteger('imported_rows')->default(0);`:

```php
$table->string('suggestion_status')->nullable();
$table->text('suggestion_error')->nullable();
$table->timestamp('suggested_at')->nullable();
```

In `2026_10_30_000002_create_import_rows_table.php`, after `$table->string('flow')->nullable();`:

```php
$table->string('transaction_type')->nullable();
```

Then rebuild local data: `php artisan migrate:fresh --no-interaction` (explicit spec decision — modify-in-place, no backfill migration).

- [ ] **Step 4: Add the model casts**

`app/Models/ImportBatch.php` — add to `casts()` and import the enum:

```php
'suggestion_status' => ImportSuggestionStatus::class,
'suggested_at'      => 'datetime',
```

`app/Models/ImportRow.php` — add to `casts()` and import the enum:

```php
'transaction_type' => TransactionType::class,
```

---

### Task 3: `ImportSuggestion` service skeleton + gate

**Complexity:** Low

**Files:**
- Create: `app/Services/Import/ImportSuggestion.php`
- Test: `tests/Integration/Services/Import/ImportSuggestionTest.php` (new file)

**Interfaces:**
- Consumes: `config('feature.import-suggestions.enabled')`, `config('ai.providers.ollama-cloud.key')`
- Produces: `ImportSuggestion::isEnabled(): bool` (static) — consumed by `ImportService::upload()` (Task 6) and `ImportController::retrySuggestions()` (Task 7)

- [ ] **Step 1: Write the failing gate tests**

Create `tests/Integration/Services/Import/ImportSuggestionTest.php`:

```php
<?php

use App\Services\Import\ImportSuggestion;

it('is enabled when the feature flag and API key are set', function (): void {
    config([
        'feature.import-suggestions.enabled' => true,
        'ai.providers.ollama-cloud.key' => 'test-key',
    ]);

    expect(ImportSuggestion::isEnabled())->toBeTrue();
});

it('is disabled when the feature flag is off', function (): void {
    config([
        'feature.import-suggestions.enabled' => false,
        'ai.providers.ollama-cloud.key' => 'test-key',
    ]);

    expect(ImportSuggestion::isEnabled())->toBeFalse();
});

it('is disabled when the API key is missing', function (): void {
    config([
        'feature.import-suggestions.enabled' => true,
        'ai.providers.ollama-cloud.key' => null,
    ]);

    expect(ImportSuggestion::isEnabled())->toBeFalse();
});
```

- [ ] **Step 2: Register the test file in the module group map**

In `tests/Pest.php`, next to the existing import mappings (line ~63-66):

```php
pest()->group('module:import')->in('Integration/Services/Import/ImportSuggestionTest.php');
```

- [ ] **Step 3: Create the service**

Generate with `php artisan make:service Import/ImportSuggestion --no-interaction`, then make the class `final` and add the gate (full skeleton; later tasks add instance methods):

```php
<?php

namespace App\Services\Import;

final class ImportSuggestion
{
    public static function isEnabled(): bool
    {
        return (bool) config('feature.import-suggestions.enabled')
            && filled(config('ai.providers.ollama-cloud.key'));
    }
}
```

---

### Task 4: `ImportSuggestions` agent

**Complexity:** Medium

**Files:**
- Create: `app/Ai/Agents/ImportSuggestions.php`
- Test: `tests/Unit/ImportSuggestionsAgentTest.php` (new file)
- Modify: `tests/Pest.php` (module map)

**Interfaces:**
- Consumes: `App\Enums\Category` (`label()`, `description()`, `group()->label()`, `bookable()` semantics), SDK contracts `Agent`, `HasStructuredOutput`, `Promptable`
- Produces: `new ImportSuggestions()->prompt(string $prompt, provider: 'ollama-cloud', timeout: int): StructuredAgentResponse` whose array shape is `['rows' => list<array{row_number: int, category: string, transaction_type: 'income'|'expense'|'transfer'}>]`; fakes via `ImportSuggestions::fake([[...]])`. Task 5 consumes exactly this.

- [ ] **Step 1: Write the failing unit tests**

Create `tests/Unit/ImportSuggestionsAgentTest.php` (Unit suite = pure logic, no booted app — keep the agent free of `config()` calls):

```php
<?php

use App\Ai\Agents\ImportSuggestions;
use Laravel\Ai\Contracts\HasStructuredOutput;

it('lists every bookable category slug in its instructions', function (): void {
    $instructions = (new ImportSuggestions)->instructions();

    expect($instructions)->toContain('dining_out')
        ->toContain('Dining Out')
        ->toContain('Income categories')
        ->toContain('Spending categories')
        ->not->toContain('initial_balance');
});

it('is a structured-output agent', function (): void {
    expect(new ImportSuggestions)->toBeInstanceOf(HasStructuredOutput::class);
});
```

- [ ] **Step 2: Map the unit test into the module group**

In `tests/Pest.php` import-mappings block:

```php
pest()->group('module:import')->in('Unit/ImportSuggestionsAgentTest.php');
```

- [ ] **Step 3: Scaffold and implement the agent**

```bash
php artisan make:agent ImportSuggestions --structured --no-interaction
```

Make the generated class `final` (architecture suite requires it) and implement:

```php
<?php

namespace App\Ai\Agents;

use App\Enums\Cashflow;
use App\Enums\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

final class ImportSuggestions implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        $cases = array_values(array_filter(
            Category::cases(),
            fn (Category $category): bool => $category !== Category::InitialBalance,
        ));

        $render = fn (Category $category): string => sprintf(
            '- %s — %s: %s',
            $category->value,
            $category->label(),
            $category->description(),
        );

        $income = implode("\n", array_map(
            $render,
            array_filter($cases, fn (Category $c): bool => $c->group()->flow() === Cashflow::Inflow),
        ));

        $spending = implode("\n", array_map(
            $render,
            array_filter($cases, fn (Category $c): bool => $c->group()->flow() === Cashflow::Outflow),
        ));

        return <<<TEXT
        You categorize rows from Indonesian bank and e-wallet statements for a personal finance app.

        For every statement row you receive, choose:
        1. A category — the slug of the most specific matching category from the catalog below.
        2. A transaction type — "income", "expense", or "transfer".

        Rules:
        - Inflow rows take a category from the income section; outflow rows take one from the spending section.
        - Prefer the most specific category; fall back to a broader one only when nothing narrower fits.
        - Only use category slugs from the catalog — never invent one.
        - Rows that look like money moving between the user's own accounts (transfers, e-wallet top-ups, "TRF", "TRANSFER", "BI FAST", "SETOR", account-number movements) get transaction_type "transfer" — never guess a destination account.
        - "income"/"expense" must match the row's flow; "transfer" is allowed on either flow.
        - Return one entry per input row, echoing its row number exactly.

        Income categories (for inflow rows):
        {$income}

        Spending categories (for outflow rows):
        {$spending}
        TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'rows' => $schema->array()->items(
                $schema->object(fn ($s) => [
                    'row_number'       => $s->integer()->required(),
                    'category'         => $s->string()->required(),
                    'transaction_type' => $s->string()->enum(['income', 'expense', 'transfer'])->required(),
                ])
            )->required(),
        ];
    }
}
```

(If the scaffold differs from this shape — extra methods, differing imports — keep the scaffold's contract wiring and fill in `instructions()`/`schema()` as above.)

---

### Task 5: `ImportSuggestion::suggestCategories()` core

**Complexity:** Medium

**Files:**
- Modify: `app/Services/Import/ImportSuggestion.php`
- Test: `tests/Integration/Services/Import/ImportSuggestionTest.php` (extend)

**Interfaces:**
- Consumes: `ImportSuggestions` agent (Task 4) — prompt string of `#N | Y-m-d | description | flow` lines; response `['rows' => [...]]`. Enum/model casts from Task 2.
- Produces: `suggestCategories(ImportBatch $batch): void` (instance) — dispatched via `defer()` by Task 6/7. Row updates write only `category_id` (slug) + `transaction_type` (value); `flow` never changes.

- [ ] **Step 1: Write the failing integration tests**

Append to `tests/Integration/Services/Import/ImportSuggestionTest.php` (imports to add: `App\Ai\Agents\ImportSuggestions`, `App\Enums\Category`, `App\Enums\Cashflow`, `App\Enums\ImportBatchStatus`, `App\Enums\ImportSuggestionStatus`, `App\Enums\TransactionType`, `App\Models\ImportBatch`, `App\Models\ImportRow`). File-level helper + `beforeEach` (Pest file-scoped, unique names):

```php
beforeEach(function (): void {
    config([
        'feature.import-suggestions.enabled' => true,
        'ai.providers.ollama-cloud.key' => 'test-key',
    ]);

    $this->batch = ImportBatch::factory()->create([
        'status' => ImportBatchStatus::Staging,
        'suggestion_status' => ImportSuggestionStatus::Pending,
    ]);

    $this->gofood = ImportRow::factory()->create([
        'batch_id' => $this->batch->id,
        'row_number' => 1,
        'description' => 'GOPAY QRIS GOFOOD JAKARTA',
        'amount' => 45000,
        'flow' => Cashflow::Outflow,
        'category_id' => Category::OtherExpenses->value,
        'transaction_type' => TransactionType::Expense->value,
        'parse_errors' => null,
        'raw' => [],
    ]);

    $this->salary = ImportRow::factory()->create([
        'batch_id' => $this->batch->id,
        'row_number' => 2,
        'description' => 'GAJI OKTOBER PT MAJU',
        'amount' => 8000000,
        'flow' => Cashflow::Inflow,
        'category_id' => Category::OtherIncome->value,
        'transaction_type' => TransactionType::Income->value,
        'parse_errors' => null,
        'raw' => [],
    ]);
});

function runSuggestions(ImportBatch $batch): void
{
    resolve(ImportSuggestion::class)->suggestCategories($batch);
}

it('applies suggested categories and types', function (): void {
    ImportSuggestions::fake([['rows' => [
        ['row_number' => 1, 'category' => 'dining_out', 'transaction_type' => 'expense'],
        ['row_number' => 2, 'category' => 'salary', 'transaction_type' => 'income'],
    ]]]);

    runSuggestions($this->batch);

    expect($this->gofood->fresh()->category_id)->toBe(Category::DiningOut)
        ->and($this->salary->fresh()->category_id)->toBe(Category::Salary)
        ->and($this->batch->fresh()->suggestion_status)->toBe(ImportSuggestionStatus::Completed)
        ->and($this->batch->fresh()->suggested_at)->not->toBeNull();
});

it('ignores unknown category slugs', function (): void {
    ImportSuggestions::fake([['rows' => [
        ['row_number' => 1, 'category' => 'not_a_slug', 'transaction_type' => 'expense'],
    ]]]);

    runSuggestions($this->batch);

    expect($this->gofood->fresh()->category_id)->toBe(Category::OtherExpenses);
});

it('ignores direction-illegal categories', function (): void {
    // dining_out flows out; the row flows in — the suggestion must be dropped.
    ImportSuggestions::fake([['rows' => [
        ['row_number' => 2, 'category' => 'dining_out', 'transaction_type' => 'income'],
    ]]]);

    runSuggestions($this->batch);

    expect($this->salary->fresh()->category_id)->toBe(Category::OtherIncome);
});

it('marks transfer without touching the destination', function (): void {
    ImportSuggestions::fake([['rows' => [
        ['row_number' => 1, 'category' => 'admin_bank_fees', 'transaction_type' => 'transfer'],
    ]]]);

    runSuggestions($this->batch);

    $row = $this->gofood->fresh();

    expect($row->transaction_type)->toBe(TransactionType::Transfer)
        ->and($row->transfer_destination_account_id)->toBeNull()
        ->and($row->flow)->toBe(Cashflow::Outflow);
});

it('marks failed when every chunk fails', function (): void {
    ImportSuggestions::fake(fn () => throw new RuntimeException('ollama down'));

    runSuggestions($this->batch);

    expect($this->batch->fresh()->suggestion_status)->toBe(ImportSuggestionStatus::Failed)
        ->and($this->batch->fresh()->suggestion_error)->toBe('ollama down')
        ->and($this->gofood->fresh()->category_id)->toBe(Category::OtherExpenses);
});

it('records partial coverage as completed with an error note', function (): void {
    config(['feature.import-suggestions.chunk_size' => 1]);

    $calls = 0;
    ImportSuggestions::fake(function () use (&$calls): array {
        $calls++;

        // Chunk 1 (gofood) succeeds; every later attempt for chunk 2 fails.
        if ($calls === 1) {
            return ['rows' => [
                ['row_number' => 1, 'category' => 'dining_out', 'transaction_type' => 'expense'],
            ]];
        }

        throw new RuntimeException('chunk 2 exploded');
    });

    runSuggestions($this->batch);

    expect($this->gofood->fresh()->category_id)->toBe(Category::DiningOut)
        ->and($this->batch->fresh()->suggestion_status)->toBe(ImportSuggestionStatus::Completed)
        ->and($this->batch->fresh()->suggestion_error)->toContain('chunk 2 exploded');
});

it('skips rows the user edited after the run started', function (): void {
    $this->gofood->update(['updated_at' => now()->addMinute()]);

    ImportSuggestions::fake([['rows' => [
        ['row_number' => 1, 'category' => 'dining_out', 'transaction_type' => 'expense'],
    ]]]);

    runSuggestions($this->batch);

    expect($this->gofood->fresh()->category_id)->toBe(Category::OtherExpenses);
});

it('no-ops for non-staging batches', function (): void {
    $this->batch->update(['status' => ImportBatchStatus::Approved]);

    ImportSuggestions::fake([['rows' => [
        ['row_number' => 1, 'category' => 'dining_out', 'transaction_type' => 'expense'],
    ]]]);

    runSuggestions($this->batch);

    expect($this->gofood->fresh()->category_id)->toBe(Category::OtherExpenses)
        ->and($this->batch->fresh()->suggestion_status)->toBe(ImportSuggestionStatus::Pending);
});
```

(If the SDK's `fake()` closure signature differs — it receives an `AgentPrompt` — adjust the closures to `fn ($prompt) => ...` with the same bodies.)

- [ ] **Step 2: Implement `suggestCategories()` and the chunk runner**

Add to `app/Services/Import/ImportSuggestion.php` (new imports: `App\Ai\Agents\ImportSuggestions as ImportSuggestionsAgent`, `App\Enums\Category`, `App\Enums\ImportBatchStatus`, `App\Enums\ImportSuggestionStatus`, `App\Enums\TransactionType`, `App\Models\ImportBatch`, `App\Models\ImportRow`, `Carbon\CarbonInterface`, `Illuminate\Support\Collection`, `Throwable`):

```php
/**
 * Runs category/type suggestions for a staged batch. Every failure
 * mode leaves the batch reviewable with its staged defaults.
 */
public function suggestCategories(ImportBatch $batch): void
{
    if ($batch->status !== ImportBatchStatus::Staging
        || ! in_array($batch->suggestion_status, [ImportSuggestionStatus::Pending, ImportSuggestionStatus::Processing], true)) {
        return;
    }

    $startedAt = now();

    $batch->update(['suggestion_status' => ImportSuggestionStatus::Processing]);

    /** @var Collection<int, ImportRow> $rows */
    $rows = $batch->rows()
        ->whereNull('parse_errors')
        ->whereNotNull('flow')
        ->whereNotNull('amount')
        ->where('updated_at', '<=', $startedAt)
        ->orderBy('row_number')
        ->get();

    $errors = [];
    $appliedAny = false;

    foreach ($rows->chunk((int) config('feature.import-suggestions.chunk_size')) as $chunk) {
        // Mid-run lifecycle guard: approving/discarding between chunks stops the run.
        $batch->refresh();

        if ($batch->status !== ImportBatchStatus::Staging) {
            $batch->update([
                'suggestion_status' => ImportSuggestionStatus::Failed,
                'suggestion_error'  => 'Batch left staging before suggestions finished.',
            ]);

            return;
        }

        try {
            $appliedAny = $this->suggestChunk($chunk, $startedAt) || $appliedAny;
        } catch (Throwable) {
            try {
                // One inline retry per chunk — the second failure tolerates the chunk.
                $appliedAny = $this->suggestChunk($chunk, $startedAt) || $appliedAny;
            } catch (Throwable $retryException) {
                $errors[] = $retryException->getMessage();
            }
        }
    }

    $batch->update([
        'suggestion_status' => $appliedAny ? ImportSuggestionStatus::Completed : ImportSuggestionStatus::Failed,
        'suggestion_error'  => $errors === [] ? null : implode(' | ', array_slice($errors, 0, 3)),
        'suggested_at'      => $appliedAny ? now() : null,
    ]);
}

/**
 * @param  Collection<int, ImportRow>  $chunk
 */
private function suggestChunk(Collection $chunk, CarbonInterface $startedAt): bool
{
    $prompt = $chunk->map(
        fn (ImportRow $row): string => sprintf(
            '#%d | %s | %s | %s',
            $row->row_number,
            $row->transaction_date?->toDateString() ?? '—',
            $row->description ?? '—',
            $row->flow->value,
        )
    )->implode("\n");

    $response = (new ImportSuggestionsAgent)->prompt(
        $prompt,
        provider: 'ollama-cloud',
        timeout: (int) config('feature.import-suggestions.timeout'),
    );

    $byRowNumber = $chunk->keyBy(fn (ImportRow $row): int => $row->row_number);
    $applied = false;

    foreach ($response['rows'] as $suggestion) {
        $row = $byRowNumber->get($suggestion['row_number'] ?? 0);

        if ($row === null) {
            continue;
        }

        $category = Category::tryFrom((string) ($suggestion['category'] ?? ''));

        // Unknown or direction-illegal slug — keep the staged default.
        if ($category === null
            || $category === Category::InitialBalance
            || $category->group()->flow() !== $row->flow) {
            continue;
        }

        $type = TransactionType::tryFrom((string) ($suggestion['transaction_type'] ?? ''));

        // Type must match the row's flow, or be a transfer (destination stays the user's pick).
        if ($type === null
            || ($type !== TransactionType::Transfer && $type->toCashflow() !== $row->flow)) {
            continue;
        }

        // Guarded apply — a row the user touched since the run started is skipped, not overwritten.
        $applied = (bool) ImportRow::query()
            ->whereKey($row->id)
            ->where('updated_at', '<=', $startedAt)
            ->update([
                'category_id'      => $category->value,
                'transaction_type' => $type->value,
            ]) || $applied;
    }

    return $applied;
}
```

Note: the guarded `update()` writes raw strings (bulk updates bypass casts), which matches the column storage.

---

### Task 6: ImportService wiring (upload, staging insert, updateRow, approve guard)

**Complexity:** Medium

**Files:**
- Modify: `app/Services/ImportService.php`
- Test: `tests/Feature/App/Import/ImportSuggestionsTest.php` (new file)

**Interfaces:**
- Consumes: `ImportSuggestion::isEnabled()` (Task 3), `suggestCategories()` (Task 5), enum casts (Task 2)
- Produces: upload sets `suggestion_status = Pending` + `defer()`s suggestions when gated on; `ImportRow` staging rows carry `transaction_type`; `updateRow()` persists type with transfer keeping parsed flow; `assertRowImportable()` keys transfer off `transaction_type`

- [ ] **Step 1: Write the failing feature tests**

Create `tests/Feature/App/Import/ImportSuggestionsTest.php` (mirrors `ImportTest.php` setup; imports: `App\Ai\Agents\ImportSuggestions`, `App\Enums\Category`, `App\Enums\Cashflow`, `App\Enums\ImportBatchStatus`, `App\Enums\ImportSuggestionStatus`, `App\Enums\ProviderType`, `App\Models\Account`, `App\Models\ImportBatch`, `App\Models\ImportRow`, `App\Models\Provider`, `App\Models\User`):

```php
<?php

use App\Ai\Agents\ImportSuggestions;

beforeEach(function (): void {
    config([
        'feature.import-suggestions.enabled' => true,
        'ai.providers.ollama-cloud.key' => 'test-key',
    ]);

    $this->provider = Provider::factory()->create([
        'name' => 'Mandiri',
        'slug' => 'mandiri',
        'type' => ProviderType::Bank,
    ]);

    [$this->user, $this->account] = userWithAccount(['provider_id' => $this->provider->id]);
});

test('upload defers suggestions and rows land categorized', function (): void {
    ImportSuggestions::fake([['rows' => [
        ['row_number' => 1, 'category' => 'dining_out', 'transaction_type' => 'expense'],
        ['row_number' => 2, 'category' => 'other_expenses', 'transaction_type' => 'expense'],
        ['row_number' => 3, 'category' => 'salary', 'transaction_type' => 'income'],
    ]]]);

    $this->actingAs($this->user)
        ->post(route('imports.store', $this->account), ['statement' => statementFile()]);

    $batch = ImportBatch::query()->sole();

    // Deferred callbacks flush when the request terminates inside the test
    // client; if they ever stop, $this->app->flushDeferred() forces them.
    expect($batch->suggestion_status)->toBe(ImportSuggestionStatus::Completed)
        ->and($batch->rows->firstWhere('row_number', 1)->category_id)->toBe(Category::DiningOut);
});

test('upload skips suggestions when the gate is closed', function (): void {
    config(['feature.import-suggestions.enabled' => false]);

    $this->actingAs($this->user)
        ->post(route('imports.store', $this->account), ['statement' => statementFile()]);

    expect(ImportBatch::query()->sole()->suggestion_status)->toBeNull();
});

test('staging rows carry a transaction type derived from flow', function (): void {
    $this->actingAs($this->user)
        ->post(route('imports.store', $this->account), ['statement' => statementFile()]);

    expect(ImportRow::query()->get())
        ->each(fn ($row) => expect($row->transaction_type)->not->toBeNull());
});

test('update keeps the parsed flow when the type is transfer', function (): void {
    // stage without suggestions for a deterministic fixture
    config(['feature.import-suggestions.enabled' => false]);
    $this->actingAs($this->user)
        ->post(route('imports.store', $this->account), ['statement' => statementFile()]);
    $batch = ImportBatch::query()->sole();

    $inflowRow = $batch->rows->firstWhere('flow', 'inflow') ?? $batch->rows->first();
    $destination = Account::factory()->create(['owner_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->patch(route('imports.rows.update', ['batch' => $batch->id, 'row' => $inflowRow->id]), [
            'transaction_type' => 'transfer',
            'transfer_destination_account_id' => $destination->id,
        ])
        ->assertRedirectToRoute('imports.show', $batch);

    expect($inflowRow->fresh()->flow)->toBe(Cashflow::Inflow)
        ->and($inflowRow->fresh()->transaction_type->value)->toBe('transfer');
});

test('approve rejects a transfer row without a destination', function (): void {
    config(['feature.import-suggestions.enabled' => false]);
    $this->actingAs($this->user)
        ->post(route('imports.store', $this->account), ['statement' => statementFile()]);
    $batch = ImportBatch::query()->sole();

    ImportRow::query()->first()->update([
        'transaction_type' => 'transfer',
        'transfer_destination_account_id' => null,
    ]);

    $this->actingAs($this->user)
        ->post(route('imports.approve', $batch))
        ->assertSessionHasErrors('rows');

    expect($batch->fresh()->status)->toBe(ImportBatchStatus::Staging);
});
```

- [ ] **Step 2: Wire the gate + defer into `upload()`**

In `app/Services/ImportService.php`: add imports `App\Enums\ImportSuggestionStatus`, `App\Enums\TransactionType`, `App\Services\Import\ImportSuggestion`. Change the tail of `upload()` from `return DB::transaction(...)` to:

```php
$batch = DB::transaction(function () use ($user, $account, $file, $statement): ImportBatch {
    // ... existing staging body unchanged ...
});

if (ImportSuggestion::isEnabled()) {
    $batch->update(['suggestion_status' => ImportSuggestionStatus::Pending]);

    defer(fn () => app(ImportSuggestion::class)->suggestCategories($batch));
}

return $batch;
```

- [ ] **Step 3: Set `transaction_type` in the staging insert**

In the `$rows[]` array inside the same transaction (right after `'flow' => $parsed->flow?->value,`):

```php
'transaction_type' => match (true) {
    $parsed->flow?->isInflow() => TransactionType::Income->value,
    $parsed->flow?->isOutflow() => TransactionType::Expense->value,
    default => null,
},
```

(`insert()` bypasses casts — the value must be the pre-stringified enum value, like its neighbors.)

- [ ] **Step 4: Persist type + flow-keeping transfers in `updateRow()`**

Replace the `'flow' => $transactionType->toCashflow(),` line in the `$row->update([...])` array with:

```php
// Transfer rows keep the parsed flow — the statement's direction decides
// source/destination booking in importTransferRow().
'flow' => $transactionType === TransactionType::Transfer
    ? $row->flow
    : ($transactionType?->toCashflow() ?? $row->flow),
'transaction_type' => $transactionType ?? $row->transaction_type,
```

- [ ] **Step 5: Key the approve guard off `transaction_type`**

In `assertRowImportable()`, replace `$isTransfer = $row->transfer_destination_account_id !== null;` with:

```php
$isTransfer = $row->transaction_type === TransactionType::Transfer;

if (! $isTransfer && $row->transfer_destination_account_id !== null) {
    throw ValidationException::withMessages([
        'rows' => "Row {$row->row_number} has a transfer destination but its type is not transfer.",
    ]);
}
```

---

### Task 7: Retry route, controller action, stale flip, policy

**Complexity:** Medium

**Files:**
- Modify: `routes/web.php` (import group)
- Modify: `app/Http/Controllers/ImportController.php`
- Modify: `app/Policies/ImportBatchPolicy.php`
- Modify: `app/Services/Import/ImportSuggestion.php` (add `retry()` + `failIfStale()`)
- Test: `tests/Feature/App/Import/ImportSuggestionsTest.php` (extend)

**Interfaces:**
- Consumes: `ImportSuggestion::isEnabled()` / `suggestCategories()` (Tasks 3+5), `ImportBatchPolicy::canAccess()`
- Produces: `POST imports/{batch}/suggestions/retry` named `imports.suggestions.retry` → `ImportController::retrySuggestions()` (redirect back to `imports.show`); `ImportSuggestion::retry(ImportBatch)` + `failIfStale(ImportBatch)`; policy ability `retry`

- [ ] **Step 1: Write the failing feature tests**

Append to `tests/Feature/App/Import/ImportSuggestionsTest.php`:

```php
test('retry resets and re-runs suggestions for the owner', function (): void {
    config(['feature.import-suggestions.enabled' => false]);
    $this->actingAs($this->user)
        ->post(route('imports.store', $this->account), ['statement' => statementFile()]);

    $batch = ImportBatch::query()->sole();
    $batch->update(['suggestion_status' => ImportSuggestionStatus::Failed]);

    config(['feature.import-suggestions.enabled' => true]);
    ImportSuggestions::fake([['rows' => [
        ['row_number' => 1, 'category' => 'other_expenses', 'transaction_type' => 'expense'],
    ]]]);

    $this->actingAs($this->user)
        ->post(route('imports.suggestions.retry', $batch))
        ->assertRedirectToRoute('imports.show', $batch);

    // The deferred re-run completed and re-applied a suggestion.
    expect($batch->fresh()->suggestion_status)->toBe(ImportSuggestionStatus::Completed);
});

test('retry is forbidden for a stranger', function (): void {
    config(['feature.import-suggestions.enabled' => false]);
    $this->actingAs($this->user)
        ->post(route('imports.store', $this->account), ['statement' => statementFile()]);

    $batch = ImportBatch::query()->sole();

    $this->actingAs(User::factory()->create())
        ->post(route('imports.suggestions.retry', $batch))
        ->assertForbidden();
});

test('retry rejects non-staging batches', function (): void {
    config(['feature.import-suggestions.enabled' => false]);
    $this->actingAs($this->user)
        ->post(route('imports.store', $this->account), ['statement' => statementFile()]);

    $batch = ImportBatch::query()->sole();
    $batch->update([
        'status' => ImportBatchStatus::Discarded,
        'discarded_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->post(route('imports.suggestions.retry', $batch))
        ->assertUnprocessable();
});

test('show lazily fails stale processing suggestions', function (): void {
    config(['feature.import-suggestions.enabled' => false]);
    $this->actingAs($this->user)
        ->post(route('imports.store', $this->account), ['statement' => statementFile()]);

    $batch = ImportBatch::query()->sole();
    $batch->update([
        'suggestion_status' => ImportSuggestionStatus::Processing,
        'updated_at' => now()->subMinutes(11),
    ]);

    $this->actingAs($this->user)
        ->get(route('imports.show', $batch))
        ->assertOk();

    expect($batch->fresh()->suggestion_status)->toBe(ImportSuggestionStatus::Failed);
});
```

- [ ] **Step 2: Add the policy ability**

In `app/Policies/ImportBatchPolicy.php`, after `discard()`:

```php
public function retry(User $user, ImportBatch $batch): bool
{
    return $this->canAccess($user, $batch);
}
```

- [ ] **Step 3: Add `retry()` and `failIfStale()` to the service**

In `app/Services/Import/ImportSuggestion.php`:

```php
public function retry(ImportBatch $batch): void
{
    $batch->update([
        'suggestion_status' => ImportSuggestionStatus::Pending,
        'suggestion_error'  => null,
        'suggested_at'      => null,
    ]);

    defer(fn () => $this->suggestCategories($batch));
}

public function failIfStale(ImportBatch $batch): void
{
    if ($batch->suggestion_status === ImportSuggestionStatus::Processing
        && $batch->updated_at->lt(now()->subMinutes(10))) {
        $batch->update([
            'suggestion_status' => ImportSuggestionStatus::Failed,
            'suggestion_error'  => 'Timed out while processing suggestions.',
        ]);
    }
}
```

- [ ] **Step 4: Wire the controller**

In `app/Http/Controllers/ImportController.php`: constructor-inject `private readonly ImportSuggestion $importSuggestion` (import `App\Services\Import\ImportSuggestion`, `App\Enums\ImportBatchStatus`). In `show()`, directly after `$this->authorize('view', $batch);`:

```php
$this->importSuggestion->failIfStale($batch);
```

Add the action after `discard()`:

```php
public function retrySuggestions(Request $request, ImportBatch $batch): RedirectResponse
{
    $this->authorize('retry', $batch);

    abort_unless($batch->status === ImportBatchStatus::Staging, 422, 'Only staging batches can retry suggestions.');
    abort_unless(ImportSuggestion::isEnabled(), 422, 'Suggestions are not configured.');

    $this->importSuggestion->retry($batch);

    return to_route('imports.show', $batch)->flash('Retrying suggestions.');
}
```

- [ ] **Step 5: Register the route**

In `routes/web.php`, inside the `Route::controller(ImportController::class)` group, after the `discard` line:

```php
Route::post('imports/{batch}/suggestions/retry', 'retrySuggestions')->name('imports.suggestions.retry');
```

---

### Task 8: Wayfinder + frontend (status chip, polling, transfer marker, editor)

**Complexity:** Medium

**Files:**
- Modify: `resources/js/wayfinder/**` (regenerated — never by hand)
- Create: `resources/js/components/module/import/import-suggestion-status.svelte`
- Modify: `resources/js/pages/app/settings/imports/review.svelte`
- Modify: `resources/js/components/module/import/import-review-table.svelte`
- Modify: `resources/js/components/module/import/import-row-editor-form.svelte`

**Interfaces:**
- Consumes: `batch.suggestion_status` + `row.transaction_type` on Wayfinder-generated types (regenerated in Step 1); `ImportController.retrySuggestions` URL helper
- Produces: `ImportSuggestionStatus` chip component with built-in Retry button

No test: view-only changes (no Browser-suite coverage exists for the import review page).

- [ ] **Step 1: Regenerate Wayfinder types**

```bash
php artisan wayfinder:generate --no-interaction
```

This exposes `ImportController.retrySuggestions.url({ batch })` and adds `transaction_type` to the `ImportRow` model type.

- [ ] **Step 2: Create the status chip component**

`resources/js/components/module/import/import-suggestion-status.svelte`:

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import ImportController from '@wayfinder/App/Http/Controllers/ImportController';

    import Badge from '@components/ui/badge.svelte';
    import Button from '@components/ui/button.svelte';

    let { batch }: { batch: App.Models.ImportBatch } = $props();

    let retrying = $state(false);

    function retry(): void {
        retrying = true;
        router.post(
            ImportController.retrySuggestions.url({ batch: batch.id }),
            {},
            { onFinish: () => (retrying = false) }
        );
    }
</script>

<div class="flex flex-wrap items-center gap-2">
    {#if batch.suggestion_status === 'pending' || batch.suggestion_status === 'processing'}
        <Badge color="info" variant="soft">
            <span class="loading loading-spinner loading-xs mr-1"></span>
            Suggestions: processing…
        </Badge>
    {:else if batch.suggestion_status === 'completed'}
        <Badge color="success" variant="soft">Suggestions applied — review before approving</Badge>
    {:else if batch.suggestion_status === 'failed'}
        <Badge color="warning" variant="soft">Suggestions unavailable</Badge>
        {#if batch.status === 'staging'}
            <Button
                size="sm"
                color="light"
                disabled={retrying}
                onclick={retry}
                variant="outline">
                {retrying ? 'Retrying…' : 'Retry'}
            </Button>
        {/if}
    {/if}
</div>
```

- [ ] **Step 3: Mount the chip + poll while running on the review page**

In `resources/js/pages/app/settings/imports/review.svelte`: add the import (module group, alphabetical):

```ts
import ImportSuggestionStatus from '@components/module/import/import-suggestion-status.svelte';
```

Add a polling effect in the script (next to the other state):

```ts
// Poll while suggestions are still running; Inertia partial reloads keep the page alive.
$effect(() => {
    if (batch.status !== 'staging') return;
    if (batch.suggestion_status !== 'pending' && batch.suggestion_status !== 'processing') return;

    const timer = setInterval(() => {
        router.reload({ only: ['batch', 'rows'] });
    }, 3000);

    return () => clearInterval(timer);
});
```

Render the chip inside the header block, right after the existing `{#if batch.status !== 'staging'}` badge block:

```svelte
{#if batch.suggestion_status}
    <div class="mt-2">
        <ImportSuggestionStatus {batch} />
    </div>
{/if}
```

- [ ] **Step 4: Add the transfer needs-attention marker in the table**

In `resources/js/components/module/import/import-review-table.svelte`, inside the Flags cell (after the existing `transfer_destination_account_id` badge):

```svelte
{#if row.transaction_type === 'transfer' && !row.transfer_destination_account_id}
    <Badge color="warning" variant="soft">Transfer — set destination</Badge>
{/if}
```

- [ ] **Step 5: Seed the editor from the stored type**

In `resources/js/components/module/import/import-row-editor-form.svelte`, replace the inferred `transaction_type` line in `buildInitialData()`:

```ts
transaction_type:
    row.transaction_type ??
    (row.flow === 'inflow' ? TransactionType.Income : TransactionType.Expense),
```
