# Initial Balance as Opening Income Transaction Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use better-executing-plans to implement this plan task-by-task — or better-parallel-subagents-executions if the plan splits into independent slices, or superpowers:subagent-driven-development if workflow mode is `remote`. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the `accounts.initial_balance` special-casing (observer magic + balance-math term) with a real Income transaction row booked through `TransactionService`, identified by a dedicated fixed `Initial Balance` category. The column stays as a display copy.

**Architecture:** No migrations, no schema changes, no new enum cases. `CategoryService` owns a find-only category lookup (presence guaranteed by the always-run seeder); `AccountService::syncInitialBalance()` decides create/update/soft-delete and delegates all row mechanics to `TransactionService` (the `TransferService` pattern). `AccountObserver` is deleted; `BalanceService` truth becomes `Σ inflows − Σ outflows`. Transaction routes guard opening rows against mint/edit/delete, mirroring the transfer-member guards.

**Tech Stack:** Laravel 12 (PHP 8.4), Spatie Laravel Data, Inertia v3 + Svelte 5 (no frontend changes), Pest.

**Spec:** `docs/superpowers/specs/2026-10-09-initial-balance-opening-transaction-design.md`

**Workflow Mode:** `direct` (user is present reviewing each change; no automated verify/commit steps in any task — testing, pint, and commits are handled by the user between tasks)

**Complexity:** Medium (5 tasks: Small, Large, Small, Medium, Small)

## Global Constraints

- The `Initial Balance` category is **always present** — guaranteed by `CategorySeeder`, which is always run. Lookups are find-only queries; there is no `firstOrCreate`/self-healing. Test databases seed the seeder in a `beforeEach` wherever accounts with opening balances are created.
- PHP 8.4: explicit param types + return types on everything; curly braces on all control structures
- No DB migrations — zero schema changes in this plan (accepted: existing dev DBs are not backfilled; refresh with `migrate:fresh --seed`)
- All row persistence goes through `TransactionService::create/update/softDelete` — never `Transaction::create()` directly for opening rows
- SQL aggregates stay in SQL — never PHP reductions — but queries go through Eloquent models, never `DB::table()` (`BalanceService` queries `Transaction` directly; the model brings the soft-delete scope for free)
- Money conventions unchanged: `initial_balance` int-cast on the model, `float` in `TransactionData`, decimal(15,2) columns
- Controllers stay thin: guards are one-line `abort_if`/redirect checks; resolution logic lives in `CategoryService`, booking in `AccountService`
- Negative initial balances stay rejected (validation `min:0` already exists — do not add negative handling)
- No wayfinder regen, no `composer generate:ts` (no controller signatures, DTOs, or enums change)
- No frontend changes (form, schema, and detail pages read the column, which stays)
- Events (`TransactionSaved`/`TransactionDeleted`) currently have no listeners — delegation keeps one booking path; do not "optimize" the delegation away

## Review Focus

Five failure modes the spec implies that a person using this software would most plausibly hit, each already pinned by a test in its owning task:

1. **A test file creates accounts with opening balances but forgets the seeder `beforeEach`** — the hook then books the row uncategorized, silently losing identity (row not found on later edits) → mitigated by the `beforeEach` seeding in every affected file (Tasks 2–4); the invariant is documented in Global Constraints.
2. **Opening row manually soft-deleted, then initial balance edited** — row must be re-created exactly once and the balance restored, not double-counted → pinned by "re-creates the row when it was manually deleted…" (Task 2 Step 5).
3. **zero → X → zero → X edit cycle** — soft-deleted rows must never resurrect; exactly one live row at all times → pinned by "keeps exactly one live row through a zero-X-zero-X cycle" (Task 2 Step 5).
4. **Multi-account owner edits one account's initial balance** — the other account's opening row and balance must stay untouched → pinned by "scopes the opening row to its own account…" (Task 2 Step 5).
5. **Display-copy drift** — any code path writing `accounts.initial_balance` outside `AccountService::update` would desync the column from the opening row → pinned by "ignores accounts.initial_balance when no opening row exists" (Task 3 Step 2, which simulates a rogue writer via `updateQuietly`).

---

### Task 1: Initial Balance category — seeder entry + lookup

**Complexity:** Small

**Files:**
- Modify: `database/seeders/CategorySeeder.php:16-24` (Income children array)
- Modify: `app/Services/CategoryService.php`

**Interfaces:**
- Consumes: nothing new.
- Produces (all later tasks rely on this exact signature):
  - `CategoryService::initialBalanceCategoryId(): ?int` — find-only lookup (name `Initial Balance`, child-level). The category's presence is guaranteed by `CategorySeeder`, which is always run; the method never creates.
- Verification: no dedicated test — the signature is consumed (and exercised) by Task 2's suite, which seeds the seeder and books opening rows end-to-end.

- [ ] **Step 1: Add the child category to `CategorySeeder`'s Income group**

In the `Income` group's `children` array (after `Other Income`), append:

```php
['name' => 'Initial Balance', 'icon_slug' => 'course-up', 'color_slug' => 'green-200', 'fixed' => true],
```

- [ ] **Step 2: Add the lookup to `app/Services/CategoryService.php`**

One static method on `CategoryService`:

```php
/**
 * The dedicated bookkeeping category for account opening balances.
 * Find-only — presence is guaranteed by CategorySeeder; never creates.
 */
public static function initialBalanceCategoryId(): ?int
{
    return Category::query()
        ->whereNotNull('parent_id')
        ->where('name', 'Initial Balance')
        ->value('id');
}
```

No new imports needed (`Category` and `Collection` are already imported).

- [ ] **Step 3: Hand off for review** (Workflow Mode `direct` — user runs the test, pints, and commits between tasks)

---

### Task 2: Behavior transfer — booking via AccountService, observer deleted

The heart of the plan. The observer's implicit behavior (copy initial_balance into current_balance on create; apply delta on update) transfers to an explicit service path plus a factory hook, atomically — partial transfer would double-count.

**Complexity:** Large

**Files:**
- Modify: `app/Services/AccountService.php`
- Modify: `database/factories/AccountFactory.php`
- Modify: `database/factories/TransactionFactory.php`
- Delete: `app/Observers/AccountObserver.php`
- Modify: `app/Models/Account.php:8,19` (drop `use App\Observers\AccountObserver;` and the `#[ObservedBy([AccountObserver::class])]` attribute)
- Test: `tests/Feature/InitialBalanceSyncTest.php` (create)
- Test: `tests/Feature/AccountObserverTest.php` (delete — spec-approved rewrite)
- Test: `tests/Feature/AccountServiceTest.php:11-60` (rewrite the two summarize tests — negative `initial_balance` no longer produces a balance; add seeder `beforeEach`)
- Test: `tests/Feature/AccountTest.php` (add seeder `beforeEach` — its store test books an opening row via the controller)

**Interfaces:**
- Consumes: `CategoryService::initialBalanceCategoryId(): ?int` (Task 1); `TransactionService::create(User, TransactionData): Transaction`, `::update(Transaction, TransactionData): Transaction`, `::softDelete(Transaction): void`.
- Produces: `AccountService::syncInitialBalance(Account $account): void` — public; called by `AccountService::create/update` and the factory hook. Ensures exactly one live opening row matching `$account->initial_balance` (creates when > 0 and missing, updates amount in place, soft-deletes when ≤ 0).

- [ ] **Step 1: Add an `initialBalance()` state to `database/factories/TransactionFactory.php`**

```php
/** Account opening balance row — an income row in the Initial Balance category. */
public function initialBalance(): static
{
    return $this->state([
        'type' => TransactionType::Income,
        'flow' => TransactionFlow::Inflow,
        'category_id' => \App\Services\CategoryService::initialBalanceCategoryId(),
        'description' => 'Initial balance',
    ]);
}
```

(Import `App\Services\CategoryService` at the top instead of the FQN if preferred — match the file's existing import style.)

- [ ] **Step 2: Add the `afterCreating` hook to `database/factories/AccountFactory.php`**

```php
public function configure(): static
{
    return $this->afterCreating(function (Account $account): void {
        if ((float) $account->initial_balance > 0) {
            app(\App\Services\AccountService::class)->syncInitialBalance($account);
        }
    });
}
```

This replaces what the observer did implicitly: positive `initial_balance` now yields `current_balance == initial_balance` through the booked row's own `TransactionObserver`. Zero/negative initial balances book nothing (negatives are validation-rejected anyway).

- [ ] **Step 3: Wire `syncInitialBalance` into `app/Services/AccountService.php`**

Constructor (the class currently has none) + public sync + call sites:

```php
public function __construct(private readonly TransactionService $transactionService) {}
```

```php
/**
 * Ensure exactly one live opening row matches the account's display
 * initial_balance. All row mechanics delegate to TransactionService —
 * this method only decides create / update-in-place / soft-delete.
 */
public function syncInitialBalance(Account $account): void
{
    $amount = (float) $account->initial_balance;
    $categoryId = CategoryService::initialBalanceCategoryId();

    $row = Transaction::query()
        ->where('account_id', $account->id)
        ->where('category_id', $categoryId)
        ->first();

    if ($amount <= 0) {
        if ($row !== null) {
            $this->transactionService->softDelete($row);
        }

        return;
    }

    $data = new TransactionData(
        account_id: $account->id,
        type: TransactionType::Income,
        amount: $amount,
        transaction_date: ($row?->transaction_date ?? $account->created_at)->toDateString(),
        category_id: $categoryId,
        description: 'Initial balance',
    );

    if ($row === null) {
        $this->transactionService->create($account->owner, $data);

        return;
    }

    $this->transactionService->update($row, $data);
}
```

Call sites — `create()`:

```php
public function create(User $user, array $data): Account
{
    return DB::transaction(function () use ($user, $data): Account {
        $account = Account::create([...$this->normalizeDecorations($data), 'owner_id' => $user->id]);

        if ((float) $account->initial_balance > 0) {
            $this->syncInitialBalance($account);
        }

        return $account;
    });
}
```

`update()`:

```php
public function update(Account $account, array $data): Account
{
    return DB::transaction(function () use ($account, $data): Account {
        $account->update($this->normalizeDecorations($data));

        if ($account->wasChanged('initial_balance')) {
            $this->syncInitialBalance($account);
        }

        return $account->fresh();
    });
}
```

Imports to add: `App\Data\Transaction\TransactionData`, `App\Enums\TransactionType`, `App\Models\Transaction`, `Illuminate\Support\Facades\DB`. ($account->owner lazy-loads; every account has an owner.)

- [ ] **Step 4: Delete the observer**

Delete `app/Observers/AccountObserver.php`. In `app/Models/Account.php` remove the `use App\Observers\AccountObserver;` import and the `#[ObservedBy([AccountObserver::class])]` attribute (and the now-unused `use Illuminate\Database\Eloquent\Attributes\ObservedBy;` import).

- [ ] **Step 5: Replace `tests/Feature/AccountObserverTest.php` with `tests/Feature/InitialBalanceSyncTest.php`**

Delete `AccountObserverTest.php` (spec-approved). New file pins the five behaviors that matter (create books; edit updates in place; to-zero deletes; cycle leaves one live row; multi-account scoping):

```php
<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountService;
use App\Services\CategoryService;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(CategorySeeder::class));

function openingRow(Account $account): ?Transaction
{
    return Transaction::query()
        ->where('account_id', $account->id)
        ->where('category_id', CategoryService::initialBalanceCategoryId())
        ->first();
}

it('books an opening income row and sets current_balance on create', function (): void {
    $account = Account::factory()->create(['initial_balance' => 1_000_000]);

    $row = openingRow($account);

    expect($row)->not->toBeNull()
        ->and($row->type->value)->toBe('income')
        ->and($row->flow->value)->toBe('inflow')
        ->and((float) $row->amount)->toBe(1_000_000.0)
        ->and($row->description)->toBe('Initial balance')
        ->and($row->transaction_date->toDateString())->toBe($account->created_at->toDateString())
        ->and((float) $account->fresh()->current_balance)->toBe(1_000_000.0);
});

it('books nothing for a zero initial balance', function (): void {
    $account = Account::factory()->create(['initial_balance' => 0]);

    expect(openingRow($account))->toBeNull()
        ->and((float) $account->fresh()->current_balance)->toBe(0.0);
});

it('updates the opening row in place when the initial balance is edited', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id, 'initial_balance' => 500_000]);
    $rowId = openingRow($account)->id;

    app(AccountService::class)->update($account, ['initial_balance' => 1_200_000]);

    $row = openingRow($account);

    expect($row->id)->toBe($rowId)
        ->and((float) $row->amount)->toBe(1_200_000.0)
        ->and((float) $account->fresh()->current_balance)->toBe(1_200_000.0)
        ->and($row->transaction_date->toDateString())->toBe($account->created_at->toDateString());
});

it('soft-deletes the opening row when the initial balance is set to zero', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id, 'initial_balance' => 500_000]);

    app(AccountService::class)->update($account, ['initial_balance' => 0]);

    expect(openingRow($account))->toBeNull()
        ->and((float) $account->fresh()->current_balance)->toBe(0.0);
});

it('keeps exactly one live row through a zero-X-zero-X cycle', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id, 'initial_balance' => 0]);
    $service = app(AccountService::class);

    $service->update($account, ['initial_balance' => 300_000]);
    $service->update($account, ['initial_balance' => 0]);
    $service->update($account, ['initial_balance' => 450_000]);

    expect(Transaction::withTrashed()
        ->where('account_id', $account->id)
        ->where('category_id', CategoryService::initialBalanceCategoryId())
        ->count())->toBe(2)
        ->and(openingRow($account))->not->toBeNull()
        ->and((float) $account->fresh()->current_balance)->toBe(450_000.0);
});

it('re-creates the row when it was manually deleted and the balance is edited', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id, 'initial_balance' => 500_000]);
    openingRow($account)->delete();

    app(AccountService::class)->update($account, ['initial_balance' => 800_000]);

    expect(openingRow($account))->not->toBeNull()
        ->and((float) $account->fresh()->current_balance)->toBe(800_000.0);
});

it('scopes the opening row to its own account in a multi-account setup', function (): void {
    $user = User::factory()->create();
    $accountA = Account::factory()->create(['owner_id' => $user->id, 'initial_balance' => 100_000]);
    $accountB = Account::factory()->create(['owner_id' => $user->id, 'initial_balance' => 0]);

    app(AccountService::class)->update($accountB, ['initial_balance' => 700_000]);

    expect((float) openingRow($accountA)->amount)->toBe(100_000.0)
        ->and((float) $accountA->fresh()->current_balance)->toBe(100_000.0)
        ->and((float) openingRow($accountB)->amount)->toBe(700_000.0);
});
```

(Note the `zero-X-zero-X` test expects 2 rows total including trashed — one soft-deleted + one live.)

- [ ] **Step 6: Update `tests/Feature/AccountServiceTest.php` and `tests/Feature/AccountTest.php`**

Both files get the seeder wired in — after `uses(RefreshDatabase::class);` add:

```php
beforeEach(fn () => $this->seed(CategorySeeder::class));
```

with `use Database\Seeders\CategorySeeder;` in the imports. (AccountTest's store test posts `initial_balance` through the controller, so the hook books an opening row and needs the category.)

Then rewrite the two summarize tests in `AccountServiceTest.php`: `summarize` reads the denormalized `current_balance` column, and negative `initial_balance` no longer produces a balance (validation rejects negatives; the hook skips them). Model credit-card debt the way it really occurs — via expense rows:

Replace the first test's accounts collection with:

```php
$cards = Account::factory()->creditCard()->create(['owner_id' => $user->id]);
Transaction::factory()->expense()->create([
    'account_id' => $cards->id,
    'created_by' => $user->id,
    'amount' => 750_000,
]);
```

and the second test keeps the same shape (card debt via one 750_000 expense instead of `initial_balance => -750_000`). Add `use App\Models\Transaction;` to the file's imports (the expense rows are new here). First test then expects `total_balance` = 1_000_000 + 200_000 + 300_000 + 4_850_000 − 750_000 = **5_600_000.0**; keep the other assertions (`available_balance` 1_500_000.0, `investment_balance` 4_850_000.0). Second test expects `total_balance` **−750_000.0** with `available_balance`/`investment_balance` 0.0.

- [ ] **Step 7: Hand off for review** (user runs the suite, pints, commits between tasks)

---

### Task 3: BalanceService truth formula drops the column

**Complexity:** Small

**Files:**
- Modify: `app/Services/BalanceService.php`
- Test: `tests/Feature/BalanceServiceTest.php:22-27` (rename first test), `:88-101` (append one test)

**Interfaces:**
- Consumes: nothing.
- Produces: `BalanceService::forAccount(Account): string` — truth is now purely transaction-based; the column is display-only.

- [ ] **Step 1: Rewrite `app/Services/BalanceService.php`**

```php
<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;

class BalanceService
{
    public function forAccount(Account $account): string
    {
        $balance = Transaction::query()
            ->where('account_id', $account->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN flow = 'inflow' THEN amount ELSE -amount END), 0) AS balance")
            ->value('balance');

        return (string) ($balance ?? 0);
    }
}
```

(The old `DB::table('accounts')` join existed only to pull `initial_balance` into the aggregate — with the column out of the formula, both the join and the `groupBy` go, along with the `DB` import. The `Transaction` model brings the soft-delete scope for free, replacing the join's `whereNull('t.deleted_at')`.)

- [ ] **Step 2: Update `tests/Feature/BalanceServiceTest.php`**

Add the seeder after `uses(RefreshDatabase::class);` — `beforeEach(fn () => $this->seed(CategorySeeder::class));` with the `Database\Seeders\CategorySeeder` import (the helper's accounts carry positive `initial_balance`, so the factory hook books rows).

Rename the first test to `it('returns the opening row amount when there are no other transactions')` — same assertion (`1_000_000.0`; the factory hook from Task 2 books the row). Append one regression test pinning the column as display-only:

```php
it('ignores accounts.initial_balance when no opening row exists', function (): void {
    [$user, $account] = setupBalanceAccount(0);
    $account->updateQuietly(['initial_balance' => 1_000_000]);

    $service = new BalanceService;

    expect((float) $service->forAccount($account))->toBe(0.0);
});
```

(`updateQuietly` writes the column without going through `AccountService::update`, so no row books — exactly the display-copy drift scenario.)

- [ ] **Step 3: Hand off for review**

---

### Task 4: Route guards — opening rows cannot be minted, re-categorized, or deleted

**Complexity:** Medium

**Files:**
- Modify: `app/Http/Requests/SaveTransactionRequest.php:28`
- Modify: `app/Services/CategoryService.php` (add `getBookableCategories()`)
- Modify: `app/Http/Controllers/TransactionController.php:62,71-73,79,93-102,104-115`
- Test: `tests/Feature/InitialBalanceGuardTest.php` (create)

**Interfaces:**
- Consumes: `CategoryService::initialBalanceCategoryId(): ?int` (Task 1).
- Produces: `CategoryService::getBookableCategories(): Collection` — levelChildren excluding Initial Balance; feeds the transaction form on `create`/`edit`.

- [ ] **Step 1: Guard `SaveTransactionRequest`**

```php
'category_id' => [
    'required',
    'integer',
    'exists:categories,id',
    Rule::notIn(array_filter([CategoryService::initialBalanceCategoryId()])),
],
```

(`array_filter` drops null so the rule degrades to plain validation when the category is absent. Import `App\Services\CategoryService` — static call matches the codebase's service usage style.)

- [ ] **Step 2: Add `getBookableCategories()` to `CategoryService`**

```php
/** Child categories selectable in the transaction form — excludes the Initial Balance bookkeeping category. */
public static function getBookableCategories(): Collection
{
    return self::getCategories()
        ->reject(fn (Category $category): bool => $category->id === self::initialBalanceCategoryId())
        ->values();
}
```

- [ ] **Step 3: Wire the controller**

In `TransactionController`:

- `create()` (line 62): `'categories' => CategoryService::getBookableCategories(),`
- `edit()`: first the existing transfer redirect, then:

```php
if ($transaction->category_id === CategoryService::initialBalanceCategoryId()) {
    return to_route('accounts.edit', $transaction->account_id);
}
```

and line 79: `'categories' => CategoryService::getBookableCategories(),`

- `update()` — after the existing transfer-member guard:

```php
abort_if(
    $transaction->category_id === CategoryService::initialBalanceCategoryId(),
    422,
    'Initial balance must be changed via the account.'
);
```

- `destroy()` — same `abort_if` before the soft-delete branch.

- [ ] **Step 4: Create `tests/Feature/InitialBalanceGuardTest.php`**

```php
<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CategoryService;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(CategorySeeder::class));

function guardUser(): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id, 'initial_balance' => 500_000]);

    return [$user, $account];
}

function openingRowOf(Account $account): Transaction
{
    return Transaction::query()
        ->where('account_id', $account->id)
        ->where('category_id', CategoryService::initialBalanceCategoryId())
        ->first();
}

it('rejects booking an income row in the Initial Balance category', function (): void {
    [$user, $account] = guardUser();

    $this->actingAs($user)->post(route('transactions.store'), [
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 100_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => CategoryService::initialBalanceCategoryId(),
    ])->assertSessionHasErrors('category_id');
});

it('redirects the edit page of an opening row to the account edit page', function (): void {
    [$user, $account] = guardUser();

    $this->actingAs($user)->get(route('transactions.edit', openingRowOf($account)))
        ->assertRedirect(route('accounts.edit', $account));
});

it('rejects updating an opening row via the transactions endpoint', function (): void {
    [$user, $account] = guardUser();
    $row = openingRowOf($account);
    $otherCategoryId = Category::query()
        ->whereNotNull('parent_id')
        ->where('id', '!=', CategoryService::initialBalanceCategoryId())
        ->first()->id;

    $this->actingAs($user)->put(route('transactions.update', $row), [
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 100_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => $otherCategoryId,
    ])->assertStatus(422);
});
```

Plus a fourth test: `it('rejects deleting an opening row via the transactions endpoint')` — `actingAs` DELETE `route('transactions.destroy', openingRowOf($account))` then `assertStatus(422)` (the abort_if fires after the owner policy admits the request), and assert the opening row still exists and `current_balance` is unchanged.

- [ ] **Step 5: Hand off for review**

---

### Task 5: Seeder opening balances + README

**Complexity:** Small

**Files:**
- Modify: `database/seeders/DummyDataSeeder.php` (`seedUser` call site, `seedAccounts` signature/body)
- Modify: `README.md:24`

**Interfaces:**
- Consumes: `AccountFactory` `afterCreating` hook (Task 2 — booking is automatic once the state sets `initial_balance` and `created_at`).

- [ ] **Step 1: Backdate and fund seeded accounts**

In `seedUser`, pass the activity window start into `seedAccounts`:

```php
$accountIds = $this->seedAccounts(
    $user,
    Provider::query()->get(),
    $accountsPerUserMin,
    $accountsPerUserMax,
    $date = Date::now()->startOfMonth()->subMonths($months - 1),
);
```

Compute the window date before the loop (it is currently derived inside it) and reuse it for both. New signature and body:

```php
/**
 * @param  Collection<int, Provider>  $providers
 * @param  CarbonInterface  $openedAt  Start of the seeded activity window — accounts "open" shortly before it.
 *
 * @return list<int>
 */
private function seedAccounts(User $user, Collection $providers, int $min, int $max, CarbonInterface $openedAt): array
{
    $accounts = collect();

    foreach (range(1, random_int(min($min, $max), max($min, $max))) as $ignored) {
        $factory = Account::factory()->state([
            'type' => collect(AccountType::cases())->random(),
            'owner_id' => $user->id,
            'initial_balance' => fake()->boolean(80) ? random_int(1, 50) * 100_000 : 0,
            'created_at' => $openedAt->copy()->subDays(random_int(1, 14)),
        ]);

        // … unchanged provider/name branches …
    }

    return $accounts->pluck('id')->toArray();
}
```

The factory hook books the opening row (dated at the backdated `created_at`, so it lands before the first activity month). Note in the class docblock: rerunning the seeder still duplicates data (unchanged).

- [ ] **Step 2: Update `README.md:24`**

Replace the dual-tracking bullet's truth description with:

> - **Balance is dual-tracked**: `current_balance` is denormalized and observer-maintained by every transaction create/update/delete/restore (`±amount` by flow). On-demand truth is recomputed by `BalanceService` as `Σ inflows − Σ outflows` — the account's opening balance is itself an Income transaction row (Initial Balance category), making it visible in history. `accounts.initial_balance` is a display copy kept in lockstep by `AccountService`.

- [ ] **Step 3: Hand off for review**

---

## Self-Review Notes

- Spec coverage: category + resolver (T1), booking/service/observer/factories/tests (T2), truth formula (T3), guards (T4), seeder + README (T5). Spec's "no wayfinder regen" and "no frontend changes" are honored by omission.
- Sequencing constraint: Task 2 is atomic by design — hook and observer transfer must land together or balances double-count.
- Guard assertions use `assertStatus(422)` for the controller `abort_if` checks; `transactions.store` rejection uses `assertSessionHasErrors('category_id')`.
