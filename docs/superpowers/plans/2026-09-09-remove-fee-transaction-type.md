# Remove `TransactionType::Fee` Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use better-executing-plans to implement this plan task-by-task — or better-parallel-subagents-executions if the plan splits into independent slices, or superpowers:subagent-driven-development if workflow mode is `remote`. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Delete the `TransactionType::Fee` concept — transfer fees book as regular `expense` rows (Admin Fees category) — and give the frontend a 3-kind view (`income | expense | transfer`) fed by a `TransactionListData` DTO instead of raw rows.

**Architecture:** One-pass change, no DB migration (zero `fee` rows exist; `type` is a string column). Backend: enum loses the case, services stop referencing `'fee'`, a new Spatie DTO carries transfer-pair `destination_account_id` to `index`/`edit`. Frontend: `resolveKind()` in `transaction.schema.ts` becomes the only place reasoning about raw type values; all badges, filters, summaries, and form modes switch to the 3-kind contract.

**Tech Stack:** Laravel 12 (PHP 8.4), Spatie Laravel Data + TypeScript transformer, Inertia v3 + Svelte 5 (runes), Wayfinder, Pest.

**Spec:** `docs/superpowers/specs/2026-09-09-remove-fee-transaction-type-design.md`

**Workflow Mode:** `direct` (user is present reviewing each change; no automated verify/commit steps in any task — testing, pint, and commits are handled by the user between tasks)

**Complexity:** Medium (rolled up: all four tasks rated Medium — every step has exact code)

## Global Constraints

- PHP 8.4: explicit param types + return types on everything; curly braces on all control structures
- Enum helpers (`inflows()` / `outflows()`) are the convention for type lists in queries — `spendTypes()` is deleted (zero call sites)
- **No DB queries inside DTOs** (`.ai/rules/data.md`): relational loading (`with()`/`loadMissing()`), mapping, or filtering only — cross-row data comes from model relations eager-loaded at the controller/service layer
- SQL aggregates stay in SQL — never PHP reductions
- Svelte 5 runes only (`$props()`, `$derived`, `$state`); no legacy `export let` / `$:`
- `resolveKind()` in `resources/js/schema/transaction.schema.ts` is the **only** frontend code that maps raw type values; exception: `transaction-detail.svelte` keeps its direction-aware internals (transfer_in/transfer_out) because the 3-kind view intentionally discards direction and the detail page needs it for Source/Destination labels — it contains no `fee` references and needs no change
- `fee_amount` request plumbing is KEPT (input concept survives; only booking changes)
- **`TransactionListData` is scoped to the list chain only** — `transaction-list.svelte` and its relatives (`transaction-list-item`, `transaction-list-filter`, `pages/transactions/index.svelte`) consume `Data.TransactionListData` via `import type { Data } from '@type/type';` (the proven `Data.TransactionDetailData` pattern). The form, the schema, `edit.svelte`, `show.svelte`, `dashboard.svelte`, and `transaction-card.svelte` keep `App.Models.Transaction`
- Never hardcode URLs — Wayfinder `.url()` only
- After backend changes: `php artisan wayfinder:generate`. After DTO changes: `composer generate:ts`

---

### Task 1: Remove the `fee` type from the backend

**Complexity:** Medium

**Files:**
- Modify: `app/Enums/TransactionType.php`
- Modify: `app/Services/BalanceService.php`
- Modify: `app/Services/SpendingService.php:20`, `app/Services/SpendingService.php:48`
- Modify: `app/Services/ReportService.php:46-49`, `:85`, `:103`, `:127`, `:243`
- Test: `tests/Feature/TransactionObserverTest.php:72-84` (delete the fee test)

**Interfaces:**
- Consumes: nothing new.
- Produces: `TransactionType` with 4 cases; `inflows()` = `['income', 'transfer_in']`, `outflows()` = `['expense', 'transfer_out']`; `spendTypes()` gone. All later tasks and the frontend enum union depend on this shape.

- [x] **Step 1: Rewrite `app/Enums/TransactionType.php`**

```php
<?php

namespace App\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';

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
        return [self::Expense->value, self::TransferOut->value];
    }
}
```

(`Fee` case and `spendTypes()` are gone; `outflows()` drops `fee`.)

- [x] **Step 2: Rewrite `app/Services/BalanceService.php` to use the helpers**

```php
<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BalanceService
{
    public function forAccount(Account $account): string
    {
        $cacheKey = "balance:account:{$account->id}";

        return Cache::tags(["account:{$account->id}"])->rememberForever($cacheKey, function () use ($account): string {
            $inflowTypes = TransactionType::inflows();
            $outflowTypes = TransactionType::outflows();

            $inflowPlaceholders = implode(', ', array_fill(0, count($inflowTypes), '?'));
            $outflowPlaceholders = implode(', ', array_fill(0, count($outflowTypes), '?'));

            $balance = DB::table('accounts')
                ->selectRaw(
                    "accounts.initial_balance + COALESCE(SUM(CASE
                        WHEN t.type IN ({$inflowPlaceholders}) THEN t.amount
                        WHEN t.type IN ({$outflowPlaceholders}) THEN -t.amount
                        ELSE 0
                    END), 0) AS balance",
                    [...$inflowTypes, ...$outflowTypes]
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

(Behavior identical to before minus `fee` — the helpers now exclude it.)

- [x] **Step 3: Drop `'fee'` from `SpendingService` type arrays**

Line 20: `->whereIn('type', ['expense', 'fee'])` → `->whereIn('type', ['expense'])`
Line 48: `->whereIn('t.type', ['expense', 'fee'])` → `->whereIn('t.type', ['expense'])`

(Kept as literals — no helper exists for expense-only semantics now that `spendTypes()` is deleted.)

- [x] **Step 4: Drop `'fee'` from `ReportService`**

Line 46-49 — the trend query bindings:

```php
                    $result = DB::table('transactions')
                        ->selectRaw('
                            SUM(CASE WHEN type IN (?, ?) THEN amount ELSE 0 END) AS total_income,
                            SUM(CASE WHEN type IN (?) THEN amount ELSE 0 END) AS total_expense
                        ', ['income', 'transfer_in', 'expense'])
```

(The second `CASE` had two placeholders `expense`/`fee`; it now has one: `expense`.)

Line 85 — docblock: `Category Leak — expense + fee totals ranked by category for a given period.` → `Category Leak — expense totals ranked by category for a given period.`

Lines 103, 127, 243: `->whereIn('type', ['expense', 'fee'])` / `->whereIn('t.type', ['expense', 'fee'])` → `['expense']` (three sites, same replacement).

- [x] **Step 5: Delete the fee test in `tests/Feature/TransactionObserverTest.php`**

Remove the entire block (lines 72-84):

```php
it('handles fee transactions as outflows', function (): void {
    [$user, $account] = createBalanceAccount();

    Transaction::factory()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 10_000,
        'type' => TransactionType::Fee->value,
        'transfer_link_id' => null,
    ]);

    expect($account->fresh()->current_balance)->toEqual(-10_000.0);
});
```

Expense-as-outflow coverage already exists one test above ("decrements account balance on expense transaction created"). Do not delete any other test in the file.

---

### Task 2: Book transfer fees as expenses

**Complexity:** Medium

**Files:**
- Modify: `app/Services/TransactionService.php:127-135` (fee branch) + new private helper
- Test: `tests/Feature/TransactionTest.php` (replace the fee test, add the fallback test)

**Interfaces:**
- Consumes: `TransactionType::Expense` from Task 1; `Category::levelChildren()` scope; `CategorySeeder`'s global `Admin Fees` child category (matched by name).
- Produces: `createTransfer(...)` unchanged signature; with a fee it now writes a third row `type = expense` carrying the Admin Fees `category_id` (or `null`), same `transfer_link_id`. Task 3's DTO tests build transfers through this behavior.

- [x] **Step 1: Replace the fee test in `tests/Feature/TransactionTest.php`**

Delete the existing test `creates a transfer with fee when fee_amount is provided` (lines 81-99) and put these two in its place:

```php
it('books a transfer fee as an expense in the Admin Fees category', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id]);

    $parent = Category::factory()->create(['name' => 'Finance']);
    $adminFees = Category::factory()->create(['name' => 'Admin Fees', 'parent_id' => $parent->id]);

    $this->actingAs($user)->post(route('transactions.store', $sourceAccount), [
        'type' => 'transfer',
        'amount' => 500_000,
        'transaction_date' => now()->toDateString(),
        'destination_account_id' => $destAccount->id,
        'fee_amount' => 6_500,
    ])->assertRedirect();

    $linkId = Transaction::where('account_id', $sourceAccount->id)
        ->where('type', TransactionType::TransferOut->value)
        ->value('transfer_link_id');

    $feeRow = Transaction::where('transfer_link_id', $linkId)
        ->where('type', TransactionType::Expense->value)
        ->first();

    expect(Transaction::where('transfer_link_id', $linkId)->count())->toBe(3);
    expect($feeRow)->not->toBeNull();
    expect($feeRow->account_id)->toBe($sourceAccount->id);
    expect($feeRow->category_id)->toBe($adminFees->id);
    expect((float) $feeRow->amount)->toBe(6_500.0);
});

it('books a transfer fee as uncategorized when no Admin Fees category exists', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('transactions.store', $sourceAccount), [
        'type' => 'transfer',
        'amount' => 500_000,
        'transaction_date' => now()->toDateString(),
        'destination_account_id' => $destAccount->id,
        'fee_amount' => 6_500,
    ])->assertRedirect();

    $linkId = Transaction::where('account_id', $sourceAccount->id)
        ->where('type', TransactionType::TransferOut->value)
        ->value('transfer_link_id');

    $feeRow = Transaction::where('transfer_link_id', $linkId)
        ->where('type', TransactionType::Expense->value)
        ->first();

    expect($feeRow)->not->toBeNull();
    expect($feeRow->category_id)->toBeNull();
});
```

(The `Category` import already exists at the top of the file.)

- [x] **Step 2: Change the fee branch in `TransactionService::createTransfer()`**

Replace lines 127-135:

```php
            if ($feeAmount !== null && $feeAmount > 0) {
                $this->create($sourceAccount, $creator, [
                    'amount' => $feeAmount,
                    'type' => TransactionType::Fee->value,
                    'transfer_link_id' => $linkId,
                    'transaction_date' => $transactionDate,
                    'description' => 'Transfer fee',
                ]);
            }
```

with:

```php
            if ($feeAmount !== null && $feeAmount > 0) {
                $this->create($sourceAccount, $creator, [
                    'amount' => $feeAmount,
                    'type' => TransactionType::Expense->value,
                    'transfer_link_id' => $linkId,
                    'transaction_date' => $transactionDate,
                    'category_id' => $this->resolveTransferFeeCategory(),
                    'description' => 'Transfer fee',
                ]);
            }
```

- [x] **Step 3: Add the category resolver to `TransactionService`**

Add at the end of the class (after `createTransfer`):

```php
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
```

(`use App\Models\Category;` is already imported at line 9. Categories are global reference data — no per-user scoping exists, matching `CategoryService::getCategories()`.)

---

### Task 3: `TransactionListData` DTO + controller wiring + codegen

**Complexity:** Medium

**Files:**
- Modify: `app/Models/Transaction.php` (add `relatedTransaction()` relation)
- Create: `app/Data/Transaction/TransactionListData.php`
- Modify: `app/Services/TransactionService.php:18-21` (`getTransactions()` eager load)
- Modify: `app/Http/Controllers/TransactionController.php:31-35` (index), `:84-93` (edit)
- Test: `tests/Feature/TransactionTest.php` (add two DTO tests)

**Interfaces:**
- Consumes: `Transaction` model (casts: `type` → enum, `amount` → `decimal:0` string, `transaction_date` → Carbon); `CategoryService::getCategories()` for the edit fix.
- Produces: new `Transaction::relatedTransaction(): HasOne` relation (opposite leg of the transfer pair). `TransactionListData::fromTransaction(Transaction $transaction): self` — maps `destination_account_id` and `related_transaction` from the loaded relation; uses `loadMissing()` (relational loading) so ad-hoc callers never get silent nulls, while eager-loaded callers skip the query entirely. Frontend type `Data.TransactionListData` appears after codegen with fields: `id, type, amount, description, transaction_date, category_id, account_id, transfer_link_id, destination_account_id, related_transaction, account, category`. Task 4 consumes exactly this shape.

- [x] **Step 0: Add the `relatedTransaction()` relation to `app/Models/Transaction.php`**

Add below the existing `creator()` relation (and `use Illuminate\Database\Eloquent\Relations\HasOne;` to the imports):

```php
    public function relatedTransaction(): HasOne
    {
        $oppositeType = $this->type === TransactionType::TransferIn
            ? TransactionType::TransferOut
            : TransactionType::TransferIn;

        return $this->hasOne(Transaction::class, 'transfer_link_id', 'transfer_link_id')
            ->where('type', $oppositeType);
    }
```

(Self-referencing `hasOne` keyed by `transfer_link_id`, filtered to the opposite transfer type. Rows with `transfer_link_id = null` match nothing; the fee-expense row — `type = expense` — resolves to the `transfer_in` leg, which is harmless. Standing rule: DTOs never query — this relation is their only path to cross-row data.)

- [x] **Step 1: Add the DTO tests to `tests/Feature/TransactionTest.php`**

Append at the end of the file:

```php
it('resolves destination_account_id for both sides of a transfer pair', function (): void {
    [$user, $sourceAccount] = createAccountForUser();
    $destAccount = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('transactions.store', $sourceAccount), [
        'type' => 'transfer',
        'amount' => 250_000,
        'transaction_date' => now()->toDateString(),
        'destination_account_id' => $destAccount->id,
    ])->assertRedirect();

    $outflow = Transaction::where('type', TransactionType::TransferOut->value)->first();
    $inflow = Transaction::where('type', TransactionType::TransferIn->value)->first();

    expect(TransactionListData::fromTransaction($outflow)->destination_account_id)->toBe($destAccount->id);
    expect(TransactionListData::fromTransaction($inflow)->destination_account_id)->toBe($sourceAccount->id);
});

it('sets destination_account_id to null for non-transfer transactions', function (): void {
    [$user, $account] = createAccountForUser();
    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
    ]);

    expect(TransactionListData::fromTransaction($transaction)->destination_account_id)->toBeNull();
});
```

And add the import next to the other `use` statements at the top:

```php
use App\Data\Transaction\TransactionListData;
```

- [x] **Step 2: Create `app/Data/Transaction/TransactionListData.php`**

```php
<?php

namespace App\Data\Transaction;

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

        public float $amount,

        public ?string $description,

        public string $transaction_date,

        public ?int $category_id,

        public int $account_id,

        public ?string $transfer_link_id,

        public ?int $destination_account_id,

        #[TypeScriptModel(Transaction::class)]
        public mixed $related_transaction,

        #[TypeScriptModel(Account::class)]
        public mixed $account,

        #[TypeScriptModel(Category::class)]
        public mixed $category,
    ) {}

    /**
     * Build from a transaction, mapping the transfer counterpart from the
     * relatedTransaction relation. No DB queries here (standing rule:
     * relational loading, mapping, filtering only) — `loadMissing` covers
     * ad-hoc callers; eager-loaded callers skip it entirely.
     */
    public static function fromTransaction(Transaction $transaction): self
    {
        $related = $transaction->loadMissing('relatedTransaction')->getRelation('relatedTransaction');

        return new self(
            id: $transaction->id,
            type: $transaction->type,
            amount: (float) $transaction->amount,
            description: $transaction->description,
            transaction_date: $transaction->transaction_date->toDateString(),
            category_id: $transaction->category_id,
            account_id: $transaction->account_id,
            transfer_link_id: $transaction->transfer_link_id,
            destination_account_id: $related?->account_id,
            related_transaction: $related,
            account: $transaction->relationLoaded('account') ? $transaction->account : null,
            category: $transaction->relationLoaded('category') ? $transaction->category : null,
        );
    }
}
```

(Note: `getRelation()` does not trigger lazy loading — it reads the already-loaded value, so this factory only ever issues SQL through the explicit `loadMissing()` relational load.)

- [x] **Step 3: Wire the controller**

In `app/Http/Controllers/TransactionController.php` — replace `index()`:

```php
    public function index(Request $request, Account $account): Response
    {
        $this->authorize('viewAny', [Transaction::class, $account]);

        return Inertia::render('transactions/index', [
            'transactions' => $this->transactionService->getTransactions()
                ->through(fn (Transaction $transaction): TransactionListData => TransactionListData::fromTransaction($transaction)),
            'summary' => [],
        ]);
    }
```

Replace `edit()` (raw model stays — the DTO is list-only; this also fixes the
pre-existing bug where it called the non-existent
`$this->transactionService->getCategories()`):

```php
    public function edit(Request $request, Account $account, Transaction $transaction): Response
    {
        $this->authorize('update', $transaction);

        return Inertia::render('transactions/edit', [
            'account' => $account,
            'transaction' => $transaction->load('category'),
            'categories' => CategoryService::getCategories(),
        ]);
    }
```

Eager-load the relation for the list payload in `app/Services/TransactionService.php` — `getTransactions()` line 20, so `loadMissing()` inside the DTO factory stays a no-op per row:

```php
    public static function getTransactions(): LengthAwarePaginator
    {
        return Transaction::query()
            ->with(['account', 'category', 'relatedTransaction.account'])
            ->latest('transaction_date')
            ->paginate(30);
    }
```

Add the import to the controller:

```php
use App\Data\Transaction\TransactionListData;
```

(`CategoryService` is already imported at line 12. `show()` is untouched — `TransactionDetailData` stays.)

- [x] **Step 4: Regenerate types**

```
php artisan wayfinder:generate
composer generate:ts
```

Verify: `grep TransactionListData resources/js/types/generated.d.ts` → expect `export type TransactionListData = {` mirroring `TransactionDetailData` (which sits at ~line 105).

---

### Task 4: Frontend 3-kind contract

**Complexity:** Medium

No test step — the repo has no JS test runner; correctness is enforced at compile time by the exhaustive `KIND_BY_TYPE` map and the `Record<TransactionKind, ...>` config maps (a missed raw-type handling or kind entry is a TS error), verified by the user when building.

**Files:**
- Modify: `resources/js/schema/transaction.schema.ts`
- Modify: `resources/js/components/module/transaction/transaction-type-badge.svelte`
- Modify: `resources/js/components/module/transaction/transaction-list-item.svelte`
- Modify: `resources/js/components/module/transaction/transaction-list.svelte`
- Modify: `resources/js/components/module/transaction/transaction-list-filter.svelte`
- Modify: `resources/js/components/module/transaction/transaction-card.svelte`
- Modify: `resources/js/pages/dashboard/dashboard.svelte`
- Modify: `resources/js/pages/transactions/show.svelte`
- Modify: `resources/js/pages/transactions/edit.svelte`
- Modify: `resources/js/components/module/transaction/transaction-form.svelte`
- Modify: `resources/js/pages/transactions/index.svelte`
- Unchanged on purpose: `transaction-detail.svelte` (no `fee` refs; direction-aware Source/Destination labels need the raw transfer types — see Global Constraints)

**Interfaces:**
- Consumes: `Data.TransactionListData` (Task 3), `TransactionType` enum union without `fee` (Task 1).
- Produces: `TransactionKind` + `resolveKind()` exported from `@schema/transaction.schema` — the only raw-type reasoning in the frontend.

- [x] **Step 1: Add the kind contract to `resources/js/schema/transaction.schema.ts`**

After the imports, add:

```typescript
export type TransactionKind = 'income' | 'expense' | 'transfer';

/**
 * Exhaustive map from the raw transaction type enum to the 3-kind UI view.
 * Adding an enum case without updating this map is a compile error.
 */
const KIND_BY_TYPE: Record<App.Enums.TransactionType, TransactionKind> = {
    income: 'income',
    expense: 'expense',
    transfer_out: 'transfer',
    transfer_in: 'transfer',
};

export function resolveKind(type: App.Enums.TransactionType): TransactionKind {
    return KIND_BY_TYPE[type];
}
```

Change the base schema generic (line 17-19) — the schema stays on the raw
model (form territory), only the kind mapping is added:

```typescript
const transactionSchema: DataSchema<
    App.Models.Transaction & { destination_account_id?: number; fee_amount?: number }
> = {
```

(Keep `fee_amount` in `TransactionFormData` and in `transferSchema` unchanged.)

- [x] **Step 2: Rewrite `transaction-type-badge.svelte`**

```svelte
<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { App } from '@wayfinder/types';

    import { resolveKind, type TransactionKind } from '@schema/transaction.schema';

    import Badge from '@components/ui/badge.svelte';

    let { type }: { type: App.Enums.TransactionType } = $props();

    const config: Record<TransactionKind, { label: string; color: ColorVariant }> = {
        income: { label: 'Income', color: 'success' },
        expense: { label: 'Expense', color: 'error' },
        transfer: { label: 'Transfer', color: 'info' },
    };

    const badge = $derived(config[resolveKind(type)]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
```

(Callers keep passing raw `type`; the badge collapses it. The `TransactionType` import is gone.)

- [x] **Step 3: Rewrite the `transaction-list-item.svelte` module block and instance script**

Module script (replaces the 5-key `TYPE_STYLE`):

```svelte
<script lang="ts" module>
    import type { App } from '@wayfinder/types';

    import type { TransactionKind } from '@schema/transaction.schema';

    /* ── Kind style map ──────────────────────────────────── */

    export const TYPE_STYLE: Record<
        TransactionKind,
        { label: string; color: string; bg: string; icon: string; signIcon: string }
    > = {
        income: {
            label: 'Income',
            color: 'var(--color-success)',
            bg: 'color-mix(in oklab, var(--color-success) 12%, transparent)',
            icon: 'solar--arrow-up-line-duotone',
            signIcon: 'solar--add-bold-duotone',
        },
        expense: {
            label: 'Expense',
            color: 'var(--color-error)',
            bg: 'color-mix(in oklab, var(--color-error) 12%, transparent)',
            icon: 'solar--arrow-down-line-duotone',
            signIcon: 'solar--minus-bold-duotone',
        },
        transfer: {
            label: 'Transfer',
            color: 'var(--color-info)',
            bg: 'color-mix(in oklab, var(--color-info) 12%, transparent)',
            icon: 'solar--transfer-horizontal-bold-duotone',
            signIcon: '',
        },
    };
</script>
```

Instance script — replace the `TransactionType` import line and the props/derived section:

```svelte
<script lang="ts">
    import type { RestProps } from '@type/index';

    import { Link } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import { resolveKind } from '@schema/transaction.schema';
    import Formatter from '@utilities/formatter';
    import { cn } from '@utilities/shadcn';

    /* ── Props ───────────────────────────────────────────── */

    interface Props extends RestProps {
        transaction: App.Data.TransactionListData;
        class?: string;
    }

    let { transaction, class: _class }: Props = $props();

    const typeConfig = $derived(TYPE_STYLE[resolveKind(transaction.type)]);
</script>
```

(The template below is unchanged — it only reads `typeConfig` and `transaction.description/category/account/amount`, all present on the DTO. `App` type visibility across the module/instance scripts mirrors the current file.)

- [x] **Step 4: Switch `transaction-list.svelte` to DTO + kind**

Module script line 30:

```typescript
    export type TransactionListTable = SvelteTable<TransactionListFeatures, App.Data.TransactionListData>;
```

Instance script — replace the `TransactionType` import (line 38) with:

```typescript
    import { resolveKind } from '@schema/transaction.schema';
```

Props (lines 56-59):

```typescript
    interface Props extends RestProps {
        transactions: App.Data.TransactionListData[];
        class?: string;
    }
```

Columns type (line 63):

```typescript
    const columns: ColumnDef<typeof transactionListFeatures, App.Data.TransactionListData>[] = [
```

Replace the raw `type` column (`accessorKey: 'type'`, lines 94-98) with:

```typescript
        {
            id: 'type',
            accessorFn: (row) => resolveKind(row.type),
            filterFn: 'arrayHas',
            enableGlobalFilter: false,
        },
```

Grouped-map types (lines 118-119) — replace both `App.Models.Transaction[]` with `App.Data.TransactionListData[]`:

```typescript
    const groupedTransactions = $derived.by<[string, App.Data.TransactionListData[]][]>(() => {
        const groups = new SvelteMap<string, App.Data.TransactionListData[]>();
```

Summary loop (lines 138-149) — replace with:

```typescript
        for (const transaction of filteredTransactions) {
            const kind = resolveKind(transaction.type);

            if (kind === 'income') income += transaction.amount;
            else if (kind === 'expense') expense += transaction.amount;
        }
```

(Intentional behavior change: transfer rows no longer count into the Income/Expense summary strip — previously `transfer_in` inflated Income while `transfer_out` was ignored, an asymmetry. A transfer moves money; it is neither.)

- [x] **Step 5: Switch `transaction-list-filter.svelte` to kind values**

Add import (next to the `TYPE_STYLE` import at line 51):

```typescript
    import { type TransactionKind } from '@schema/transaction.schema';
```

Props (lines 61-64):

```typescript
    interface Props {
        transactions: App.Data.TransactionListData[];
        table: TransactionListTable;
    }
```

Filter state (lines 76-78, 109):

```typescript
    const types = $derived(
        getArrayFilter<TransactionKind>(columnFilters, FILTER_COLUMNS.type)
    );
```

```typescript
    let tTypes: TransactionKind[] = $state([]);
```

Sheet options `case 'type'` (lines 342-357):

```typescript
            case 'type':
                return Object.entries(TYPE_STYLE).map(([key, cfg]): SheetOption => {
                    const kind = key as TransactionKind;

                    return {
                        key,
                        label: cfg.label,
                        selected: tTypes.includes(kind),
                        onSelect: () => (tTypes = toggled(tTypes, kind)),
                        badge: {
                            text: cfg.label.charAt(0),
                            background: cfg.bg,
                            color: cfg.color,
                        },
                    };
                });
```

(`TYPE_STYLE[type].label` in `activeFilters` and `TYPE_STYLE[types[0]].label` in `filterChips` keep working unchanged — the map is now kind-keyed and the state holds kinds. The `App` import stays for the account/category decoration types. The filter now offers exactly 3 options: Income / Expense / Transfer.)

- [x] **Step 6: Kind-based sign in `transaction-card.svelte`**

Add import:

```typescript
    import { resolveKind } from '@schema/transaction.schema';
```

Replace lines 21-22:

```typescript
    const kind = $derived(resolveKind(transaction.type));
    const color = $derived(
        kind === 'income' ? 'text-success' : kind === 'expense' ? 'text-error' : 'text-info'
    );
    const signIcon = $derived(
        kind === 'income'
            ? 'solar--add-bold-duotone'
            : kind === 'expense'
              ? 'solar--minus-bold-duotone'
              : 'solar--transfer-horizontal-bold-duotone'
    );
```

And in the template (lines 49-53), replace the icon ternary:

```svelte
                <i class={cn(['iconify size-3 text-current', signIcon])}></i>
```

(Keep the `App.Models.Transaction` prop — this card is also rendered from raw-model pages; `resolveKind` works on the raw enum either way. Behavior change: transfer rows now show info color + transfer icon instead of the binary +/− treatment.)

- [x] **Step 7: Kind config in `dashboard.svelte`**

Replace the `TransactionType` import (line 5) with:

```typescript
    import { resolveKind, type TransactionKind } from '@schema/transaction.schema';
```

Replace `TYPE_STYLE` (lines 107-141) with:

```typescript
    const TYPE_STYLE: Record<
        TransactionKind,
        { label: string; color: string; bg: string; sign: string }
    > = {
        income: { label: 'Income', color: 'text-success', bg: 'bg-success/12', sign: '+' },
        expense: { label: 'Expense', color: 'text-error', bg: 'bg-error/12', sign: '−' },
        transfer: { label: 'Transfer', color: 'text-info', bg: 'bg-info/12', sign: '' },
    };
```

In the recent-transactions loop (line 366), change the lookup:

```svelte
                        {@const style = TYPE_STYLE[resolveKind(tx.type)]}
```

(`recent_transactions` stays `App.Models.Transaction[]` — the dashboard controller still passes raw models this pass; `resolveKind` handles it.)

- [x] **Step 8: `show.svelte` — kind-based transfer detection**

Add import:

```typescript
    import { resolveKind } from '@schema/transaction.schema';
```

Replace lines 22-26:

```typescript
    const isTransferRow = $derived(resolveKind(transaction.type) === 'transfer');
```

(`transaction` prop stays `Data.TransactionDetailData`; the `fee` branch disappears because an expense row never resolves to the `'transfer'` kind.)

- [x] **Step 9: `edit.svelte` — kind detection (prop stays the raw model)**

Props keep `App.Models.Transaction` — the edit page is not list territory:

```typescript
    let {
        account,
        transaction,
        categories,
    }: {
        account: App.Models.Account;
        transaction: App.Models.Transaction;
        categories: App.Models.Category[];
    } = $props();
```

Add import:

```typescript
    import { resolveKind } from '@schema/transaction.schema';
```

Replace lines 33-37:

```typescript
    const isTransferRow = $derived(resolveKind(transaction.type) === 'transfer');
```

- [x] **Step 10: `transaction-form.svelte` — kind-resolved mode (prop stays the raw model)**

Props interface (lines 14-21) — `transaction` stays `App.Models.Transaction`:

```typescript
    interface Props {
        type?: 'income' | 'expense' | 'transfer';
        account?: App.Models.Account;
        categories: App.Models.Category[];
        accounts?: App.Models.Account[];
        transaction?: App.Models.Transaction;
        onCancel?: () => void;
    }
```

Add import:

```typescript
    import { resolveKind } from '@schema/transaction.schema';
```

Replace the `isTransferType`/`resolvedType` derivations (lines 36-52) with:

```typescript
    const resolvedType = $derived<string>(
        isEdit && transaction ? resolveKind(transaction.type) : type
    );
```

(The form's three modes are exactly the three kinds, so `resolveKind` **is** the mode resolver. This also fixes old fee rows having opened "transfer" mode.)

`buildInitialData()` is unchanged — `destination_account_id` stays `null` on edit (the raw model cannot know the transfer counterpart).

- [x] **Step 11: `pages/transactions/index.svelte` — DTO payload type**

```typescript
    interface PaginatedTransactions {
        data: App.Data.TransactionListData[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
    }
```

(After all edits, the user-side build/svelte-check is expected to pass — the exhaustive maps fail compilation if any raw type or kind handling was missed.)
