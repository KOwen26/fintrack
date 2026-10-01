# Design: Remove `TransactionType::Fee` — fees are expenses

Date: 2026-09-09
Status: Approved direction, pending spec review

## Background

A transfer fee is currently modeled as its own `TransactionType::Fee` case. A
transfer with a fee writes **three** transaction rows: `transfer_out`,
`transfer_in`, and a standalone `fee` row on the source account. This is
conceptually wrong: a fee is not a transaction *type*, it is an *expense*
incurred as part of a transfer.

Secondary problems with the current implementation:

- `'fee'` (and `transfer_out` / `transfer_in`) raw strings are scattered
  across frontend badge config maps, list logic, and form mode checks.
- The frontend's conceptual model has 5 transaction types when the UI only
  ever presents 3 concepts: money in, money out, transfer.
- The transfer edit flow cannot reconstruct `destination_account_id` from a
  single raw row (it is hardcoded to `null` in the edit form today).
- SQL aggregates in `BalanceService`, `SpendingService`, and `ReportService`
  hardcode `'fee'` in type arrays.

**Data state:** the `transactions` table contains **zero** `fee` rows (only
`income` and `expense`). The `type` column is a plain string. Therefore **no
schema migration and no data backfill are required.**

## Decisions

1. **A fee is an expense.** Transfer fees are booked as regular `expense`
   transactions on the source account. `TransactionType::Fee` is deleted.
2. **The frontend sees exactly 3 kinds.** A frontend-only mapping reduces the
   4 remaining enum values (`income`, `expense`, `transfer_out`,
   `transfer_in`) to `'income' | 'expense' | 'transfer'`. The mapping is
   *not* a backend accessor.
3. **Transaction payloads are DTOs, not raw rows — for the list only.**
   Following the existing `TransactionDetailData` pattern, the
   `transactions/index` payload moves from raw Eloquent models to a new
   `TransactionListData` DTO, which carries `destination_account_id` and the
   nullable `related_transaction` (resolved through the `relatedTransaction`
   model relation — no queries inside DTOs). The edit page and forms keep raw
   `App.Models.Transaction` — the DTO affects `transaction-list` and its
   relatives only.
4. **One-pass change.** Remove and replace in a single coherent change. No
   phased "remove now, add later" — there is no data to migrate and a broken
   intermediate state buys nothing.

## Backend Changes

### Enum (`app/Enums/TransactionType.php`)

- Delete `case Fee = 'fee';`.
- `inflows()` unchanged: `[income, transfer_in]`.
- `outflows()` becomes `[expense, transfer_out]`.
- `spendTypes()` is **deleted** — it has zero call sites in the codebase
  (verified: only `inflows()`/`outflows()` are consumed, by
  `DashboardController` and `TransactionObserver`). Removing `Fee` from the
  remaining helpers automatically drops it from every query they feed.
- Note: the `AGENTS.md` / `CLAUDE.md` enum examples still show
  `spendTypes()`; those are agent-documentation snapshots and are not updated
  as part of this change.

### Transfer booking (`app/Services/TransactionService.php`)

`createTransfer()` currently creates a third row with `type = Fee`. After the
change, when `$feeAmount > 0` the third row is:

- `type` = `expense`
- `account_id` = source account (unchanged)
- `transfer_link_id` = same UUID as the pair (unchanged — deleting the
  transfer still soft-deletes the fee with it)
- `transaction_date` = same date (unchanged)
- `description` = `'Transfer fee'` (unchanged)
- `category_id` = the **Admin Fees** child category, looked up by
  `Category::where('name', 'Admin Fees')->levelChildren()->first()`.
  Categories are global reference data (seeded by `CategorySeeder`), so this
  lookup is deterministic. If the category does not exist (seeder never run,
  or renamed), fall back to `null` — the fee books as an uncategorized
  expense. This must not throw.

### SQL aggregates

Drop `'fee'` from every hardcoded type array. Two distinct treatments:

- `BalanceService`: replace the inline literal arrays with
  `TransactionType::inflows()` / `outflows()` bindings — this matches the
  convention already used by `DashboardController` and `TransactionObserver`,
  and the helpers no longer contain `'fee'` after the enum change.
- `SpendingService` (`['expense', 'fee']`, 2 sites) and `ReportService`
  (category-leak `['expense', 'fee']`, 3 sites; net-flow
  `['income', 'transfer_in', 'expense', 'fee']`, 1 site): drop `'fee'` from
  the literal arrays and keep them literal — no helper exists for these
  semantics now that the unused `spendTypes()` is being deleted.

### DTO (`app/Data/Transaction/TransactionListData.php` — new)

List/edit payload DTO, following `TransactionDetailData` conventions
(`#[TypeScript]`, `#[TypeScriptModel]` for relations):

- `id`, `type` (`TransactionType` enum), `amount`, `transaction_date`,
  `description`, `category_id` (nullable), `account_id`,
  `transfer_link_id` (nullable), `destination_account_id` (nullable),
  `related_transaction` (nullable).
- `related_transaction` is the opposite leg of the transfer pair (a
  `transfer_out` row's `transfer_in` sibling and vice versa), exposed as a
  nested model; `null` for non-transfer transactions.
- `destination_account_id` is **mapped from the loaded relation**
  (`related_transaction.account_id`), never queried inside the DTO — per the
  standing rule: no DB queries in DTOs; relational loading, mapping, or
  filtering only.
- New relation on `App\Models\Transaction`:

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

  (The fee-expense row shares the `transfer_link_id` but is `type = expense`,
  so the opposite-type filter keeps the resolution deterministic.)
- `TransactionController::index` — transform the paginator items via
  `->through(fn (Transaction $t) => TransactionListData::from($t))`.
- `TransactionController::edit` — keeps passing the raw model
  (`$transaction->load('category')`). While touching this action, fix the
  pre-existing bug where it calls the non-existent
  `$this->transactionService->getCategories()`; it must use
  `CategoryService::getCategories()`.
- `TransactionController::show` — `TransactionDetailData` stays; only the
  enum union shrinks. (`destination_account_id` on the detail DTO is a
  follow-up, see Out of Scope.)
- Run `composer generate:ts` after creating the DTO; run
  `php artisan wayfinder:generate` after controller signatures change.
- Pages consuming these payloads switch from `App.Models.Transaction` to the
  generated `App.Data.TransactionListData` type.

Scope note: only `TransactionController` payloads become DTOs in this pass.
Dashboard and report controllers continue passing raw models (they still
benefit from the frontend `resolveKind` mapping); converting them is a
follow-up.

## Frontend Changes

### Kind mapping (`resources/js/schema/transaction.schema.ts`)

The schema file is the single frontend contract point for transactions. It
gains the 3-kind view model alongside its existing schemas:

```typescript
export type TransactionKind = 'income' | 'expense' | 'transfer';

export function resolveKind(type: App.Enums.TransactionType): TransactionKind;
```

Mapping: `income → 'income'`; `expense → 'expense'`; `transfer_out`,
`transfer_in` → `'transfer'`. The function must be exhaustive over the enum
(a `default` branch that would fail compilation if a case is added). This is
the **only** place in the frontend that reasons about raw type values.

The base schema type switches from
`App.Models.Transaction & { destination_account_id?...; fee_amount?... }`
to `App.Data.TransactionListData` (which carries `destination_account_id`
natively after the DTO change).

### Consumers switch to `kind`

All of these currently embed raw type strings or `TransactionType.Fee` keys:

| File | Change |
| --- | --- |
| `components/module/transaction/transaction-type-badge.svelte` | Config map keyed by `TransactionKind` (3 entries); prop becomes `kind` |
| `components/module/transaction/transaction-list-item.svelte` | Remove `TransactionType.Fee` entry; key by `resolveKind` |
| `components/module/transaction/transaction-list.svelte` | Replace `type === Expense \|\| type === Fee` with `resolveKind(t.type) === 'out'` |
| `pages/dashboard/dashboard.svelte` | Remove `TransactionType.Fee` config entry; key by `resolveKind` |
| `pages/transactions/show.svelte` | Replace `type === 'fee'` branch — delete it (fees render as normal expenses) |
| `pages/transactions/edit.svelte` | Replace `'fee'`/type checks with `resolveKind` |
| `components/module/transaction/transaction-form.svelte` | Transfer-mode detection via `resolveKind(t.type) === 'transfer'`; prop stays `App.Models.Transaction` |
| `schema/transaction.schema.ts` | Home of `TransactionKind` / `resolveKind()`; base schema type stays `App.Models.Transaction`; keep `fee_amount` (still a valid transfer-form input) |

Fee rows now render and edit as ordinary expenses — which also fixes today's
quirk where a fee row opened the "transfer" edit mode.

`fee_amount` request plumbing (`StoreTransactionRequest`, controller,
transfer form input) is **kept** — the input concept "transfer with optional
fee" survives; only the booking changes.

## Error Handling

- Missing Admin Fees category → `category_id = null` (uncategorized expense);
  never blocks the transfer.
- Missing sibling row for `transfer_link_id` → `destination_account_id = null`.
- No new user-facing error states.

## Testing

- `TransactionTest` — update "creates a transfer with fee": third row is
  `type = expense`, carries the Admin Fees category id, same
  `transfer_link_id`, same source account. Keep/verify the no-fee case
  creates exactly two rows.
- `TransactionTest` — new: `TransactionListData` resolves
  `destination_account_id` for both sides of a transfer pair; `null` for
  non-transfer transactions.
- `TransactionObserverTest` — delete "handles fee transactions as outflows"
  (the case no longer exists; expense-as-outflow is covered by existing
  expense observer coverage — verify before deleting).
- Run affected Pest files, then the full suite (`php artisan test --compact`).
- No JS test infrastructure exists for `resolveKind`; exhaustiveness is
  enforced at compile time instead.

## Rollout

- No migration, no backfill, no cache invalidation concerns (no fee rows
  exist; balance cache keys unaffected).
- Deploy is a plain code deploy: enum + services + DTO + frontend + regenerated
  Wayfinder/TS types in one change.

## Out of Scope / Follow-ups

- Dashboard and report controllers moving to DTOs.
- `destination_account_id` on `TransactionDetailData` (show page).
- Full transfer round-trip editing (today `update()` edits a single row's
  scalar fields; editing a transfer as a unit is a separate feature).
- Renaming/aliasing `transfer_out`/`transfer_in` at the API level — raw values
  stay; only the frontend view of them changes.
