# Transaction Tagging Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use better-executing-plans to implement this plan task-by-task — or better-parallel-subagents-executions if the plan splits into independent slices, or superpowers:subagent-driven-development if workflow mode is `remote`. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship Phase 1 of transaction tagging — normalized tag input on all transaction types and templates, JSON storage via a `Tags` value object, tag display in list/detail, and per-user tag suggestions.

**Architecture:** A `tags json nullable` column on `transactions` and `transaction_templates`, written through `App\Objects\Tags` (a `Castable` value object mirroring `Decoration`, per `.ai/rules/objects.md`) with `App\Services\TagService` as the single normalization authority (constants + statics; read/computation-only posture per `.ai/rules/services.md`). The write path extends `TransactionData`/`TransferData`/`SaveTransactionTemplateRequest` and both save requests; transfers carry tags on the source (outflow) member row only; templates prefill the create form through `TransactionFormData::fromTemplate`. The frontend adds a domain-agnostic `tags-input` combobox (free-text commit → normalized slug chips) and chip display. The list filter is a separate spec, not this plan.

**Tech Stack:** Laravel 13 / PHP 8.5, Spatie Laravel Data (`#[TypeScript]`), Pest 5, Svelte 5 + bits-ui Combobox, Inertia v3, Wayfinder.

**Workflow Mode:** `direct` — no automated verify/commit; tests are written as part of tasks (TDD as design), never run/committed by the plan itself.

**Complexity:** Medium — rolled up from per-task ratings after Self-Review.

**Spec:** `docs/superpowers/specs/2026-10-28-transaction-tagging-design.md`

## Global Constraints

- Caps come from one place per language: `TagService::MAX_TAGS = 10`, `TagService::MAX_TAG_LENGTH = 32`, `TagService::MAX_RAW_LENGTH = 64` (PHP); mirrored `MAX_TAGS` / `MAX_TAG_LENGTH` in `resources/js/schema/tags.schema.ts` (TS).
- Normalization contract (spec §1): lowercase → collapse `[^\p{L}\p{N}]+` runs to one `_` → trim `_` → truncate 32 → drop empties, dedupe preserving first-seen order → cap 10 → empty set ⇒ `null`.
- Storage: `tags json nullable` on both tables; **`null` when empty — never `[]`, never an empty JSON array**.
- Transfers: tags are written to the **source (outflow) member row only**; destination and fee rows stay `null`.
- TS normalization is best-effort preview; the backend re-normalizes on every write and is authoritative.
- Count > `MAX_TAGS` → 422; raw tag > `MAX_RAW_LENGTH` → 422; raw ≤ 64 that normalizes longer than 32 → silent truncate (spec §4).
- Tests follow `.ai/rules/tests.md`: Feature uses `test(...)` + named `route(...)` + `assertSessionHasErrors()` for validation refusals + `expect()` with an Eloquent re-query for persisted state; Unit/Integration use `it(...)` with named dataset cases; factories only; each test makes its own data; refusal paths assert nothing changed.
- No new dependencies. Kebab-case file names; Svelte 5 runes with the block order `<script module>` → `<script>` → markup → snippets; Solar/Tabler icons only; no i18n; no new listeners (events are extension points).
- New frontend styling uses **shadcn design tokens** (`bg-muted`, `text-muted-foreground`, `bg-secondary`, `bg-primary/10`, …) — never DaisyUI `base-*` utilities in the new components.
- After DTO/model changes: `composer generate:ts` and `php artisan wayfinder:generate` (generation is part of the work, not verification). Never hardcode URLs — Wayfinder imports in app code, `route()` in tests.

---

### Task 1: TagService — normalization authority

**Complexity:** Medium

**Files:**
- Create: `app/Services/TagService.php` (generate with `php artisan make:service TagService --no-interaction`)
- Test: `tests/Integration/Services/TagServiceTest.php`

**Interfaces:**
- Consumes: nothing (pure static).
- Produces: `TagService::MAX_TAGS`, `MAX_TAG_LENGTH`, `MAX_RAW_LENGTH` (int constants); `TagService::normalize(array $raw): array`; `TagService::normalizeToNullable(array $raw): ?array` — used by Tasks 2, 3, 4, 6, 10.

- [ ] **Step 1: Write the failing test** — Integration suite, `it()` declarations, named dataset cases (style of `tests/Integration/Services/TransactionTemplateServiceTest.php`):

```php
<?php

use App\Services\TagService;

it('normalizes raw tags to ordered unique snake_case slugs', function (array $raw, array $expected): void {
    expect(TagService::normalize($raw))->toBe($expected);
})->with([
    'symbols and casing collapse'        => [['Bills & Rent!'], ['bills_rent']],
    'leading trailing junk is trimmed'   => [['--FOOD--'], ['food']],
    'case and symbol variants dedupe'    => [['Food', 'food!', 'FOOD'], ['food']],
    'separator runs collapse to one'     => [['Kopi  Senja!!'], ['kopi_senja']],
    'surrounding spacing is trimmed'     => [['  spacing  test  '], ['spacing_test']],
    'slug truncates at 32 chars'         => [[str_repeat('a', 40)], [str_repeat('a', 32)]],
    'per-tag truncation before dedupe'   => [['a', str_repeat('b', 64), 'a'], ['a', str_repeat('b', 32)]],
    'symbol-only input drops out'        => [['!!!', '   ', ''], []],
]);

it('keeps unicode letters and digits', function (): void {
    expect(TagService::normalize(['Görk Uçuş-2']))->toBe(['görk_uçuş_2']);
});

it('caps the list at MAX_TAGS preserving first-seen order', function (): void {
    $raw = array_map(fn (int $i): string => "tag {$i}", range(1, 12));

    $normalized = TagService::normalize($raw);

    expect($normalized)->toHaveCount(TagService::MAX_TAGS)
        ->and($normalized[0])->toBe('tag_1')
        ->and($normalized[9])->toBe('tag_10');
});

it('returns null when nothing survives normalization', function (array $raw): void {
    expect(TagService::normalizeToNullable($raw))->toBeNull();
})->with([
    'empty input'      => [[]],
    'symbol-only tags' => [['!!!']],
    'blank strings'    => [['', '  ']],
]);
```

- [ ] **Step 2: Write minimal implementation**

```php
<?php

namespace App\Services;

/**
 * Normalization authority + suggestion reads for transaction tags. Pure
 * computation and reads only — no writes, all static (services.md posture).
 */
final class TagService
{
    public const int MAX_TAGS = 10;

    public const int MAX_TAG_LENGTH = 32;

    /** Sanity ceiling for un-normalized client input — longer raw values 422 upstream. */
    public const int MAX_RAW_LENGTH = 64;

    /**
     * Lowercase, collapse non letter/number runs to one underscore, trim,
     * truncate, drop empties, dedupe preserving first-seen order, cap.
     *
     * @param  list<string>  $raw
     * @return list<string>
     */
    public static function normalize(array $raw): array
    {
        $slugs = [];

        foreach ($raw as $value) {
            $slug = mb_substr(
                trim((string) preg_replace('/[^\p{L}\p{N}]+/u', '_', mb_strtolower((string) $value)), '_'),
                0,
                self::MAX_TAG_LENGTH,
            );

            if ($slug !== '' && ! in_array($slug, $slugs, true)) {
                $slugs[] = $slug;
            }

            if (count($slugs) === self::MAX_TAGS) {
                break;
            }
        }

        return $slugs;
    }

    /**
     * @param  list<string>  $raw
     * @return list<string>|null
     */
    public static function normalizeToNullable(array $raw): ?array
    {
        $slugs = self::normalize($raw);

        return $slugs === [] ? null : $slugs;
    }
}
```

Do not add `getUniqueTags` yet (Task 6).

### Task 2: `App\Objects\Tags` value object + migration + model cast

**Complexity:** Medium

**Files:**
- Create: `app/Objects/Tags.php`
- Create: `database/migrations/2026_10_28_000000_add_tags_to_transactions_table.php`
- Modify: `app/Models/Transaction.php` (casts)
- Test: `tests/Unit/Objects/TagsTest.php` (pure), `tests/Integration/Models/TagsTest.php` (cast round-trip, style of `DecorationsTest`)

**Interfaces:**
- Consumes: `TagService::normalizeToNullable()` (Task 1).
- Produces: `Tags::fromArray(?array $raw): ?self`, `Tags::toArray(): array` (`list<string>`); `Transaction::$tags` attribute is `?Tags`; cast declared as `'tags' => Tags::class`. Used by Tasks 3–6, 10.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Objects/TagsTest.php` (Unit suite — no DB, `it()` style of `DatePeriodTest`):

```php
<?php

use App\Objects\Tags;

it('wraps normalized slugs', function (): void {
    expect(Tags::fromArray(['Bills & Rent!'])?->toArray())->toBe(['bills_rent']);
});

it('returns null for null and all-dropped input', function (?array $raw): void {
    expect(Tags::fromArray($raw))->toBeNull();
})->with([
    'null input'  => [null],
    'empty list'  => [[]],
    'symbol-only' => [['!!!']],
]);
```

`tests/Integration/Models/TagsTest.php` (cast round-trip through the real model):

```php
<?php

use App\Objects\Tags;
use App\Models\Transaction;

it('normalizes on write and hydrates a Tags object on read', function (): void {
    $transaction = Transaction::factory()->create(['tags' => ['Food', 'food!']]);

    expect(json_decode($transaction->getRawOriginal('tags'), true))->toBe(['food'])
        ->and($transaction->tags)->toBeInstanceOf(Tags::class)
        ->and($transaction->tags->toArray())->toBe(['food']);
});

it('stores null when tags are absent or empty', function (): void {
    $absent = Transaction::factory()->create();

    $empty = Transaction::factory()->create(['tags' => []]);

    expect($absent->getRawOriginal('tags'))->toBeNull()
        ->and($empty->getRawOriginal('tags'))->toBeNull();
});

it('accepts a Tags instance on write without re-normalizing damage', function (): void {
    $transaction = Transaction::factory()->make();

    $transaction->fill(['tags' => Tags::fromArray(['a', 'A!'])])->save();

    expect($transaction->fresh()->tags?->toArray())->toBe(['a']);
});
```

- [ ] **Step 2: Write the migration** (`database/migrations/2026_10_28_000000_add_tags_to_transactions_table.php`):

```php
public function up(): void
{
    Schema::table('transactions', function (Blueprint $table): void {
        $table->json('tags')->nullable()->after('description');
    });
}

public function down(): void
{
    Schema::table('transactions', function (Blueprint $table): void {
        $table->dropColumn('tags');
    });
}
```

No index (spec §2 — the read window is already user + period bounded).

- [ ] **Step 3: Implement `App\Objects\Tags`** — mirror `app/Objects/Decoration.php` exactly in structure (`.ai/rules/objects.md`: Castable + inline anonymous `CastsAttributes` from `castUsing()`, `fromArray()`/`toArray()`, cast declared by class name — no `app/Casts` directory):

```php
<?php

namespace App\Objects;

use App\Services\TagService;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Immutable list of normalized transaction-tag slugs. Persisted as JSON
 * through the inline cast; every write re-normalizes via TagService.
 */
final readonly class Tags implements Castable
{
    /** @param list<string> $slugs */
    public function __construct(
        public readonly array $slugs,
    ) {}

    /** @param list<string>|null $raw */
    public static function fromArray(?array $raw): ?self
    {
        $slugs = $raw !== null ? TagService::normalizeToNullable($raw) : null;

        return $slugs !== null ? new self($slugs) : null;
    }

    /** @return list<string> */
    public function toArray(): array
    {
        return $this->slugs;
    }

    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get(Model $model, string $key, mixed $value, array $attributes): ?Tags
            {
                if ($value === null) {
                    return null;
                }

                $decoded = json_decode($value, true);

                return is_array($decoded) ? new Tags(TagService::normalize($decoded)) : null;
            }

            public function set(Model $model, string $key, mixed $value, array $attributes): ?string
            {
                if ($value === null) {
                    return null;
                }

                if ($value instanceof Tags) {
                    return json_encode($value->toArray());
                }

                if (is_array($value)) {
                    return json_encode(TagService::normalize($value));
                }

                throw new InvalidArgumentException('Tags value must be null, an array, or a Tags instance.');
            }
        };
    }
}
```

- [ ] **Step 4: Wire the cast** — `app/Models/Transaction.php` `casts()` gains `'tags' => Tags::class`.

### Task 3: Plain-row write path

**Complexity:** Medium

**Files:**
- Modify: `app/Http/Requests/SaveTransactionRequest.php` (rules)
- Modify: `app/Data/Transaction/TransactionData.php` (+`$tags`)
- Modify: `app/Services/TransactionService.php` (create/update pass-through)
- Test: `tests/Feature/App/TransactionTaggingTest.php` (new)

**Interfaces:**
- Consumes: `Tags` cast (Task 2), `TagService` constants (Task 1).
- Produces: request payload key `tags` (`null | list<string>`); `TransactionData::$tags` (`?array`, `@param list<string>|null`) — Task 4's transfer path reuses both.

- [ ] **Step 1: Write the failing tests** — Feature suite: `test()` declarations, named `route()` URLs, file-local helpers with globally unique names, AAA with blank-line separation (`.ai/rules/tests.md`):

```php
<?php

use App\Enums\Category;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

function createTaggingAccount(): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    return [$user, $account];
}

function taggingExpensePayload(Account $account): array
{
    return [
        'account_id' => $account->id,
        'type' => TransactionType::Expense->value,
        'amount' => 25_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => Category::GeneralShopping->value,
    ];
}

test('stores normalized tags on a plain transaction', function (): void {
    [$user, $account] = createTaggingAccount();

    $this->actingAs($user)->post(route('transactions.store'), [
        ...taggingExpensePayload($account),
        'tags' => ['Bills & Rent!', 'food!'],
    ])->assertRedirectToRoute('transactions.index');

    $stored = Transaction::query()->where('account_id', $account->id)->first();

    expect($stored->tags?->toArray())->toBe(['bills_rent', 'food']);
});

test('stores null when tags are omitted or all-dropped', function (): void {
    [$user, $account] = createTaggingAccount();

    $this->actingAs($user)->post(route('transactions.store'), taggingExpensePayload($account));
    $this->actingAs($user)->post(route('transactions.store'), [
        ...taggingExpensePayload($account),
        'tags' => ['!!!'],
    ]);

    expect(Transaction::query()->where('account_id', $account->id)->get())
        ->each(fn (Transaction $row) => $row->tags)->toBeNull()->toBeNull();
});

test('rejects more than MAX_TAGS tags without persisting', function (): void {
    [$user, $account] = createTaggingAccount();

    $this->actingAs($user)->post(route('transactions.store'), [
        ...taggingExpensePayload($account),
        'tags' => array_fill(0, TagService::MAX_TAGS + 1, 'x'),
    ])->assertSessionHasErrors('tags');

    expect(Transaction::query()->where('account_id', $account->id)->exists())->toBeFalse();
});

test('rejects a raw tag over MAX_RAW_LENGTH without persisting', function (): void {
    [$user, $account] = createTaggingAccount();

    $this->actingAs($user)->post(route('transactions.store'), [
        ...taggingExpensePayload($account),
        'tags' => [str_repeat('a', TagService::MAX_RAW_LENGTH + 1)],
    ])->assertSessionHasErrors('tags.0');

    expect(Transaction::query()->where('account_id', $account->id)->exists())->toBeFalse();
});

test('replaces tags wholesale on update', function (): void {
    [$user, $account] = createTaggingAccount();
    $transaction = Transaction::factory()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'tags' => ['a'],
    ]);

    $this->actingAs($user)->put(route('transactions.update', $transaction), [
        ...taggingExpensePayload($account),
        'tags' => ['b'],
    ])->assertRedirectToRoute('transactions.index');

    expect($transaction->fresh()->tags?->toArray())->toBe(['b']);
});
```

(Add the `use App\Services\TagService;` import.)

- [ ] **Step 2: Write minimal implementation**

`SaveTransactionRequest::rules()` gains:

```php
'tags'   => ['nullable', 'array', 'max:' . TagService::MAX_TAGS],
'tags.*' => ['string', 'max:' . TagService::MAX_RAW_LENGTH],
```

`TransactionData` — after `description`:

```php
/** @param list<string>|null */
public readonly ?array $tags = null,
```

`TransactionService::create()` and `update()` attribute arrays each gain `'tags' => $data->tags` — the cast normalizes; nothing else changes.

### Task 4: Transfer write path — source row only

**Complexity:** Medium

**Files:**
- Modify: `app/Http/Requests/SaveTransferRequest.php` (same `tags` rules as Task 3)
- Modify: `app/Data/Transaction/TransferData.php` (+`$tags`)
- Modify: `app/Services/TransferService.php` (`createUnitTransactions`)
- Test: `tests/Integration/Services/TransferServiceTest.php` (extend, `it()` style)

**Interfaces:**
- Consumes: `TransactionData::$tags` (Task 3).
- Produces: transfer units where only the source (outflow) row carries tags; `TransferData::$tags` (`?array`).

- [ ] **Step 1: Write the failing tests**

```php
it('puts transfer tags on the source row only', function (): void {
    $user = User::factory()->create();
    $source = Account::factory()->create(['owner_id' => $user->id]);
    $destination = Account::factory()->create(['owner_id' => $user->id]);

    $transfer = app(TransferService::class)->create($user, new TransferData(
        account_id: $source->id,
        destination_account_id: $destination->id,
        amount: 500_000,
        transaction_date: now()->toDateString(),
        fee_amount: 2_500,
        tags: ['monthly_sweep'],
    ));

    $rows = $transfer->transactions->fresh();

    expect($rows->firstWhere('flow', Cashflow::Outflow)->tags?->toArray())->toBe(['monthly_sweep'])
        ->and($rows->firstWhere('flow', Cashflow::Inflow)->tags)->toBeNull()
        ->and($rows->firstWhere('type', TransactionType::Expense)->tags)->toBeNull();
});

it('keeps tags on the source row across unit edit-recreate', function (): void {
    // arrange: create as above with tags ['a']
    // act: app(TransferService::class)->update($transfer, new TransferData(..., tags: ['b']))
    // assert: recreated outflow row tags === ['b']; inflow and fee rows still null
});
```

- [ ] **Step 2: Write minimal implementation** — `TransferData` gains `public readonly ?array $tags = null` after `description` (PHPDoc `@param list<string>|null`). `SaveTransferRequest` gains the same two rules as Task 3. In `TransferService::createUnitTransactions()`, add `tags: $data->tags` **only** to the first (source/outflow) `TransactionData` construction; the destination and fee constructions get no `tags` argument.

### Task 5: Read path — DTOs + cache version bump

**Complexity:** Medium

**Files:**
- Modify: `app/Data/Transaction/TransactionListData.php` (ctor, `fromTransaction`, `collectFromTransactions`, `row()`)
- Modify: `app/Data/Transaction/TransactionDetailData.php` (ctor + `fromTransaction`)
- Modify: `app/Data/Transaction/TransactionFormData.php` (ctor + `fromTransaction`, `fromTransfer`, `defaultFromRequest` — `fromTemplate` is Task 10)
- Modify: `app/Services/TransactionListService.php` (`cacheKey` version segment)
- Test: `tests/Feature/App/TransactionTaggingTest.php` (extend), `tests/Feature/App/TransactionListCacheTest.php` (extend)

**Interfaces:**
- Consumes: `Transaction::$tags: ?Tags` (Task 2).
- Produces: wire field `tags: string[] | null` on list/detail/form DTOs (Tasks 9, 11); `TransactionListService::cacheKey()` versioned.

- [ ] **Step 1: Write the failing tests** — in `TransactionTaggingTest.php` (pin `transaction_date` — the list window is period-dependent):

```php
test('exposes tags on list rows and the detail DTO', function (): void {
    [$user, $account] = createTaggingAccount();

    $tagged = Transaction::factory()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'transaction_date' => now()->subDay()->format('Y-m-d H:i:s'),
        'tags' => ['food'],
    ]);

    $untagged = Transaction::factory()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'transaction_date' => now()->subDay()->format('Y-m-d H:i:s'),
    ]);

    $rows = TransactionListService::getTransactions($user, DatePeriodPreset::YearOnYear);

    expect(collect($rows)->firstWhere('id', $tagged->id)['tags'])->toBe(['food'])
        ->and(collect($rows)->firstWhere('id', $untagged->id)['tags'])->toBeNull()
        ->and(TransactionDetailData::fromTransaction($tagged->fresh())->tags)->toBe(['food']);
});

test('seeds the edit form from the row — transfers from the source row', function (): void {
    [$user, $account] = createTaggingAccount();

    $tagged = Transaction::factory()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'tags' => ['food'],
    ]);

    $transfer = Transfer::factory()->create(); // with source/destination rows via existing seeding helpers in this file's style

    expect(TransactionFormData::fromTransaction($tagged)->tags)->toBe(['food'])
        ->and(TransactionFormData::fromTransfer($transfer)->tags)->toBe($transfer->sourceTransaction->tags?->toArray())
        ->and(TransactionFormData::defaultFromRequest(request())->tags)->toBeNull();
});
```

In `TransactionListCacheTest.php`:

```php
test('serves the tags-bearing row shape under the versioned cache key', function (): void {
    $user = User::factory()->create();

    expect(TransactionListService::cacheKey($user->id))->toBe('transaction-list:v2:user:' . $user->id);

    $rows = TransactionListService::getCachedTransactions($user, DatePeriodPreset::default());

    expect(collect($rows)->every(fn (array $row) => array_key_exists('tags', $row)))->toBeTrue();
});
```

- [ ] **Step 2: Write minimal implementation** — each DTO gains `public readonly ?array $tags` (`@param list<string>|null`), mapped as `$transaction->tags?->toArray()` in every constructor site: `TransactionListData` (3 mappers — `fromTransaction`, `collectFromTransactions`, `row()`), `TransactionDetailData::fromTransaction`, `TransactionFormData::fromTransaction`. `TransactionFormData::fromTransfer` reads `$transfer->sourceTransaction?->tags?->toArray()`; `defaultFromRequest` yields `null`. `TransactionListService::cacheKey()` becomes `"transaction-list:v2:user:{$userId}"` with its docblock updated (v1 entries orphan and expire — the documented pattern).

- [ ] **Step 3: Regenerate types** — `composer generate:ts && php artisan wayfinder:generate` (Tasks 8–11 consume the generated `tags` fields).

### Task 6: Suggestions — `TagService::getUniqueTags` + transaction controller props

**Complexity:** Medium

**Files:**
- Modify: `app/Services/TagService.php` (+ method)
- Modify: `app/Http/Controllers/TransactionController.php` (`create`, `edit`)
- Test: `tests/Integration/Services/TagServiceTest.php` (extend), `tests/Feature/App/TransactionTaggingTest.php` (extend)

**Interfaces:**
- Consumes: `Transaction::$tags` (Task 2).
- Produces: `TagService::getUniqueTags(User $user): array` (`list<string>`, sorted); Inertia prop `tags` on transaction create/edit pages — Tasks 9, 10 consume.

- [ ] **Step 1: Write the failing tests**

In `TagServiceTest.php`:

```php
it('returns the sorted distinct tag union across the user’s live rows', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);
    $other = User::factory()->create();

    $transfer = Transfer::factory()->create(['created_by' => $user->id]);

    Transaction::factory()->create(['account_id' => $account->id, 'created_by' => $user->id, 'tags' => ['bills']]);
    Transaction::factory()->transferOutflow($transfer)->create(['created_by' => $user->id, 'tags' => ['food']]);
    Transaction::factory()->transferFee($transfer)->create(['created_by' => $user->id]); // untagged fee
    Transaction::factory()->create(['created_by' => $other->id, 'tags' => ['private']]);

    expect(TagService::getUniqueTags($user))->toBe(['bills', 'food']);
});

it('excludes soft-deleted rows from tag suggestions', function (): void {
    // create tagged row, $row->delete(), expect getUniqueTags === [] (minus other rows)
});
```

In `TransactionTaggingTest.php`:

```php
test('passes the tags prop on the create and edit pages', function (): void {
    [$user, $account] = createTaggingAccount();
    Transaction::factory()->create(['account_id' => $account->id, 'created_by' => $user->id, 'tags' => ['food']]);

    $this->actingAs($user)->get(route('transactions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('app/transaction/create')->where('tags', ['food']));

    $transaction = Transaction::factory()->create(['account_id' => $account->id, 'created_by' => $user->id]);

    $this->actingAs($user)->get(route('transactions.edit', $transaction))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('app/transaction/edit')->where('tags', ['food']));
});
```

- [ ] **Step 2: Write minimal implementation** — raw SQL so the JSON array is exploded and de-duplicated in the database (`use Illuminate\Support\Facades\DB;`):

```php
/** Distinct tags across the user’s live transactions, sorted. @return list<string> */
public static function getUniqueTags(User $user): array
{
    $rows = DB::select(
        <<<'SQL'
        SELECT DISTINCT jt.tag
        FROM transactions t,
             JSON_TABLE(t.tags, '$[*]' COLUMNS (tag VARCHAR (255) PATH '$')) jt
        WHERE t.created_by = ?
          AND t.deleted_at IS NULL
          AND t.tags IS NOT NULL
        ORDER BY jt.tag
        SQL,
        [$user->id],
    );

    return array_map(strval(...), array_column($rows, 'tag'));
}
```

Raw SQL bypasses `SoftDeletes` — the explicit `deleted_at IS NULL` is load-bearing, and the `excludes soft-deleted rows` test pins it. The `VARCHAR(255)` width is deliberately unreachable for a normalized tag (cap 32), so a truncation there is itself a tripwire that upstream normalization broke — no constant coupling needed. `TransactionController::create()` and `edit()` props gain `'tags' => TagService::getUniqueTags($this->user)`.

### Task 7: Frontend — `StringHelper.toSnakeCase` + `tags.schema.ts`

**Complexity:** Low

**Files:**
- Modify: `resources/js/utilities/string-helper.ts` (+ static method on the existing default-exported class)
- Create: `resources/js/schema/tags.schema.ts`

**Interfaces:**
- Produces: `StringHelper.toSnakeCase(value: string): string`; `tags.schema.ts` exports `MAX_TAGS: number`, `MAX_TAG_LENGTH: number`, `normalizeTag(raw: string): string`, `normalizeTags(raw: string[]): string[]` — Tasks 8–10 consume. No JS unit runner in the stack — no test (behavior is pinned by the PHP side; these are trivial mirrors; per resources-js.md the helper lives in `utilities/` so it is reusable).

- [ ] **Step 1: Add `toSnakeCase` to `StringHelper`** — docblock style matching `getInitials`:

```ts
/**
 * Lowercase, collapse every non letter/number run to a single
 * underscore, and trim underscores from both ends.
 */
public static toSnakeCase(value: string): string {
    return value.toLowerCase().replace(/[^\p{L}\p{N}]+/gu, '_').replace(/^_+|_+$/g, '');
}
```

- [ ] **Step 2: Create `resources/js/schema/tags.schema.ts`** — default-import `StringHelper` (it is a default export):

```ts
import StringHelper from '@utilities/string-helper';

export const MAX_TAGS = 10;
export const MAX_TAG_LENGTH = 32;

export function normalizeTag(raw: string): string {
    return StringHelper.toSnakeCase(raw).slice(0, MAX_TAG_LENGTH);
}

export function normalizeTags(raw: string[]): string[] {
    return Array.from(new Set(raw.map(normalizeTag).filter(Boolean))).slice(0, MAX_TAGS);
}
```

### Task 8: `tags-input` component

**Complexity:** Medium

**Files:**
- Create: `resources/js/components/ui/forms/tags-input.svelte`

**Interfaces:**
- Consumes: `normalizeTags` / `normalizeTag` / `MAX_TAGS` (Task 7), `combobox.svelte` wrapper (bits-ui, `type="multiple"`), chip markup conventions.
- Produces: `<TagsInput bind:value tags placeholder />` — `value: string[]` (selected slugs, bindable), `tags?: string[]` (suggestion slugs, defaults `[]`), `placeholder?: string`. Tasks 9, 10 consume. View-only component, no JS test runner — no test.

- [ ] **Step 1: Implement the component** — Svelte 5 runes; block order `<script>` → markup → snippets; bits-ui Combobox `type="multiple"` with a custom trigger snippet following the `category-select.svelte` pattern (`ComboboxTriggerContext`, `ComboboxPrimitive.Trigger` + `ComboboxPrimitive.Input`, `bind:open`). Script sketch:

```ts
import Combobox, { type ComboboxTriggerContext } from '@components/ui/forms/combobox.svelte';
import { Combobox as ComboboxPrimitive } from 'bits-ui';
import { MAX_TAGS, normalizeTag } from '@schema/tags.schema';

interface Props {
    value?: string[];
    suggestions?: string[];
    placeholder?: string;
    disabled?: boolean;
}

let {
        value = $bindable([]),
        suggestions = [],
        placeholder = 'Add tag…',
        disabled = false,
    }: Props = $props();

let input = $state('');
let open = $state(false);

const filtered = $derived(
    suggestions.filter((slug) => !value.includes(slug) && slug.includes(normalizeTag(input))),
);

const creatableSlug = $derived.by(() => {
    const slug = normalizeTag(input);
    return slug && !value.includes(slug) && !suggestions.includes(slug) ? slug : null;
});

function commit(raw: string): void {
    const slug = normalizeTag(raw);
    if (slug && !value.includes(slug) && value.length < MAX_TAGS) {
        value = [...value, slug];
    }
    input = '';
}

function remove(slug: string): void {
    value = value.filter((s) => s !== slug);
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        commit(input);
    } else if (event.key === 'Backspace' && input === '' && value.length > 0) {
        value = value.slice(0, -1);
    }
}
```

Markup: chips inline before the input, styled with shadcn tokens — `rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary` (the token treatment `transaction-list-filter.svelte`'s active-filter chips already use) with a remove `×` button; chips render the slug only (what you see is what is stored); input `disabled` when `value.length >= MAX_TAGS`; the suggestion list offers `filtered` plus a "Create `<creatableSlug>`" row when `creatableSlug` is non-empty. All interactive elements are real `<button>`s; icons if any are `solar--` prefixed only. No DaisyUI `base-*` classes anywhere in the component.

### Task 9: Transaction form integration

**Complexity:** Low

**Files:**
- Modify: `resources/js/components/module/transaction/transaction-form.svelte`
- Modify: `resources/js/pages/app/transaction/create.svelte`, `edit.svelte` — pass the `tags` prop through to the form

**Interfaces:**
- Consumes: `TagsInput` (Task 8), `tags` page prop (Task 6), `TransactionFormData.tags` (Task 5 generated types).
- Produces: all-type tags field on the create/edit form; submitted `tags: string[]`. View-only integration — no test.

- [ ] **Step 1: Integrate the field** — `Props` gains `tags?: string[]`; `buildInitialData()` gains `tags: transaction.tags ?? []`; render one field for **all types** — after the Category/Destination conditional block, before Date & Time:

```svelte
<Field
    titleClass="uppercase text-base-content/50 tracking-wide font-bold text-xs"
    error={form.errors.tags}
    title="Tags">
    <TagsInput bind:value={form.tags} {tags} placeholder="Add tags…" />
</Field>
```

`create.svelte` / `edit.svelte` pass `tags={tags}` from their page props. **No `form.transform` change** — tags must survive both branches, and the existing transform never strips unknown keys.

### Task 10: Template tags — storage, prefill, management form

**Complexity:** Medium

**Files:**
- Create: `database/migrations/2026_10_28_000001_add_tags_to_transaction_templates_table.php`
- Modify: `app/Models/TransactionTemplate.php` (cast)
- Modify: `app/Http/Requests/SaveTransactionTemplateRequest.php` (rules)
- Modify: `app/Data/Transaction/TransactionFormData.php` (`fromTemplate`)
- Modify: `app/Http/Controllers/Settings/TransactionTemplateController.php` (`create`, `edit` props)
- Modify: `resources/js/components/module/template/template-form.svelte` (TagsInput field)
- Modify: `resources/js/components/module/template/template-detail.svelte` (chip row)
- Test: `tests/Feature/App/TransactionTemplateTest.php` (extend, `test()` style), `tests/Feature/App/TransactionTemplateSeedingTest.php` (extend)

**Interfaces:**
- Consumes: `Tags` cast (Task 2), `TagService` constants + `getUniqueTags` (Tasks 1, 6), `TagsInput` (Task 8).
- Produces: template rows with normalized tags; `fromTemplate` prefill; `tags` prop on template form pages.

- [ ] **Step 1: Write the failing tests**

In `TransactionTemplateTest.php` (reuse its existing fixtures/payload builders):

```php
test('stores template tags normalized', function (): void {
    // acting as user, existing template payload builder + 'tags' => ['Bills & Rent!']
    // → assertRedirectToRoute, re-query template → tags?->toArray() === ['bills_rent']
});

test('rejects template tags over the cap without persisting', function (): void {
    // 'tags' => 11 items → assertSessionHasErrors('tags'); no template row persisted
});
```

In `TransactionTemplateSeedingTest.php`:

```php
test('prefills the create form with the template’s tags', function (): void {
    // template tagged ['food']
    // TransactionFormData::fromTemplate($template)->tags === ['food']
    // GET route('transactions.create', ['template' => $template->id])
    //   → assertInertia component + ->where('transaction.tags', ['food'])
});
```

- [ ] **Step 2: Write minimal implementation** — migration (`Schema::table('transaction_templates', ...)`: `$table->json('tags')->nullable()->after('decorations');`, symmetric `dropColumn` down — no index). `TransactionTemplate::casts()` gains `'tags' => Tags::class`. `SaveTransactionTemplateRequest` gains the same two rules as Task 3 (no service change — `TransactionTemplateService` spreads `validated()`, the cast normalizes). `fromTemplate` gains `tags: $template->tags?->toArray()`. `Settings\TransactionTemplateController::create()` and `edit()` props gain `'tags' => TagService::getUniqueTags($this->user)`.

- [ ] **Step 3: Template form + detail** — `template-form.svelte`: one `TagsInput` seeded from the template row on edit (`template?.tags ?? []`), `suggestions` from the new page prop, submitted as `tags` alongside the existing fields. `template-detail.svelte`: the template's tags as chips (same markup as Task 11). Then `composer generate:ts`.

### Task 11: Display — list item + detail chips

**Complexity:** Low

**Files:**
- Modify: `resources/js/components/module/transaction/transaction-list-item.svelte`
- Modify: `resources/js/components/module/transaction/transaction-detail.svelte`

**Interfaces:**
- Consumes: wire field `tags` on list/detail rows (Task 5). View-only — no test.

- [ ] **Step 1: Implement the chips** — list item: when `transaction.tags?.length`, render up to two muted mini-chips plus `+n` when more (placed with the description; shadcn tokens that don't fight the row's type color):

```svelte
{#if transaction.tags?.length}
    <div class="mt-0.5 flex flex-wrap gap-1">
        {#each transaction.tags.slice(0, 2) as tag (tag)}
            <span class="rounded-full bg-muted px-2 py-0.5 text-[0.65rem] font-medium text-muted-foreground">{tag}</span>
        {/each}
        {#if transaction.tags.length > 2}
            <span class="rounded-full bg-muted px-2 py-0.5 text-[0.65rem] font-medium text-muted-foreground">+{transaction.tags.length - 2}</span>
        {/if}
    </div>
{/if}
```

Detail (`transaction-detail.svelte`): the full chip row, same chip markup without the slice cap.

---

## Post-plan note

The list filter is **not** in this plan — it has its own approved spec: `docs/superpowers/specs/2026-10-28-transaction-tag-filter-design.md` (implement after this plan ships, with its own plan).
