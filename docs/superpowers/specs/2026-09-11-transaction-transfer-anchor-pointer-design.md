# Transaction Transfer & Linking — Implementation Spec (Approach A: Anchor Pointer)

- **Date:** 2026-09-11 (revised same day — endpoint split settled)
- **Status:** SUPERSEDED — never implemented; replaced by `2026-09-11-transfer-aggregate-design.md` (Approach B, adopted after domain-pattern analysis)
- **Decision record:** `2026-09-10-transaction-transfer-linking-design.md` (approach comparison A/B/C, review findings, full rationale for every settled decision)
- **Scope:** `transactions` table linking concept, transfer unit lifecycle, `type`/`flow` normalization, API surface, service + DTO + frontend fallout

---

## 1. Background

Transfers move money between two of a user's accounts and are booked as **two transaction rows** (outflow leg on the source, inflow leg on the destination), optionally plus a **fee row** on the source.

The linking mechanism churned through three designs before this spec:

1. **UUID group key (deprecated).** Shared `Str::uuid()` per unit in `transfer_link_id`. No referential integrity, orphan legs on partial deletes.
2. **Sibling-pointer FK (interim schema).** `transfer_link_id` became a self-FK requiring a real transaction id while the service still wrote UUIDs — the FK correctly rejected every transfer insert until the drift was found.
3. **Mutual pointers (code at time of writing).** Outflow and inflow point at each other. Works, but needs a two-phase write (insert both legs, then patch the outflow), encodes no ordering, and cannot admit the fee row into the unit (its pointer slot is spent on the sibling).

This spec defines the chosen design — **anchor pointer via `transfer_parent_id`** — end to end. Approach selection and alternatives (explicit `transfers` aggregate table; key-without-FK) are covered in the decision record; a summary lives in §13.

## 2. Goals

- A transfer is **one logical unit** — **one row per unit in the global transactions list** (the anchor, carrying the destination from its inflow leg); account-scoped lists show the leg belonging to that account. Edited as a whole, deleted as a whole.
- **Referential integrity enforced by the database**, not app discipline — on inserts (FK validity) *and* hard deletes (`restrictOnDelete` blocks anchor deletion while dependents exist).
- Unit membership includes the **optional fee**; the fee dies with the unit (explicit behavior change, §11).
- **Edit = delete + re-create** the unit through `PUT /transfers/{anchor}`; no leg-by-leg field sync. The unit's edit payload covers all three rows (anchor, inflow, fee). Direct fee-row edits via `PUT /transactions/{fee}` are a locked-field exception (§8), not a unit recreate.
- **Split write endpoints for plain vs transfer shapes** — unconditional validation rules per endpoint, no `type`-conditional matrix.
- Directional truth (`in`/`out`) becomes a structural column so balance math, sign icons, and cascade logic stop depending on enumerated type lists.

## 3. Non-goals

- Split / multi-leg transfers (single source → single destination only; the dependent-pointer rule generalizes informally if ever needed).
- Cross-user transfers.
- Restoring deleted transfer legs (no restore route exists; if one is added later it must operate at unit level — §12).
- **Atomic type conversion over HTTP** (plain ↔ transfer in one request) — the edit UI fixes the type; conversion is delete + create via the appropriate endpoint. The service retains the capability internally for future callers.
- Data migration from the current dev schema — `migrate:fresh` + reseed is the strategy (dev data is disposable).

## 4. Settled decisions

| Decision | Value |
|---|---|
| `type` semantics | `income \| expense \| transfer` (3 cases) |
| `flow` semantics | `inflow \| outflow` — the row's effect sign on its account |
| Pairing rule | `income → inflow`, `expense → outflow`, `transfer → either` — **derived server-side in the service, never request validation** (transfer payloads carry no `type`; plain payload `type` implies its `flow`) |
| Fee identity | `type = expense`, `flow = outflow`, booked on the source account, `transfer_parent_id` = anchor id. **Fee detection rule:** `transfer_parent_id !== NULL && type = expense` |
| Linking column | `transfer_parent_id` — every dependent points at its unit's anchor (the outflow row); NULL on the anchor itself |
| FK delete action | `restrictOnDelete()` — hard-deleting an anchor with live dependents is a DB error; service deletes dependents first, anchor last |
| **Endpoint split** | `POST/PUT /transactions` for plain + fee rows; `POST/PUT /transfers` for transfer units (§7). `GET` index/show and `DELETE /transactions/{id}` stay unified — unit resolution in the service |
| **`type` in payloads** | absent from transfer payloads (the endpoint implies it); plain payloads carry `income \| expense` only |
| Unit edit | `PUT /transfers/{anchor}`: delete unit + re-create; payload covers anchor + inflow + fee |
| Fee-row direct edit | `PUT /transactions/{fee}`: locked-field in-place update (§8) — amount, category, description, date only; `account_id`/`type`/`flow`/pointer immutable via this path |
| Fee lifecycle | fee is deleted with the unit (reverses today's "fee survives" behavior — §11) |
| List rendering | global list = one row per unit (anchor) + plain rows + fee rows; inflow legs hidden globally, visible on the destination account's list |
| Report semantics | **type-based**: dashboard/trend "income" = `type = income` only, "expense" = `type = expense` only; transfer legs excluded (behavior change — §11). Balance math stays flow-based |
| Plain row edit | `PUT /transactions/{id}`: direct field update (no unit involved) |
| Authorization | creator-based policy (unchanged); every unit member is booked by the same creator, so any unit cascade is creator-initiated |
| Fold | `TransactionListData` carries both ends (`destination_account_id`, `related_transaction`) |
| Observer | flow-based multiplier; fix the pre-existing account-change reversal bug (§10) |
| Data strategy | `migrate:fresh` + `DummyDataSeeder` reseed |

## 5. Foundation — `type` / `flow` split

### 5.1 Schema

```php
$table->string('type');                              // TransactionType: income | expense | transfer
$table->string('flow');                              // TransactionFlow: inflow | outflow
$table->index(['account_id', 'transaction_date']);   // replaces [account_id, type, transaction_date]
$table->index(['account_id', 'flow']);               // balance aggregates
```

Note: type-filtered report queries (`type = expense` + date range) lose their dedicated composite index and ride `[account_id, transaction_date]` with a post-index filter — acceptable at personal-finance per-account row counts; revisit only if report queries surface in slow-query logs.

### 5.2 Enums

```php
enum TransactionType: string { case Income = 'income'; case Expense = 'expense'; case Transfer = 'transfer'; }
enum TransactionFlow: string { case Inflow = 'inflow'; case Outflow = 'outflow'; }
```

### 5.3 Code deleted by the split

- `TransactionType::inflows()` / `outflows()` helper arrays — consumers are `BalanceService`, `TransactionObserver`, and `DashboardController` (all rewired per §5.4/§5.6)
- `resolveKind()` + `KIND_BY_TYPE` in `transaction.schema.ts`; `TransactionKind` frontend union (replaced by the real `type` enum)
- The `'transfer'` pseudo-type accepted by `SaveTransactionRequest` — transfers no longer send `type` at all (the endpoint implies it); plain endpoints validate `Rule::enum(TransactionType::class)` against `income \| expense`
- The `isTransfer()` branch in `TransactionController::store()` — each endpoint dispatches to exactly one service method

### 5.4 Balance math

`BalanceService::forAccount()` CASE collapses from enumerated type lists to:

```sql
SUM(CASE WHEN t.flow = 'inflow' THEN t.amount ELSE -t.amount END)
```

`TransactionObserver::directionMultiplier()` becomes `flow === Inflow ? 1 : -1`. Adding new types later never touches balance code.

### 5.5 Frontend fallout

- Wayfinder enum regen (`TransactionType` 3 cases, new `TransactionFlow`) + `composer generate:ts`
- `transaction.schema.ts`: `resolveKind`/`KIND_BY_TYPE` deleted; `TransactionKind` union removed
- `transaction-list-item.svelte` / `transaction-list-filter.svelte`: `TYPE_STYLE` keyed on the 3 real types; sign icon + color derive from `flow`
- `transaction-detail.svelte`: `['income', 'transfer_in'].includes(type)` and `transfer_out`/`transfer_in` string checks → `flow`-based / 3-type checks
- `dashboard.svelte`: its **duplicated** local `TYPE_STYLE` + `resolveKind` usage → shared 3-type styling
- `accounts/show.svelte`: client-side inflow/outflow filters on type strings → `flow`-based
- `types/generated.d.ts`: `transfer_parent_id: number | null`; `type` narrows to 3 values; `flow` added
- **Endpoint routing:** create form's active tab selects the submit URL (`TransactionsController.store.url()` vs `TransfersController.store.url()`); transfer edits post to `TransfersController.update.url({ transaction: anchorId })`; a fee row's edit action links to the **parent transfer's** edit page (§9)

### 5.6 Report & summary semantics (decided: type-based)

All summary/report queries that mean "income" or "expense" filter on `type`, never on `flow`; only balance math uses `flow`.

| Consumer | Today | After |
|---|---|---|
| `DashboardController` monthly_income / monthly_expenses | `type IN (income, transfer_in)` / `type IN (expense, transfer_out)` via helper arrays | `type = income` / `type = expense` — **numbers change** (§11) |
| `ReportService::trend()` | raw literals `('income', 'transfer_in')` / `('expense')` | `type = income` / `type = expense` — literals must be rewritten, not left to drift |
| `ReportService::categorySpending()`, `fixedVsVariable()`, `SpendingService::globalCategorySpending()` | `whereIn('type', ['expense'])` literals | `TransactionType::Expense->value` — semantics unchanged (fee rows still count toward category spending, now by explicit decision) |
| `ReportService::contributionSplit()` | `type = 'income'` | unchanged semantics |

## 6. Linking model — anchor pointer via `transfer_parent_id`

### 6.1 Schema

```php
$table->foreignId('transfer_parent_id')->nullable()->constrained('transactions', 'id')->restrictOnDelete();
$table->index('transfer_parent_id');
```

Semantics: `transfer_parent_id` = *the id of my unit's anchor (the outflow row)*. NULL on the anchor itself, on income, and on plain expense rows. The fee participates by pointing at the anchor.

**Member invariants** (what each row may be):

| Row shape | `type` | `flow` | `transfer_parent_id` |
|---|---|---|---|
| Anchor | `transfer` | `outflow` | NULL |
| Inflow leg | `transfer` | `inflow` | anchor id |
| Fee | `expense` | `outflow` | anchor id |
| Plain row | `income`/`expense` | matching flow | NULL |

### 6.2 Unit resolution (the one rule)

```
anchor = (type = transfer AND flow = outflow) ? me : my parentTransaction
unit   = anchor + all rows whose transfer_parent_id = anchor.id
```

Members: anchor (outflow), inflow, fee (if present).

### 6.3 Model relations

```php
public function parentTransaction(): BelongsTo      // transfer_parent_id -> my anchor
public function dependentTransactions(): HasMany    // rows pointing at me
```

`relatedTransaction()` (the fold's convenience) resolves directionally **from pre-loaded relations**:
- dependents (inflow) → their anchor
- anchor → its dependent with `flow = inflow` (the true counterpart; the fee row resolves to nothing for the fold — `destination_account_id` is NULL on fee rows)

## 7. API surface — routes & validation

### 7.1 Routes

| Route | Name | Purpose |
|---|---|---|
| `POST /transactions` | `transactions.store` | create a plain row (`income`/`expense`) |
| `PUT /transactions/{transaction}` | `transactions.update` | edit a plain row **or** locked-field fee edit |
| `POST /transfers` | `transfers.store` | create a transfer unit (anchor + inflow + optional fee) |
| `PUT /transfers/{transaction}` | `transfers.update` | edit a transfer unit — binds the **anchor** row id |
| `GET /transactions`, `GET /transactions/{transaction}` | `transactions.*` | unified — lists fold units; show works on any row |
| `DELETE /transactions/{transaction}` | `transactions.destroy` | unified — unit resolution in the service, works from any member |

Controllers: a new thin `TransferController` (`store`, `update`) delegating to `TransactionService`; `TransactionController` keeps everything else and loses its `isTransfer()` dispatch branch.

**Anchor guard:** `PUT /transfers/{transaction}` must reject (404/422) any bound row that is not `(type = transfer, flow = outflow)` — an inflow leg or fee id is not a valid transfer handle. Conversely, `PUT /transactions/{transaction}` rejects anchor and inflow rows (422, pointing the client at `PUT /transfers/{anchor}`).

### 7.2 Validation matrix — unconditional per endpoint

| | `SaveTransactionRequest` (`POST/PUT /transactions`) | `SaveTransferRequest` (`POST/PUT /transfers`) |
|---|---|---|
| `type` | required, `Rule::enum` limited to `income \| expense` | **absent** — the endpoint implies it |
| `account_id` | required | required (source account) |
| `destination_account_id` | **rejected** | required, `different:account_id` |
| `category_id` | required (income/expense carry one) | **rejected** (transfers carry no category) |
| `fee_amount` | **rejected** | nullable, `numeric`, `min:0.01` |
| `amount` | required | required |
| `transaction_date` | required, `date`, `before_or_equal:today` | same |
| `description` | nullable | nullable |

No `Rule::requiredIf` anywhere — shape is known from the URL. Locked-field enforcement for fee rows (which fields a fee may change) lives in the controller/service, not the FormRequest — the request has no row context at validation time.

### 7.3 Write DTOs

- `TransactionData` (plain): `account_id`, `type: TransactionType` (real enum — the raw-string workaround dies), `amount`, `transaction_date`, `category_id`, `description`. Internally gains `flow: TransactionFlow` and `?int $transfer_parent_id` — never client-sent.
- `TransferData` (unit): `account_id` (source), `destination_account_id`, `amount`, `fee_amount`, `transaction_date`, `description`. No `type`, no `flow`, no pointer.

## 8. Service semantics

### 8.1 `createTransfer(User, TransferData)` (single `DB::transaction`)

1. create anchor: `(transfer, outflow)`, `transfer_parent_id = NULL`
2. create inflow: `(transfer, inflow)`, `transfer_parent_id = anchor.id`
3. if `fee_amount`: create `(expense, outflow)`, `transfer_parent_id = anchor.id`, category = Admin Fees ?? null

Every insert is FK-valid in a single pass — no post-insert patching.

### 8.2 `update()` — per-endpoint semantics

**`updatePlain(Transaction, TransactionData)`** (via `PUT /transactions/{id}`):
- Row must be plain or fee — anchor/inflow rows are rejected by the endpoint guard (§7.1)
- **Plain row** → direct field update, row id preserved
- **Fee row** → **locked-field in-place update**: `amount`, `category_id`, `description`, `transaction_date` only. Attempts to change `account_id`, `type`, or send `destination_account_id` → 422. Never a unit recreate — this closes the data-loss path where a fee edit destroyed both legs
- Type flips are rejected on this endpoint (§3) — conversion is delete + create via the right endpoint

**`updateTransfer(Transaction $anchor, TransferData)`** (via `PUT /transfers/{anchor}`):
- delete unit (dependents first, anchor last; members dispatch `TransactionDeleted`, observers reverse balances) → re-create the full unit from payload. **The payload covers all three rows** — `amount` (legs), `fee_amount` (fee), shared date/description — so editing the transfer edits the fee too

Type conversion remains a service-level capability (unit delete + re-create in the target shape) but is not exposed over HTTP.

### 8.3 `softDelete()` — unified

Resolve unit → soft-delete every member (**fee included**, §11). Deleting any member kills the whole unit, symmetric. Delete order (dependents first, anchor last) is specified so a future force-delete path inherits FK-safe ordering for free — soft deletes don't fire the FK either way.

### 8.4 List queries

**`getTransactions()`** (global list): anchors + plain rows + fee rows — hide inflow legs:

```php
->whereNot(fn ($q) => $q->where('type', 'transfer')->where('flow', 'inflow'))
->with(['account', 'category', 'dependentTransactions.account', 'parentTransaction.account'])
```

Both directions of the fold are pre-loaded (anchors read their inflow dependent; any inflow leg that surfaces in an account-scoped list reads its parent). `getAccountTransactions()` / `getCategoryTransactions()` keep showing every leg that belongs to the scope.

## 9. Edge cases

| Case | Behavior |
|---|---|
| Delete anchor | unit resolver finds all dependents → whole unit deleted |
| Delete inflow | pointer → anchor → whole unit deleted |
| Delete fee only | **not supported** — fee deletes are unit deletes; the recreated unit can omit the fee (`fee_amount = null`) |
| Edit fee via UI | fee row's edit action routes to the **parent transfer's** edit page — `PUT /transfers/{anchor}` covers the fee (`fee_amount` field) |
| Edit fee via API directly | `PUT /transactions/{fee}` locked-field update (§8.2); locked-field violations → 422 |
| `PUT /transfers/{id}` on non-anchor row | rejected by the anchor guard (§7.1) — inflow legs and fees are not transfer handles |
| `PUT /transactions/{id}` on anchor/inflow row | 422, pointing the client at `PUT /transfers/{anchor}` |
| Restore | no transaction restore route exists; out of scope (§12). If added: must restore at unit level |
| Ordering violation | impossible via the service (single transaction, out first); raw DB inserts with a dangling pointer are rejected by the FK |
| Hard-delete anchor with live dependents | rejected by `restrictOnDelete` — the orphaned-inflow failure mode is a DB error, not silent data corruption |
| Concurrency | unit deletes/edits run in `DB::transaction`; last-write-wins (acceptable for personal-finance volume) |

## 10. Code impact inventory

Migration (rename column, `restrictOnDelete`, index swap + `flow`), enums (new `TransactionFlow`, narrowed `TransactionType`), model (casts + `parentTransaction`/`dependentTransactions` relations), observer + `BalanceService` (flow-based; **observer `updated()` also fixed to reverse the old impact against the *original* account using `getOriginal('account_id')` / `getOriginal('flow')`** — pre-existing balance-corruption bug on account changes), routes (+ `transfers.store`/`transfers.update`), `TransferController` (new, thin), `TransactionController` (drops `isTransfer()` branch), `SaveTransactionRequest` (plain-only rules) + `SaveTransferRequest` (new), `TransactionData` (+flow, renamed pointer, real enum type) + `TransferData` (new), `TransactionService` (unit resolver, out-first transfer, per-endpoint update + fee locked-field edit, unit soft-delete, global-list leg filter), `TransactionListData::fromTransaction` (directional fold), `DashboardController` + `ReportService` + `SpendingService` (§5.6), `resolveKind`/frontend cleanup (§5.5 list), `TransactionFactory` (anchor-based states replace `transferOut`/`transferIn`), `DummyDataSeeder` (pairs + fee via anchor), test adaptations (§14).

## 11. Explicit behavior changes vs current code

Called out so none of this ships silently:

1. **Fee no longer survives unit deletion.** Today `softDelete()` intentionally leaves the fee ("the fee was really paid" — comment + test at `TransactionService.php:81-83`, `TransactionTest.php:113-124`). Under this spec the fee is deleted with the unit; editing the unit (delete + recreate) restores it if `fee_amount` is still present. The old comment and test are rewritten, not merely adapted.
2. **Dashboard and trend numbers change.** `monthly_income` / trend income stop including `transfer_in`; `monthly_expenses` / trend expense stop including `transfer_out`. Internal transfers between own accounts no longer inflate income/expense summaries (§5.6). Net worth is unaffected — balance math uses `flow`.
3. **Global transactions list shows one row per transfer** (the anchor leg) instead of both legs. Account-scoped lists still show their own leg. List counts and pagination change accordingly.
4. **Transfer creation moves to `POST /transfers`.** `POST /transactions` with `type = 'transfer'` (today's pseudo-type) is no longer accepted — the frontend's transfer tab posts to the new endpoint. Plain payloads lose `destination_account_id`/`fee_amount` acceptance.
5. **Transaction row ids churn on unit edits** (delete + recreate). Acceptable: no external references to leg ids exist (no restore route, no webhooks, frontend keys regenerate).
6. **Editing a plain row's account now balances correctly** (observer fix) where it previously corrupted both accounts' balances.

## 12. Open questions

- One deferred: unit-level restore behavior if a transaction restore route is ever added.
- Resolved 2026-09-11: column renamed to `transfer_parent_id` (churn is free pre-launch under the `migrate:fresh` strategy); endpoint split (plain vs transfer writes, unified delete) accepted.

## 13. Alternatives considered

Full schemas, relations, and the three-way comparison live in the decision record (`2026-09-10-transaction-transfer-linking-design.md`).

- **B — explicit `transfers` aggregate table** (rejected, kept as upgrade path): a first-class `transfers` row holding amount/fee/date/description with legs referencing it via `transfer_id`. Symmetric relations, free leg ordering, and it dissolves the fee-edit special case (fee becomes `fee_amount` on the aggregate). Rejected on change surface and YAGNI — none of its triggering features exist yet. **A → B is a mechanical upgrade** (the anchor row's facts lift into a `transfers` row; the parent pointer becomes `transfer_id`). Upgrade triggers: dedicated transfer detail pages, split transfers, cross-user transfers, recurring transfer presets — or friction with A's fee-edit rules in practice.
- **C — group key without FK** (ruled out): shared opaque key per unit, no FK. Strictly dominated by A — same flexibility, none of the integrity; its failure mode (orphan legs, silent key collisions) already occurred once in this codebase's history.

## 14. Rollout checklist

1. Modify original `create_transactions_table` migration: rename column → `transfer_parent_id`, `restrictOnDelete`, `flow` column, index swap (§5.1); `migrate:fresh`; reseed via `DummyDataSeeder`
2. `TransactionType` narrowed; new `TransactionFlow`; model casts + `parentTransaction`/`dependentTransactions` relations
3. Observer (flow-based multiplier, original-account/original-flow reversal fix) + `BalanceService` → flow-based
4. Routes: add `transfers.store` / `transfers.update`; new thin `TransferController`; `TransactionController` drops `isTransfer()` branch; anchor guard on `PUT /transfers`
5. `SaveTransactionRequest` (plain-only rules) + new `SaveTransferRequest` per the §7.2 matrix; `TransactionData` (+flow, renamed pointer, real enum) + new `TransferData`
6. `TransactionService` → unit resolver, out-first transfer, `updatePlain` (incl. fee locked-field edit + 422s), `updateTransfer` (delete + recreate), unit soft-delete (dependents first), global-list inflow-leg filter
7. `DashboardController`, `ReportService`, `SpendingService` → §5.6 semantics (type-based summaries; replace all raw type literals)
8. Delete `resolveKind`/`KIND_BY_TYPE`; frontend per §5.5 (`transaction-list-item`, `transaction-list-filter`, `transaction-detail`, `dashboard`, `accounts/show`, `transaction.schema.ts`, endpoint routing for create/edit forms)
9. `TransactionFactory` anchor-based states; `DummyDataSeeder` pairs + fee via anchor
10. `php artisan wayfinder:generate` + `composer generate:ts`
11. Tests: adapt transfer tests to `transfers.*` routes + parent-pointer semantics; **reverse** the fee-survival test (fee now deleted with unit); new tests — fee direct-edit locked fields (422 on locked changes), anchor guard rejections on both write endpoints, unit delete includes fee, global list hides inflow legs, dashboard/trend exclude transfer legs, observer account-change reversal
12. Pint; full suite
