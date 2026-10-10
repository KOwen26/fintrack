# Initial Balance as an Opening Income Transaction — Design

Date: 2026-10-09
Status: Approved design, pending implementation plan

## Problem

`account.initial_balance` is currently special-cased in three places:

1. `accounts.initial_balance` column drives balance math.
2. `AccountObserver` performs hidden multi-model writes: `created` copies it into `current_balance`; `updated` applies the delta on edit.
3. `BalanceService` adds it to the on-demand truth formula: `initial_balance + Σ inflows − Σ outflows`.

This is the only balance component that is not a transaction row, so it is invisible in account history and requires bespoke code in every balance path.

## Decision

Book the opening balance as a **real Income transaction row**, following the same pattern as transfer fees (`Admin Fees`): a plain row whose identity comes from a dedicated category, with all row mechanics delegated to `TransactionService`. No schema changes, no migrations, no new enum cases, no observer.

Balance truth becomes purely `Σ inflows − Σ outflows`. The `accounts.initial_balance` column stays as a **display copy** kept in lockstep by `AccountService`.

## Design

### 1. Identity: dedicated fixed category

- Seed a child category `Initial Balance` under the `Income` group in `CategorySeeder`, with `fixed: true` (same level as `Admin Fees` under `Finance`).
- The opening row books as `type = Income` (flow derives to `Inflow` automatically), `category_id` = the Initial Balance category, `description: 'Initial balance'`, uncategorized otherwise untouched.
- Identity lookup for edit-in-place: `account_id` + `category_id` + not soft-deleted.
- Presence is guaranteed by `CategorySeeder` — the app's categories are always seeded. Lookup is a find-only query (name `Initial Balance`, child of Income); no `firstOrCreate`, no self-healing. Test databases seed the seeder in a `beforeEach` wherever accounts with opening balances are created.
- No category mutation endpoints exist today (CategoryController is read-only), so the `fixed` flag needs no enforcement work now. If mutation endpoints arrive later, fixed categories must reject rename/delete — recorded as future work, not in scope here.

### 2. Booking path: AccountService decides, TransactionService executes

`AccountService` constructor-injects `TransactionService` (same pattern as `TransferService`).

Private `syncInitialBalance(Account $account, User $actor, int|float $amount): void` — idempotent "make the row match the amount":

| State | Action |
| --- | --- |
| amount > 0, no row | `TransactionService::create()` — Income row dated at `$account->created_at`, category Initial Balance, `created_by` = owner |
| row exists, amount changed | `TransactionService::update()` — row's own values, new amount (observer applies the delta) |
| amount = 0, row exists | `TransactionService::softDelete()` |
| amount = 0, no row | no-op |

Every path delegates to `TransactionService`, so there is exactly one booking mechanism (row persistence + `TransactionSaved`/`TransactionDeleted` dispatch). Note: the events currently have no listeners; reuse keeps a single path for when listeners arrive.

### 3. Service wiring

- `AccountService::create()`: `DB::transaction` { `Account::create` (column written by the request data) → `syncInitialBalance` when amount > 0 }.
- `AccountService::update()`: `$account->update($data)` → when `initial_balance` is dirty, `syncInitialBalance` with the new value, all inside one `DB::transaction`. The column and row cannot diverge: both are written by the same flow.

### 4. Removal of the magic

- Delete `AccountObserver` and the `#[ObservedBy]` attribute on `Account` (the observer's only jobs were initial_balance copies).
- `BalanceService::forAccount()`: truth = `COALESCE(SUM(CASE WHEN flow = 'inflow' THEN amount ELSE -amount END), 0)` — no `initial_balance` term, no `groupBy`, and the `DB::table` join goes entirely: the aggregate queries the `Transaction` model directly (the join only existed to pull the column into the sum).
- `Account` keeps the `initial_balance` cast (display copy).

### 5. Protection

Users can neither mint, re-categorize, nor delete opening rows. `CategoryService` owns the lookup (`initialBalanceCategoryId(): ?int`, find-only); it is shared by the booking path and the guards.

- `SaveTransactionRequest`: `category_id` gains `Rule::notIn([$initialBalanceCategoryId])` — the authoritative backstop against minting lookalike rows.
- `TransactionController::create`/`edit`: exclude the Initial Balance category from the `categories` page prop so it never appears in the form.
- `TransactionController::edit`: opening rows (matched by category) redirect to `accounts.edit`, the same way transfer members redirect to `transfers.edit`.
- `TransactionController::update`/`destroy`: `abort_if($transaction->category_id === $initialBalanceCategoryId, 422, 'Initial balance must be changed via the account.')` — mirroring the transfer-member guard. Destroy matters most: DELETE runs through no FormRequest, and an unguarded delete would silently decrement `current_balance` while the display column stays stale.

### 6. Factories

- `TransactionFactory::initialBalance()` state: `type = Income`, `category_id` = Initial Balance category (resolved lazily), used by tests and the seeder path.
- `AccountFactory` gains an `afterCreating` hook: when `initial_balance > 0`, book the opening row (creator = owner, date = account's `created_at`). This replaces what the observer used to do implicitly, so existing balance-asserting tests keep their semantics without per-test changes.

### 7. Seeder

`DummyDataSeeder::seedAccounts`: add a random `initial_balance` to the factory state (~80% of accounts get one, ~20% zero) and backdate `created_at` to just before the oldest activity month. The factory hook books naturally-dated opening rows automatically. This is the only seeder change.

### 8. Migration: none

No schema changes are required. Existing dev databases are NOT backfilled — balances of accounts with a non-zero `initial_balance` will drop by that amount unless the database is refreshed with `migrate:fresh --seed`. This is accepted; dummy data is the only dataset.

### 9. Frontend

- No changes required: the account form, `account.schema.ts`, and `account-detail.svelte` read the column, which stays.
- Opening rows appear naturally in transaction lists as Income rows with the Initial Balance category chip.
- No wayfinder regen needed (no controller/DTO/enum signature changes).
- README dual-tracking paragraph updated to the new truth formula.

### 10. Tests

- `AccountObserverTest` → rewritten as `AccountService` sync tests: create books row (and sets current_balance); edit updates the row in place (delta applied); edit to zero soft-deletes; no-op on unchanged; re-creates after the row was removed.
- `BalanceService` tests: column-free formula.
- `SaveTransactionRequest`: booking an Income row with the Initial Balance category is rejected.
- `TransactionController`: opening row update/destroy return 422; edit redirects to the account edit page.
- Test files that create accounts with opening balances seed `CategorySeeder` in a `beforeEach` (`AccountServiceTest`, `AccountTest`, `BalanceServiceTest`, plus the new files).
- Negative initial balances are out of scope — validation already enforces `min:0`; the observer's negative-value tests are dropped.

## Behavior compatibility

Old truth: `initial_balance + Σ(inflow − outflow)`. New truth: `Σ(inflow − outflow)` including the opening row whose amount equals `initial_balance`. Numerically identical for every account, before and after, with no `current_balance` backfill needed — the row books through the normal observer, which maintains the denormalized balance exactly as all other transactions do.

## Out of scope

- Negative/overdraft opening balances (validation forbids them).
- Any UI badge or special styling for opening rows.
- Backend enforcement of the `fixed` category flag (no mutation endpoints exist; revisit if they are added).
