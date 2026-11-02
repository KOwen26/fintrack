# Transaction Tag Filter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use better-executing-plans to implement this plan task-by-task — or better-parallel-subagents-executions if the plan splits into independent slices, or superpowers:subagent-driven-development if workflow mode is `remote`. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Filter the transaction list by tags — a `tags` filter id through the request/filters-DTO/service query, tag matching in global search, a `tags` prop on the index page, and a Tags filter chip + sheet in the list UI.

**Architecture:** OR semantics (a row matches when it carries any selected tag) on both sides of the engine seam: server-side one parameterized `JSON_OVERLAPS(tags, ?)` predicate in `TransactionListService::applyListFilters`; client-side a TanStack `arrIncludesSome` column filter in the shared table brain, surfaced as `tagFilter`/`setTagFilter` through all three engines and the handler facade. The wire vocabulary stays the shared TanStack-shaped filter payload. Depends on the tagging plan having shipped (`Tags` cast, `tags` wire field, `TagService::getUniqueTags`).

**Tech Stack:** Laravel 13 / PHP 8.5, Pest 5, TanStack Table 9.3 (`filterFn_arrIncludesSome`), Svelte 5 + Inertia v3.

**Workflow Mode:** `direct` — no automated verify/commit; tests are written as part of tasks, never run/committed by the plan itself.

**Complexity:** Medium — rolled up from per-task ratings after Self-Review.

**Spec:** `docs/superpowers/specs/2026-10-28-transaction-tag-filter-design.md`
**Depends on:** `docs/superpowers/plans/2026-10-28-transaction-tagging.md` (shipped first)

## Global Constraints

- OR semantics across selected tags — consistent with the account/category/type filters; no AND mode.
- Tag filter values are normalized slugs: shape `^[a-z0-9_]+$`, length ≤ `TagService::MAX_TAG_LENGTH`; anything else 422s through the existing loud-failure path, never silently ignored.
- Transfer units surface **once**: tags live on the source (outflow) row (tagging spec); inflow rows are already excluded by the base query, fee rows are untagged.
- Query predicate is one parameterized `whereRaw('JSON_OVERLAPS(tags, ?)', [json_encode($tags)])` — verified working on this app's MySQL 8.4; no index (the window is already user + period bounded).
- Tests follow `.ai/rules/tests.md`: Feature `test()` + `route()` + `assertSessionHasErrors()`; service-level semantics tested by direct invocation (the deployed serving mode is `full`, so the HTTP path validates and translates filters but does not execute them — mirror `TransactionPaginationTest`); file-local helpers with globally unique names; `transaction_date` pinned inside the window.
- New frontend styling uses **shadcn design tokens** — never DaisyUI `base-*` utilities. Svelte 5 runes; block order script → markup → snippets; Solar icons only.
- No new dependencies. After DTO/request changes: `composer generate:ts` if any generated type shifts (none expected — no DTO shape changes here).

---

### Task 1: Filter vocabulary — request id, filters DTO, service predicate

**Complexity:** Medium

**Files:**
- Modify: `app/Http/Requests/TransactionIndexRequest.php` (`filters.*.id` rule)
- Modify: `app/Data/Transaction/TransactionListFilters.php` (`$tags` + `fromRequest` arm + `tagValues` guard)
- Modify: `app/Services/TransactionListService.php` (`applyListFilters`)
- Test: `tests/Feature/App/TransactionTagFilterTest.php` (new)

**Interfaces:**
- Consumes: `TagService::MAX_TAG_LENGTH` (tagging plan); `tags` column + `Tags` cast (tagging plan).
- Produces: wire filter id `'tags'` with `value: list<string>` slugs; `TransactionListFilters::$tags` (`list<string>`, default `[]`) — Task 3's server engine emits the id; the service applies it.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Data\Transaction\TransactionListFilters;
use App\Enums\DatePeriodPreset;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\TransactionListService;

function tagFilterAccountForUser(): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    return [$user, $account];
}

/** Rows of a service page keyed by id — locally owned, globally unique. */
function tagFilterPageIds(array $page): array
{
    return array_map(fn (array $row): int => $row['id'], $page['transactions']);
}

/** A plain recent-dated row inside the default list window. */
function tagFilterRowAttributes(int $account_id, int $created_by): array
{
    return [
        'account_id' => $account_id,
        'created_by' => $created_by,
        'transaction_date' => fake()->dateTimeBetween('-5 months', 'now')->format('Y-m-d H:i:s'),
    ];
}

test('filters rows by a single tag', function (): void {
    [$user, $account] = tagFilterAccountForUser();

    $tagged = Transaction::factory()->create([
        ...tagFilterRowAttributes($account->id, $user->id),
        'tags' => ['food'],
    ]);
    Transaction::factory()->create([...tagFilterRowAttributes($account->id, $user->id), 'tags' => ['bills']]);
    Transaction::factory()->create(tagFilterRowAttributes($account->id, $user->id));

    $page = TransactionListService::getTransactionsByCursorPage(
        $user,
        null,
        DatePeriodPreset::Last3Months,
        new TransactionListFilters(tags: ['food']),
    );

    expect(tagFilterPageIds($page))->toBe([$tagged->id]);
});

test('multiple selected tags match with OR semantics', function (): void {
    [$user, $account] = tagFilterAccountForUser();

    $food = Transaction::factory()->create([...tagFilterRowAttributes($account->id, $user->id), 'tags' => ['food']]);
    $bills = Transaction::factory()->create([...tagFilterRowAttributes($account->id, $user->id), 'tags' => ['bills']]);
    Transaction::factory()->create([...tagFilterRowAttributes($account->id, $user->id), 'tags' => ['utilities']]);

    $page = TransactionListService::getTransactionsByCursorPage(
        $user,
        null,
        DatePeriodPreset::Last3Months,
        new TransactionListFilters(tags: ['food', 'bills']),
    );

    expect(tagFilterPageIds($page))->toEqualCanonicalizing([$food->id, $bills->id]);
});

test('a tagged transfer unit surfaces exactly once — its outflow representative', function (): void {
    [$user, $account] = tagFilterAccountForUser();
    $destination = Account::factory()->create(['owner_id' => $user->id]);

    $transfer = Transfer::factory()->create(['created_by' => $user->id]);

    Transaction::factory()->transferOutflow($transfer)->create([
        ...tagFilterRowAttributes($account->id, $user->id),
        'tags' => ['monthly_sweep'],
    ]);
    Transaction::factory()->transferInflow($transfer)->create(tagFilterRowAttributes($destination->id, $user->id));
    Transaction::factory()->transferFee($transfer)->create(tagFilterRowAttributes($account->id, $user->id));

    $page = TransactionListService::getTransactionsByCursorPage(
        $user,
        null,
        DatePeriodPreset::Last3Months,
        new TransactionListFilters(tags: ['monthly_sweep']),
    );

    $ids = tagFilterPageIds($page);

    expect($ids)->toHaveCount(1)
        ->and(Transaction::query()->find($ids[0])->flow->value)->toBe('outflow');
});

test('rejects malformed tag filter values without rendering', function (): void {
    [$user] = tagFilterAccountForUser();

    $this->actingAs($user)->get(route('transactions.index', [
        'filters' => [['id' => 'tags', 'value' => ['Not A Slug!']]],
    ]))->assertSessionHasErrors('filters');
});

test('accepts the tags filter id with valid slugs', function (): void {
    [$user] = tagFilterAccountForUser();

    $this->actingAs($user)->get(route('transactions.index', [
        'filters' => [['id' => 'tags', 'value' => ['food']]],
    ]))->assertOk();
});
```

- [ ] **Step 2: Write minimal implementation**

`TransactionIndexRequest::rules()` — the id rule gains `'tags'`:

```php
'filters.*.id' => ['required', 'string', Rule::in(['account_id', 'category_id', 'type', 'tags'])],
```

`TransactionListFilters` — constructor gains (with `@param list<string> $tags` in the docblock):

```php
public readonly array $tags = [],
```

`fromRequest()` — initialize `$tags = [];` beside the others, add the match arm, pass `tags: $tags` to the constructor:

```php
'tags' => $tags = self::tagValues($values),
```

New private guard beside `enumValues()` (its `InvalidArgumentException` is caught by the existing catch and rethrown as the filters ValidationException — the loud-failure path):

```php
/**
 * Asserts every value is a normalized tag slug and returns the strings.
 *
 * @param  list<mixed>  $values
 * @return list<string>
 *
 * @throws InvalidArgumentException when a value is not a slug
 */
private static function tagValues(array $values): array
{
    $values = array_map(strval(...), $values);

    foreach ($values as $value) {
        if (preg_match('/^[a-z0-9_]+$/', $value) !== 1 || mb_strlen($value) > TagService::MAX_TAG_LENGTH) {
            throw new InvalidArgumentException("Value [{$value}] is not a normalized tag slug.");
        }
    }

    return array_values($values);
}
```

`TransactionListService::applyListFilters()` — after the `types` block, same empty-array-unsets discipline:

```php
if ($filters->tags !== []) {
    $list->whereRaw('JSON_OVERLAPS(tags, ?)', [json_encode($filters->tags)]);
}
```

### Task 2: Search matches tags + `tags` prop on the index

**Complexity:** Low

**Files:**
- Modify: `app/Services/TransactionListService.php` (search OR group)
- Modify: `app/Http/Controllers/TransactionController.php` (`index`, both serving branches)
- Test: `tests/Feature/App/TransactionTagFilterTest.php` (extend)

**Interfaces:**
- Consumes: `TagService::getUniqueTags(User): array` (tagging plan); the `$like` escape already built in `applyListFilters`.
- Produces: global search hits stored tags; Inertia prop `tags: string[]` on the index page — Tasks 3–4 consume it.

- [ ] **Step 1: Write the failing tests**

```php
test('global search matches stored tags', function (): void {
    [$user, $account] = tagFilterAccountForUser();

    $tagged = Transaction::factory()->create([
        ...tagFilterRowAttributes($account->id, $user->id),
        'tags' => ['starbucks_coffee'],
        'description' => null,
    ]);
    Transaction::factory()->create([...tagFilterRowAttributes($account->id, $user->id), 'description' => null]);

    $page = TransactionListService::getTransactionsByCursorPage(
        $user,
        null,
        DatePeriodPreset::Last3Months,
        new TransactionListFilters(search: 'starbucks'),
    );

    expect(tagFilterPageIds($page))->toBe([$tagged->id]);
});

test('passes the tags prop on the transaction index', function (): void {
    [$user, $account] = tagFilterAccountForUser();
    Transaction::factory()->create([...tagFilterRowAttributes($account->id, $user->id), 'tags' => ['food']]);

    $this->actingAs($user)->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/transaction/index')
            ->where('tags', ['food'])
        );
});
```

- [ ] **Step 2: Write minimal implementation** — in `applyListFilters()`'s search OR group (the closure with `description`/`amount`/account-name `orWhere`s), append:

```php
->orWhereRaw('tags like ?', [$like])
```

(A `NULL` tags column simply never matches — no coalescing needed. The stored form is JSON text `["food","dining"]`, so the term matches slugs containing it; multi-word terms won't match underscored slugs — accepted in the spec.) In `TransactionController::index()`, both the `full` and paged render branches add:

```php
'tags' => TagService::getUniqueTags($this->user),
```

### Task 3: Frontend engine layer — table brain, engines, handler facade

**Complexity:** Medium

**Files:**
- Modify: `resources/js/services/transaction-list/contract.svelte.ts`
- Modify: `resources/js/services/transaction-list/client-engine.svelte.ts`
- Modify: `resources/js/services/transaction-list/hybrid-engine.svelte.ts`
- Modify: `resources/js/services/transaction-list/server-engine.svelte.ts`
- Modify: `resources/js/services/transaction-list.svelte.ts`

**Interfaces:**
- Consumes: the wire field `tags` on list rows (tagging plan).
- Produces: `TransactionListHandler.tagFilter: string[]` / `setTagFilter(tags: string[])` — Task 4 consumes; the server engine emits filter id `'tags'` (Task 1's contract). No JS test runner — no test.

- [ ] **Step 1: Extend the table brain** (`contract.svelte.ts`):
  - Import `filterFn_arrIncludesSome` beside `filterFn_arrHas` from `'@tanstack/svelte-table'` (verified exported by the installed table-core 9.3.1).
  - `FILTER_COLUMNS` gains `tags: 'tags'`.
  - `transactionListFeatures.filterFns` gains `arrIncludesSome: filterFn_arrIncludesSome` — intersection semantics (row array has some of the selected values), deliberately distinct from `arrayHas` (row value contained in the selected list).
  - `transactionListColumns` gains:

```ts
{
    id: FILTER_COLUMNS.tags,
    accessorFn: (row) => row.tags ?? [],
    filterFn: 'arrIncludesSome',
    enableGlobalFilter: false,
},
```

  - `TableBrain` and `TransactionListEngineImpl` interfaces each gain `readonly tagFilter: string[]` and `setTagFilter(tags: string[]): void`.
  - In `createTableBrain`: `const tagFilter = $derived(getArrayFilter<string>(columnFilters, FILTER_COLUMNS.tags));`, return-block getter `get tagFilter() { return tagFilter; }` and `setTagFilter: (tags: string[]): void => setArrayFilter(FILTER_COLUMNS.tags, tags),`. `resetLocalFilters` already resets all column filters — no change.

- [ ] **Step 2: Wire the engines** — client and hybrid engines add the passthrough pair (the `accountFilter` shape):

```ts
get tagFilter() {
    return brain.tagFilter;
},
setTagFilter: brain.setTagFilter,
```

The server engine adds the same getter and a flushing setter (the `setCategoryFilter` shape):

```ts
setTagFilter(tags: string[]): void {
    brain.setTagFilter(tags);
    scheduleFlush();
},
```

… and `flush()`'s `filters` array gains the spread entry after `type`:

```ts
...(brain.tagFilter.length
    ? [{ id: FILTER_COLUMNS.tags, value: brain.tagFilter }]
    : []),
```

- [ ] **Step 3: Expose on the facade** (`transaction-list.svelte.ts`) — `TransactionListHandler` interface gains the read/write pair; the returned object gains `get tagFilter() { return handler.tagFilter; }` and `setTagFilter: handler.setTagFilter,` beside the category entries.

### Task 4: Frontend filter UI — Tags chip + sheet

**Complexity:** Medium

**Files:**
- Modify: `resources/js/components/module/transaction/transaction-list-filter.svelte`
- Modify: `resources/js/pages/app/transaction/index.svelte` (pass the prop through)

**Interfaces:**
- Consumes: `list.tagFilter` / `list.setTagFilter` (Task 3), the `tags` page prop (Task 2), the existing sheet/chip scaffolding.
- Produces: the user-facing Tags filter. View-only — no test.

- [ ] **Step 1: Add the prop and temp state** — `Props` gains `tags: string[]`; destructure it. `SheetKind` gains `'tags'`; `SHEET_TITLES` gains `tags: 'Tags'`; add `let tTags: string[] = $state([]);` — `openSheet('tags')` seeds `tTags = [...list.tagFilter]`; `applyFilter()` calls `list.setTagFilter(tTags)` beside the other setters.

- [ ] **Step 2: Add the filter chip** — `filterChips` gains a third entry (the category chip's shape):

```ts
{
    kind: 'tags',
    label:
        list.tagFilter.length === 0
            ? 'All Tags'
            : list.tagFilter.length === 1
              ? (list.tagFilter[0] ?? '1 Tag')
              : `${list.tagFilter.length} Tags`,
    active: list.tagFilter.length > 0,
},
```

- [ ] **Step 3: Add the sheet section** — `sheetSections` gains the `case 'tags':` mirroring the category case: an "All Tags" reset row (`badge.icon: 'solar--tag-bold-duotone'`, `onSelect: () => (tTags = [])`) plus one row per `tags` prop entry — `key: tag`, `label: tag`, `selected: tTags.includes(tag)`, `onSelect: () => (tTags = toggled(tTags, tag))`, `badge: { icon: 'solar--tag-bold-duotone' }`.

- [ ] **Step 4: Add active-filter chips** — `activeFilters` gains, after the type loop:

```ts
for (const tag of list.tagFilter) {
    tags.push({
        label: tag,
        icon: 'solar--tag-bold-duotone',
        onClear: () => {
            list.setTagFilter(list.tagFilter.filter((t) => t !== tag));
        },
    });
}
```

- [ ] **Step 5: Pass the prop through the page** — `index.svelte` destructures `tags: string[]` in its props type and passes `{tags}` to `<TransactionListFilter {accounts} {categories} {tags} {list} />`.

---

## Post-plan note

Both Phase 1 (tagging) and this plan touch `TransactionListService` and the transaction list surface — ship the tagging plan first; this plan's tasks then apply cleanly on top.
