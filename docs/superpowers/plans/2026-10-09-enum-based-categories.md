# Enum-Based Categories Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use better-executing-plans to implement this plan task-by-task — or better-parallel-subagents-executions if the plan splits into independent slices, or superpowers:subagent-driven-development if workflow mode is `remote`. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the `categories` table with backed-string enums (`Category`, `CategoryGroup`) carrying metadata as methods, a `CategoryData`/`CategoryGroupData` DTO layer that presents the catalog as table-like rows, and a type-only change of `transactions.category_id` (int FK → nullable string enum value).

**Architecture:** No data migration (app not live; `migrate:fresh --seed`). `CategoryService` survives as the catalog provider — internals switch from DB to enum, call sites unchanged. System bookings (`AdminFees` fees, `InitialBalance` openings) become compile-time constants; both name-lookup null-fallback paths die. SpendingService groups by the string column in SQL and folds group rollups in PHP via the enum. Frontend adaptation is deferred (accepted TS errors post-regen).

**Tech Stack:** Laravel 12 (PHP 8.4), Spatie Laravel Data + TypeScript transformer, Pest.

**Spec:** `docs/superpowers/specs/2026-10-09-enum-based-categories-design.md` — the enum inventories (56 cases, 11 groups), DTO shapes, and JSON examples there are the authoritative transcription source for Task 1/4.

**Workflow Mode:** `direct` (user is present reviewing each change; no automated verify/commit steps in any task — testing, pint, and commits are handled by the user between tasks)

**Complexity:** Medium (5 tasks: Large, Medium, Large, Large, Medium)

## Global Constraints

- PHP 8.4: explicit param types + return types on everything; curly braces on all control structures
- The transaction column **keeps the name `category_id`** — type-only change (int FK → nullable string); no renames ripple to forms/filters
- No `DB::table()` — queries go through Eloquent models (`SpendingService` included)
- SQL aggregates stay in SQL; group rollups fold in PHP via `Category::from($value)->group()` (they are already PHP-side today)
- Enum metadata methods are exhaustive `match` over `self::cases()` — a case missing an arm must throw, never fall through to a default
- `Category::InitialBalance` is the only non-bookable case; `AdminFees` remains bookable
- The enum metadata method is `label()` (NOT `name()` — avoids colliding with native `UnitEnum::$name`); DTOs expose it as `name`
- Frontend/TS adaptation is out of scope — `php artisan wayfinder:generate` + `composer generate:ts` still run (types must be current); TS errors in components are accepted pre-launch
- **`tests/Feature/ReportTest.php` is pre-existing broken** (imports a `ReportService`, `CategoryLeakReportData`, `TrendReportData`, `Household` helpers — none exist in `app/`). Out of scope: do not fix, do not delete, do not count it against any task
- After backend changes: `php artisan wayfinder:generate`, then `composer generate:ts`

## Review Focus

The five failure modes most likely to bite, each pinned by a test in its owning task:

1. **Non-exhaustive metadata match** — a case missing from any `match` arm throws `UnmatchedCaseException` at runtime → pinned by `tests/Unit/CategoryEnumTest.php` iterating every case × every metadata method (Task 1).
2. **String-column identity round-trip** — `where('category_id', Category::InitialBalance)` must match rows whose column now holds a string; a binding mistake silently un-books opening rows → pinned by `InitialBalanceSyncTest` (Task 3).
3. **Fee fallback resurrection** — the deleted "uncategorized fee" path must stay dead; fees always book `admin_fees` → pinned by `TransactionTest`'s fee tests (Task 3).
4. **Bookable validation drift** — `Rule::enum()->unless(InitialBalance)` must reject exactly the opening category and accept everything else → pinned by `InitialBalanceGuardTest` (Task 3).
5. **Group rollup leakage** — every expense item must land in exactly one group with children percentages summing to ~100 → pinned by `SpendingServiceGroupingTest` rewrite (Task 4).

---

### Task 1: The `Category` and `CategoryGroup` enums

**Complexity:** Large (mechanical volume — 56 + 11 cases with metadata)

**Files:**
- Create: `app/Enums/Category.php`
- Create: `app/Enums/CategoryGroup.php`
- Test: `tests/Unit/CategoryEnumTest.php` (create)

**Interfaces:**
- Consumes: `App\Enums\CategoryType` (exists).
- Produces (all later tasks rely on these exact signatures):
  - `Category: string` — the 56 cases exactly as listed in the spec's inventory; methods `label(): string`, `icon(): string`, `color(): string`, `group(): CategoryGroup`, `isFixedCost(): bool`; plus `public static function bookable(): array` returning all case values except `initial_balance`.
  - `CategoryGroup: string` — the 11 cases in spec order; methods `label(): string`, `icon(): string`, `color(): string`, `type(): CategoryType`, `children(): array` (list of `Category`, declaration order).

- [ ] **Step 1: Create `app/Enums/CategoryGroup.php`**

11 cases per the spec section; metadata transcribed from the seeder's group entries (`label` = group name, `icon`/`color` = group slugs). `type()`: `self::Income => CategoryType::Input`, default `CategoryType::Output`. `children()`: `array_values(array_filter(Category::cases(), fn (Category $c) => $c->group() === $this))`.

- [ ] **Step 2: Create `app/Enums/Category.php`**

56 cases exactly as in the spec inventory (order matters — it is display order). Metadata via exhaustive `match`, pattern per the spec's `label()`/`group()` examples: `label()` from trimmed seeder names, `icon()`/`color()` from child slugs, `isFixedCost()` from the `fixed` flags, `group()` one arm per group. `bookable()`:

```php
/** Case values users may book — everything except the system-only opening balance. */
public static function bookable(): array
{
    return array_values(array_map(
        fn (self $category): string => $category->value,
        array_filter(self::cases(), fn (self $category): bool => $category !== self::InitialBalance),
    ));
}
```

- [ ] **Step 3: Create `tests/Unit/CategoryEnumTest.php`**

```php
<?php

use App\Enums\Category;
use App\Enums\CategoryGroup;
use App\Enums\CategoryType;

it('declares the full preset inventory', function (): void {
    expect(count(Category::cases()))->toBe(56)
        ->and(count(CategoryGroup::cases()))->toBe(11)
        ->and(count(Category::bookable()))->toBe(55)
        ->and(Category::bookable())->not->toContain('initial_balance');
});

it('resolves metadata for every case without throwing', function (): void {
    foreach (Category::cases() as $category) {
        expect($category->label())->not->toBeEmpty()
            ->and($category->icon())->not->toBeEmpty()
            ->and($category->color())->not->toBeEmpty()
            ->and($category->group())->toBeInstanceOf(CategoryGroup::class)
            ->and($category->isFixedCost())->toBeBool();
    }
});

it('keeps group children consistent with group() back-references', function (): void {
    foreach (CategoryGroup::cases() as $group) {
        foreach ($group->children() as $category) {
            expect($category->group())->toBe($group);
        }
    }

    $grouped = array_map(fn (Category $c): string => $c->value, CategoryGroup::Income->children());
    expect($grouped)->toContain('salary', 'initial_balance')
        ->and(CategoryGroup::Income->type())->toBe(CategoryType::Input)
        ->and(CategoryGroup::Finance->type())->toBe(CategoryType::Output);
});
```

- [ ] **Step 4: Hand off for review** (Workflow Mode `direct` — user runs the test, pints, commits between tasks)

---

### Task 2: In-place migration edits + Transaction model + factory

**Complexity:** Medium

**Files:**
- Modify: `database/migrations/2026_06_16_161919_create_transactions_table.php`
- Delete: `database/migrations/2026_06_15_142539_create_categories_table.php`
- Modify: `app/Models/Transaction.php`
- Modify: `database/factories/TransactionFactory.php`

**Interfaces:**
- Consumes: `App\Enums\Category` (Task 1).
- Produces: `transactions.category_id` as nullable string holding enum values **with its index kept**; `Transaction::$category_id` enum-cast; `TransactionFactory` default books a random bookable case.

- [ ] **Step 1: Edit the transactions migration in place**

In `2026_06_16_161919_create_transactions_table.php`, replace:

```php
$table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
```

with:

```php
$table->string('category_id')->nullable();
```

The `$table->index('category_id');` line further down **stays untouched** — the column keeps its index. Nothing else in the file changes.

- [ ] **Step 2: Delete `database/migrations/2026_06_15_142539_create_categories_table.php`**

The table no longer exists; there is no other migration referencing `categories` (verified).

- [ ] **Step 3: `app/Models/Transaction.php`**

Delete the `category()` relation and the `use App\Models\Category;` import. In `casts()`:

```php
'category_id' => Category::class,
```

Add `use App\Enums\Category;`.

- [ ] **Step 4: `database/factories/TransactionFactory.php`**

Definition default becomes:

```php
'category_id' => fake()->randomElement(Category::bookable()),
```

Add `use App\Enums\Category;`. The `transferOutflow`/`transferInflow`/`transferFee` states keep `'category_id' => null`.

- [ ] **Step 5: Hand off for review**

---

### Task 3: Transaction DTOs, services, request, guard tests

The core consumer flip: `category_id` becomes the enum everywhere, the two name-lookup paths die.

**Complexity:** Large

**Files:**
- Modify: `app/Data/Transaction/TransactionData.php`, `app/Data/Transaction/TransactionFormData.php`
- Modify: `app/Data/Transaction/TransactionListData.php`, `app/Data/Transaction/TransactionDetailData.php`
- Modify: `app/Services/TransactionService.php`, `app/Services/TransferService.php`, `app/Services/AccountService.php`
- Modify: `app/Http/Requests/SaveTransactionRequest.php`
- Test: `tests/Feature/InitialBalanceSyncTest.php`, `tests/Feature/InitialBalanceGuardTest.php` (de-seed + constant lookups)
- Test: `tests/Feature/TransactionTest.php` (enum payloads; fee tests simplified)
- Delete: `AccountService`'s use of `CategoryService::initialBalanceCategoryId()`

**Interfaces:**
- Consumes: `Category::AdminFees`, `Category::InitialBalance`, `Category::bookable()` (Task 1).
- Produces: `TransactionData`/`TransactionFormData` carry `?Category $category_id`; List/Detail DTOs carry `?Category $category_id` and **no** embedded `$category` model property; fees book `Category::AdminFees`; openings match `where('category_id', Category::InitialBalance)`.

- [ ] **Step 1: Write DTOs**

`TransactionData` and `TransactionFormData`: `public ?int $category_id` → `public ?Category $category_id` (import `App\Enums\Category`; `?Category` TS-transforms to the Wayfinder union type).

`TransactionListData` / `TransactionDetailData`: same `category_id` change; **delete** the `#[TypeScriptModel(Category::class)] public ?Category $category` property, the `Category` and `TypeScriptModel` imports, `category: …` construction args, and `TransactionDetailData::fromTransaction`'s `'category.parent'` load (display metadata comes from the catalog DTOs, Task 4).

- [ ] **Step 2: Services**

`TransactionService`: delete `getCategoryTransactions()`; remove `'category'` from all `with([...])` eager loads; remove the `Category` import. `create()`/`update()` write `'category_id' => $data->category_id` (name unchanged).

`TransferService`: `createUnitTransactions` fee row books `category_id: Category::AdminFees`; delete `resolveTransferFeeCategory()` and the `Category` model import.

`AccountService::syncInitialBalance`: the row lookup becomes `->where('category_id', Category::InitialBalance)`; booking passes `category_id: Category::InitialBalance`.

- [ ] **Step 3: `SaveTransactionRequest`**

```php
'category_id' => ['required', Rule::enum(Category::class)->unless(Category::InitialBalance)],
```

Remove the `CategoryService` import; add `App\Enums\Category`.

- [ ] **Step 4: Guard + sync tests**

`InitialBalanceSyncTest` / `InitialBalanceGuardTest`: delete the `beforeEach(fn () => $this->seed(CategorySeeder::class));` lines and the `Database\Seeders\CategorySeeder` / `App\Services\CategoryService` imports; replace `CategoryService::initialBalanceCategoryId()` with `Category::InitialBalance` (import `App\Enums\Category`). `InitialBalanceGuardTest`'s "other category" lookup becomes a fixed bookable case:

```php
$otherCategoryId = 'salary';
```

`TransactionTest`: POST payloads use enum values directly (`'category_id' => 'groceries'`); the "books a transfer fee row in the Admin Fees category" test drops both `Category::factory()` lines and asserts `$feeRow->category_id === Category::AdminFees`; the follow-up test "books a transfer fee as uncategorized when no Admin Fees category exists" is **deleted** — the fallback it pins no longer exists (spec).

- [ ] **Step 5: Hand off for review**

---

### Task 4: Category DTOs, catalog service, SpendingService, report DTOs

**Complexity:** Large

**Files:**
- Create: `app/Data/Category/CategoryData.php`, `app/Data/Category/CategoryGroupData.php`
- Modify: `app/Services/CategoryService.php` (rewrite internals)
- Modify: `app/Services/SpendingService.php` (rewrite)
- Modify: `app/Data/Report/CategorySpendingItemData.php`, `app/Data/Report/ChildSpendingItemData.php`, `app/Data/Report/ParentSpendingItemData.php`
- Test: `tests/Feature/SpendingServiceTest.php`, `tests/Unit/SpendingServiceGroupingTest.php` (rewrite)
- Test: `tests/Feature/DecorationsTest.php` (remove the two category tests)

**Interfaces:**
- Consumes: `Category`, `CategoryGroup`, `CategoryType` (Task 1).
- Produces: `CategoryService::getCategories(): Collection<CategoryData>`, `::getGroupedCategories(): Collection` (group arrays with `options`), `::getBookableCategories(): Collection<CategoryData>`; `SpendingService::globalCategorySpending(array, DatePeriodPreset): CategorySpendingReportData` (signature unchanged); report DTOs keyed by string ids (shapes below).

- [ ] **Step 1: DTOs**

Exactly as the spec's "Category DTOs" section: `CategoryData` (`$id`, `$name`, `$icon`, `$color`, `$type`, `$is_fixed_cost`, `$group: CategoryGroupData`) and `CategoryGroupData` (`$id`, `$name`, `$icon`, `$color`, `$type`), each with a `fromEnum` constructor mapping from the enum's metadata methods. `#[TypeScript]` on both.

- [ ] **Step 2: `CategoryService` internals**

```php
public static function getCategories(): Collection
{
    return collect(Category::bookable())
        ->map(fn (string $value): CategoryData => CategoryData::fromEnum(Category::from($value)))
        ->values();
}

public static function getGroupedCategories(): Collection
{
    return collect(CategoryGroup::cases())
        ->map(fn (CategoryGroup $group): array => [
            'id' => $group->value,
            'name' => $group->label(),
            'icon' => $group->icon(),
            'color' => $group->color(),
            'type' => $group->type()->value,
            'options' => collect($group->children())
                ->map(fn (Category $category): CategoryData => CategoryData::fromEnum($category))
                ->all(),
        ])
        ->values();
}
```

`getBookableCategories()` = `getCategories()` (the flat bookable list — identical by construction now); keep the method for its call sites. Delete `initialBalanceCategoryId()`. `create`/`update`/`softDelete`/`normalizeDecorations` methods are deleted (no catalog mutations).

- [ ] **Step 3: Report DTOs**

`CategorySpendingItemData`: `?int $categoryId`, `?int $parentId`, `?string $parentName` → `string $category_id`, `string $group` (enum values); `name`/`color`/`icon` stay. `ChildSpendingItemData`: `int $categoryId` → `string $category_id`. `ParentSpendingItemData`: `int $categoryId` → `string $group_id` (the enum value), rest unchanged.

- [ ] **Step 4: `SpendingService` rewrite**

`periodTotal`: same aggregate through `Transaction::query()` (no `DB::table`). Rows:

```php
$rows = Transaction::query()
    ->whereIn('account_id', $accountIds)
    ->where('type', TransactionType::Expense->value)
    ->whereBetween('transaction_date', [$from, $to])
    ->selectRaw('category_id, SUM(amount) AS total, ROUND(SUM(amount) / ? * 100, 2) AS percentage', [$periodTotal])
    ->groupBy('category_id')
    ->orderByDesc('total')
    ->get();
```

Items map each row through `Category::from($r->category_id)` for `name`/`color`/`icon` and `->group()->value` for `group`. `groupByParent(array $items, float $periodTotal)` becomes `groupByCategoryGroup(...)`: bucket items by `$item->group`, synthesize one `ParentSpendingItemData` per group (name/color/icon from `CategoryGroup::from($group)`, `group_id` = the value, children via `buildChildren`). The parent-with-direct-spend and parentName cases die — groups are not bookable. Both joins, the JSON_EXTRACT decorations extraction, and the `DB` import go.

- [ ] **Step 5: Rewrite the spending tests**

`tests/Feature/SpendingServiceTest.php` — enum values instead of `Category::factory()`; e.g.:

```php
it('nests category spending under its group', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);
    Transaction::factory()->expense()->forCategory('groceries')->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 200_000,
    ]);

    $report = (new SpendingService)->globalCategorySpending([$account->id], DatePeriodPreset::ThisMonth);

    expect($report->categories)->toHaveCount(1)
        ->and($report->categories[0]->group_id)->toBe('shopping')
        ->and($report->categories[0]->name)->toBe('Shopping')
        ->and($report->categories[0]->children[0]->category_id)->toBe('groceries')
        ->and($report->categories[0]->children[0]->name)->toBe('Groceries');
});
```

Keep the file's existing helper structure and assert the same financial math (totals/percentages) with enum categories replacing factory rows; the "parent with direct spending" test is deleted (groups are not bookable). `TransactionFactory` gains `forCategory(string|Category $category): static` accepting the enum value (`['category_id' => $category instanceof Category ? $category : Category::from($category)]`).

`tests/Unit/SpendingServiceGroupingTest.php` — rewrite around `groupByCategoryGroup`: hand-build `CategorySpendingItemData` fixtures with `category_id`/`group` string values (e.g. `dining_out` + `food_and_drinks`, `groceries` + `shopping`), assert one `ParentSpendingItemData` per group, `group_id` values, children nesting, percentage recalculation relative to the group subtotal, and descending-total sort. The top-level-vs-children split fixtures die (every item is a child of its group now).

`tests/Feature/DecorationsTest.php`: delete the two `$category = Category::factory()` tests; leave the rest of the file untouched.

- [ ] **Step 6: Hand off for review**

---

### Task 5: Cleanup — deletions, seeder, test de-seeding

**Complexity:** Medium

**Files:**
- Delete: `app/Models/Category.php`, `database/factories/CategoryFactory.php`, `database/seeders/CategorySeeder.php`, `app/Http/Controllers/CategoryController.php`
- Modify: `routes/web.php` (remove `categories.index`), `database/seeders/DatabaseSeeder.php` (remove `CategorySeeder::class` call)
- Modify: `database/seeders/DummyDataSeeder.php` (enum pick lists)
- Test: `tests/Feature/AccountServiceTest.php`, `tests/Feature/AccountTest.php`, `tests/Feature/BalanceServiceTest.php` (remove seeder `beforeEach` + imports)
- Note: `tests/Feature/ReportTest.php` — untouched (pre-existing broken, see Global Constraints)

**Interfaces:**
- Consumes: everything prior.
- Produces: no `Category` model references anywhere in `app/` or live tests; `DatabaseSeeder` = `ProviderSeeder` + `DummyDataSeeder`.

- [ ] **Step 1: Deletions**

Delete the four files. Remove from `routes/web.php` the `CategoryController` import and the `categories.index` route. `DatabaseSeeder`: remove the `CategorySeeder::class` entry.

- [ ] **Step 2: `DummyDataSeeder` pick lists**

Replace the `Category` model queries at the top of `run()`:

```php
$bookable = Category::bookable();
$incomeCategories = collect(CategoryGroup::Income->children())
    ->reject(fn (Category $category): bool => $category === Category::InitialBalance)
    ->values();
$expenseCategories = collect(Category::cases())
    ->reject(fn (Category $category): bool => $category->group()->type() === CategoryType::Input)
    ->values();
```

`buildPickList`/`coveredIncomeIds` logic keeps working — coverage collections key on `$category->id` (the string value) instead of model ids; the `type` checks become `$category->group()->type()`. The `Category`/`CategoryType` imports adjust accordingly (the latter is still used).

- [ ] **Step 3: Remove the five seeder `beforeEach` lines**

In `AccountServiceTest`, `AccountTest`, `BalanceServiceTest`, `InitialBalanceSyncTest`, `InitialBalanceGuardTest`: delete `beforeEach(fn () => $this->seed(CategorySeeder::class));` and the `use Database\Seeders\CategorySeeder;` import (booking no longer depends on seeded rows).

- [ ] **Step 4: Generation + hand off**

User runs `php artisan wayfinder:generate` + `composer generate:ts` (enum unions, DTO shapes, `App.Models.Category` removal), then the suite. TS errors in components are the accepted, deferred frontend follow-up.

---

## Self-Review Notes

- Spec coverage: enums (T1), migration + model + factory (T2), transaction consumers + guards (T3), catalog DTOs + spending (T4), deletions + seeder + de-seeding (T5). Shared props need no task — `CategoryService` keeps its call-site API (spec's Shared props section, backend bullet).
- Sequencing: model deletion lands LAST (T5) — everything referencing `Category::factory()`/the model must stop first; T2's migration drops the table while the model class still exists (nothing instantiates it after T3/T4 rewrites).
- `TransactionObserverTest` and `TransferServiceTest`/`TransferReportSemanticsTest` need no changes: they never reference categories beyond factory defaults (verified by grep).
- The spec's `CategoryGroup::children()` requires `Category` to import `CategoryGroup` and vice versa — circular type references between two enums are fine in PHP (resolved lazily at call time).
