# Transaction Transfer & Linking — Design Spec

- **Date:** 2026-09-10 (revised 2026-09-11 after code-verified review)
- **Status:** APPROVED — **Approach A (anchor pointer, amended)**; B retained as reference / upgrade path, C ruled out
- **Scope:** `transactions` table linking concept, transfer unit lifecycle, `type`/`flow` normalization, service + DTO + frontend fallout

---

## 1. Background

Transfers move money between two of a user's accounts and are booked as **two transaction rows** (outflow leg on the source, inflow leg on the destination), optionally plus a **fee row** on the source.

The linking mechanism has churned through three designs:

1. **UUID group key (deprecated).** Every row of a transfer shared a `Str::uuid()` value in `transfer_link_id`. No referential integrity, orphan legs on partial deletes, and the frontend docs described a column the schema didn't have.
2. **Sibling-pointer FK (previous schema).** `transfer_link_id` is `foreignId()->constrained('transactions', 'id')` — it must hold a *real transaction id*. The codebase drifted here while the service still wrote UUIDs, and the FK spent a whole session correctly rejecting every transfer insert.
3. **Mutual pointers (code at time of writing).** Outflow points at inflow, inflow points at outflow. Works, but requires a two-phase write (insert both legs, then patch the outflow), encodes no ordering, and cannot admit the fee row into the unit (its pointer slot is already spent on the sibling).

This spec settles the linking concept properly, adds a `type`/`flow` normalization, and defines unit lifecycle (create / edit / delete) in one place. The 2026-09-11 review verified every claim against the codebase and surfaced five decisions that are now settled in §4.

## 2. Goals

- A transfer is **one logical unit** — **one row per unit in the global transactions list** (the anchor, carrying the destination from its inflow leg); account-scoped lists show the leg belonging to that account. Edited as a whole, deleted as a whole.
- **Referential integrity enforced by the database**, not app discipline — on inserts (FK validity) *and* hard deletes (`restrictOnDelete` blocks anchor deletion while dependents exist).
- Unit membership includes the **optional fee**; the fee dies with the unit (explicit behavior change, §11).
- **Edit = delete + re-create** the unit through the standard edit endpoint; no leg-by-leg field sync. The unit's edit payload covers all three rows (anchor, inflow, fee). Direct fee-row edits are a locked-field exception (§6.5), not a unit recreate.
- Directional truth (`in`/`out`) becomes a structural column so balance math, sign icons, and cascade logic stop depending on enumerated type lists.

## 3. Non-goals

- Split / multi-leg transfers (single source → single destination only; designs below note extensibility).
- Cross-user transfers.
- Restoring deleted transfer legs (no restore route exists for transactions; if one is added later it must operate at unit level — see §12).
- Keeping the `transfer_link_id` column name — it is renamed to `transfer_parent_id` (§4), which is free while `migrate:fresh` is the data strategy.

## 4. Settled decisions (constraints for every approach)

| Decision | Value |
|---|---|
| `type` semantics | `income \| expense \| transfer` (3 cases) |
| `flow` semantics | `inflow \| outflow` — the row's effect sign on its account |
| Pairing rule | `income → inflow`, `expense → outflow`, `transfer → either` (validated in request/service, no DB check constraint per house rules) |
| Fee identity | `type = expense`, `flow = outflow`, booked on the source account, `transfer_parent_id` = anchor id (a non-null parent + `type = expense` is *the* fee-detection rule) |
| Unit edit | delete unit + re-create via the same endpoint; payload covers anchor + inflow + fee |
| Fee-row direct edit | locked-field in-place update (§6.5) — amount, category, description, date only; `account_id`/`type`/`flow`/pointer immutable via this path |
| Fee lifecycle | fee is deleted with the unit (reverses today's "fee survives" behavior — §11) |
| FK delete action | `restrictOnDelete()` — hard-deleting an anchor with live dependents is a DB error; service deletes dependents first, anchor last |
| List rendering | global list = one row per unit (anchor) + plain rows + fee rows; inflow legs hidden globally, visible on the destination account's list |
| Report semantics | **type-based**: dashboard/trend "income" = `type = income` only, "expense" = `type = expense` only; transfer legs excluded (behavior change — §11). Balance math stays flow-based |
| Plain row edit | direct field update (no unit involved) |
| Authorization | creator-based policy (unchanged); every unit member is booked by the same creator, so any unit cascade is creator-initiated |
| Fold | `TransactionListData` carries both ends (`destination_account_id`, `related_transaction`) |
| Column name | `transfer_parent_id` — every dependent points at its unit's anchor (the outflow row); NULL on the anchor itself |
| Observer | flow-based multiplier; fix the pre-existing account-change reversal bug (§6.6) |
| Data strategy | `migrate:fresh` + `DummyDataSeeder` reseed (dev data is disposable) |

## 5. Shared foundation — `type` / `flow` split (applies to A, B, C)

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
- The `'transfer'` pseudo-type accepted by `SaveTransactionRequest` — `transfer` becomes a real case (`Rule::enum(TransactionType::class)`)

### 5.4 Balance math (all approaches)

`BalanceService::forAccount()` CASE collapses from enumerated type lists to:

```sql
SUM(CASE WHEN t.flow = 'inflow' THEN t.amount ELSE -t.amount END)
```

`TransactionObserver::directionMultiplier()` becomes `flow === Inflow ? 1 : -1`. Adding new types later never touches balance code.

### 5.5 Frontend fallout (all approaches)

- Wayfinder enum regen (`TransactionType` 3 cases, new `TransactionFlow`) + `composer generate:ts`
- `transaction.schema.ts`: `resolveKind`/`KIND_BY_TYPE` deleted; `TransactionKind` union removed
- `transaction-list-item.svelte` / `transaction-list-filter.svelte`: `TYPE_STYLE` keyed on the 3 real types; sign icon + color derive from `flow`
- `transaction-detail.svelte`: `['income', 'transfer_in'].includes(type)` and `transfer_out`/`transfer_in` string checks → `flow`-based / 3-type checks
- `dashboard.svelte`: its **duplicated** local `TYPE_STYLE` + `resolveKind` usage → shared 3-type styling
- `accounts/show.svelte`: client-side inflow/outflow filters on type strings → `flow`-based
- `types/generated.d.ts`: `transfer_parent_id: number | null`; `type` narrows to 3 values; `flow` added

### 5.6 Report & summary semantics (decided: type-based)

All summary/report queries that mean "income" or "expense" filter on `type`, never on `flow`; only balance math uses `flow`.

| Consumer | Today | After |
|---|---|---|
| `DashboardController` monthly_income / monthly_expenses | `type IN (income, transfer_in)` / `type IN (expense, transfer_out)` via helper arrays | `type = income` / `type = expense` — **numbers change** (§11) |
| `ReportService::trend()` | raw literals `('income', 'transfer_in')` / `('expense')` | `type = income` / `type = expense` — literals must be rewritten, not left to drift |
| `ReportService::categorySpending()`, `fixedVsVariable()`, `SpendingService::globalCategorySpending()` | `whereIn('type', ['expense'])` literals | `TransactionType::Expense->value` — semantics unchanged (fee rows still count toward category spending, now by explicit decision) |
| `ReportService::contributionSplit()` | `type = 'income'` | unchanged semantics |

## 6. Approach A — Anchor pointer via `transfer_parent_id` (CHOSEN)

> The outflow leg is the **anchor** of the unit. Every other member points at it. Creation is out-first, so every insert is FK-valid in a single pass. Integrity is DB-enforced on both ends: FK validity on insert, `restrictOnDelete` on hard delete.

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

Fee detection rule: `transfer_parent_id !== NULL && type = expense`.

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

### 6.4 Service semantics

- **`createTransfer()`** (single `DB::transaction`):
  1. create anchor: `(transfer, outflow)`, `transfer_parent_id = NULL`
  2. create inflow: `(transfer, inflow)`, `transfer_parent_id = anchor.id`
  3. if fee: create `(expense, outflow)`, `transfer_parent_id = anchor.id`, category = Admin Fees ?? null
- **`update()`**: resolve the existing row's unit, then:
  - **Plain row** (NULL pointer, old payload non-transfer, new payload non-transfer) → direct field update, row id preserved.
  - **Unit member + transfer payload** → delete unit (dependents first, anchor last; members dispatch `TransactionDeleted`, observers reverse balances) → re-create the full unit from payload. **The payload covers all three rows** — `amount` (legs), `fee_amount` (fee), shared date/description — so editing the transfer from its anchor row edits the fee too.
  - **Fee row + plain expense payload** → locked-field in-place update (§6.5). Never a unit recreate.
  - **Type conversion** (plain → transfer or transfer → plain) → unit delete + re-create in the target shape.
- **`softDelete()`**: resolve unit → soft-delete every member (fee included, §11). Deleting any member kills the whole unit, symmetric. Delete order (dependents first, anchor last) is specified so a future force-delete path inherits FK-safe ordering for free — soft deletes don't fire the FK either way.
- **`getTransactions()`** (global list): anchors + plain rows + fee rows — hide inflow legs:

  ```php
  ->whereNot(fn ($q) => $q->where('type', 'transfer')->where('flow', 'inflow'))
  ->with(['account', 'category', 'dependentTransactions.account', 'parentTransaction.account'])
  ```

  Both directions of the fold are pre-loaded (anchors read their inflow dependent; any inflow leg that surfaces in an account-scoped list reads its parent). `getAccountTransactions()` / `getCategoryTransactions()` keep showing every leg that belongs to the scope.

### 6.5 Edge cases

| Case | Behavior |
|---|---|
| Delete anchor | unit resolver finds all dependents → whole unit deleted |
| Delete inflow | pointer → anchor → whole unit deleted |
| Delete fee only | **not supported** — fee deletes are unit deletes; the recreated unit can omit the fee (`fee_amount = null`) |
| **Edit fee via UI** | fee row's edit action routes to the **parent transfer's** edit form — the unit payload covers the fee (`fee_amount` field) |
| **Edit fee via API directly** | locked-field in-place update: `amount`, `category_id`, `description`, `transaction_date` only. Attempts to change `account_id`, `type`, or send `destination_account_id` → 422. Never a unit recreate (this closes the data-loss path where a fee edit destroyed both legs) |
| Restore | no transaction restore route exists; out of scope (§12). If added: must restore at unit level |
| Ordering violation | impossible via the service (single transaction, out first); raw DB inserts with a dangling pointer are rejected by the FK |
| Hard-delete anchor with live dependents | rejected by `restrictOnDelete` — the orphaned-inflow failure mode is now a DB error, not silent data corruption |
| Concurrency | unit deletes/edits run in `DB::transaction`; last-write-wins (acceptable for personal-finance volume) |
| `TransactionData` internals | gains `flow: TransactionFlow` and `?int $transfer_parent_id` (anchor id) — never client-sent |

### 6.6 Code impact inventory

Migration (rename column, `restrictOnDelete`, index swap + `flow`), enums (new `TransactionFlow`, narrowed `TransactionType`), model (casts + `parentTransaction`/`dependentTransactions` relations), observer + `BalanceService` (flow-based; **observer `updated()` also fixed to reverse the old impact against the *original* account using `getOriginal('account_id')` / `getOriginal('flow')`** — pre-existing balance-corruption bug on account changes), `TransactionService` (unit resolver, out-first transfer, delete-and-recreate update, fee locked-field edit, unit soft-delete, global-list leg filter), `SaveTransactionRequest` (enum rule, flow never accepted, locked-field 422s), `TransactionData` (+flow, renamed pointer), `TransactionListData::fromTransaction` (directional fold), `DashboardController` + `ReportService` + `SpendingService` (§5.6), `resolveKind`/frontend cleanup (§5.5 list), `TransactionFactory` (anchor-based states replace `transferOut`/`transferIn`), `DummyDataSeeder` (pairs + fee via anchor), test adaptations (§12).

### 6.7 Pros / cons

| ✅ Pros | ❌ Cons |
|---|---|
| Small schema surface — one FK + index, now honestly named | Out-first insert ordering and dependents-first delete ordering are service discipline; the DB enforces the *consequences* (FK validity, no orphaning), not the order |
| One-pass FK-valid inserts — no post-insert patching | Anchor → counterpart needs an indexed reverse lookup with a flow filter |
| One unit rule covers fold, edit, and delete symmetrically | The unit is implicit in data — no first-class "transfer" object |
| Fee joins the unit via the same pointer rule; fee edit has a safe dedicated rule | Fee edit is a special case (two layers: unit payload + locked-field API path) that B would not need |
| Integrity holds on both insert and hard delete | |
| Generalizes informally if extra dependents are ever added | |

---

## 7. Approach B — Explicit `transfers` aggregate table (reference / upgrade path)

> The transfer becomes a first-class entity. Transactions reference it; shared facts live once on the aggregate. There is **no leg-to-leg pointer at all** — legs relate to each other *through* the `Transfer` row.

Retained unchosen for reference. Notable review finding: **B dissolves the fee-edit special case entirely** — the fee is `fee_amount` on the aggregate, edited as part of one shape, with no standalone fee row to mis-edit. If A's fee rules (§6.5) ever grate in practice, that is the concrete signal to execute the A→B upgrade.

### 7.1 Schema

```php
Schema::create('transfers', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
    $table->decimal('amount', 15, 2);
    $table->decimal('fee_amount', 15, 2)->nullable();
    $table->date('transaction_date');                 // date, not timestamp (house rule)
    $table->string('description')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

// transactions table:
- transfer_link_id dropped entirely
+ $table->foreignId('transfer_id')->nullable()->constrained('transfers')->cascadeOnDelete();
+ $table->index(['transfer_id', 'flow']);
```

- Only the two movement legs (and the fee, if present) carry `transfer_id`; income/expense rows have NULL.
- Legs keep their own `amount` (balance math stays per-row); the aggregate's `amount` / `fee_amount` / `transaction_date` / `description` are the authoritative shared copies, kept consistent *by* the delete-and-recreate edit rule.
- Data migration: none needed — `migrate:fresh` + reseed.

### 7.2 Unit resolution

`WHERE transfer_id = X` — direct, indexed, symmetric. The unit = the `Transfer` row + its legs. Leg creation order is irrelevant.

### 7.3 Transfer ↔ transaction relation

```php
// App\Models\Transfer
public function legs(): HasMany            // Transaction::class — all unit rows
public function outflowLeg(): HasOne      // hasOne(...)->ofMany(...) constrained to flow = outflow
public function inflowLeg(): HasOne       // constrained to flow = inflow
public function feeLeg(): HasOne          // constrained to type = expense — may not exist
public function creator(): BelongsTo

// App\Models\Transaction
public function transfer(): BelongsTo     // transfer_id -> transfers.id
```

- `relatedTransaction()` resolves symmetrically through the aggregate: from either leg, the counterpart is `transfer → inflowLeg` (or `outflowLeg`). No anchor concept, no direction special-casing.
- Fold: eager load `['transfer.legs.account', 'account', 'category']`; `destination_account_id` = opposite-flow leg's `account_id`; fee rows map `destination_account_id = null`.
- Unit-level authorization: `Transfer` carries `created_by`, so a policy can check the aggregate once instead of each leg.

### 7.4 Service semantics

- **`createTransfer()`**: create the `Transfer` aggregate → create outflow leg → inflow leg → fee leg (each referencing `transfer_id`). Free order; every insert FK-valid.
- **`update()` on a unit**: update the aggregate from the payload → delete-and-recreate its legs. Plain rows: direct field update.
- **`softDelete()`**: resolve the aggregate → soft-delete it **and** its legs explicitly in one transaction. The FK `cascadeOnDelete` only fires on hard deletes — the soft-delete path is app-enforced (B's main discipline cost).

### 7.5 Edge cases

| Case | Behavior |
|---|---|
| Delete the aggregate | legs soft-deleted explicitly alongside (FK cascade covers hard deletes only) |
| Delete one leg | resolves via `transfer_id` → whole unit deleted |
| Fee removal on edit | re-create legs without a fee row; aggregate `fee_amount` set to null — natural, single shape |
| Restore | aggregate row keys the unit — unit-level restore is cleaner than A (still deferred, §12) |
| Aggregate/leg drift | shared facts exist on both; delete-and-recreate keeps them consistent — direct leg-edit paths must not exist |
| Concurrency | same as A — transactional, last-write-wins |

### 7.6 Pros / cons

| ✅ Pros | ❌ Cons |
|---|---|
| First-class, queryable transfer object (detail pages, recurring transfers, split legs later) | Largest change surface: new table/model/migration, pointer column removed |
| Symmetric relations — no anchor concept, no directional fold logic | Shared facts stored on aggregate **and** legs; consistency guarded only by the recreate rule |
| Free leg ordering; every insert FK-valid | Soft-delete lifecycle fully app-enforced (FK cascade won't do it) |
| Unit-level auth via `Transfer.created_by` | Two tables to migrate, seed, factory, and test |
| Fee is a field on the aggregate — no fee-row edit edge case at all | More machinery than today's single-shape transfer needs (YAGNI pressure) |
| Fold reads the aggregate directly — no reverse queries | |

---

## 8. Approach C — Group key without FK (previous iteration, corrected)

> Every unit member carries the same opaque shared key; the column is a plain indexed string with **no FK**. Membership is purely "same key".

### 8.1 Schema

```php
$table->string('transfer_link_id')->nullable();
$table->index('transfer_link_id');
```

`Str::uuid()` generated per unit by the service — the historical design, restored verbatim.

### 8.2 Unit resolution

`WHERE transfer_link_id = ?` — symmetric, order-free, N-leg capable. NULL = not part of a unit.

### 8.3 Transfer ↔ transaction relation

No FK-backed relation — legs are peers matched by key (see historical `relatedTransaction` peer query filtered by opposite flow). Nothing guarantees the matched row exists, is unique, or belongs to the same user. The fee row joins by carrying the same key and must be filtered out of the peer query by flow.

### 8.4 Edge cases

| Case | Behavior |
|---|---|
| Delete one leg | cascade is app-side only — any missed code path permanently orphans the remaining legs |
| Key typo / reuse | silently merges two units, or yields NULL relations — undetectable by the DB |
| Key validity | untyped string; nothing distinguishes a valid key from garbage |
| Restore | key survives soft-delete, but re-linking semantics are app-side |
| Integrity | ❌ none — this is the defect class this spec supersedes |

### 8.5 Pros / cons

| ✅ Pros | ❌ Cons |
|---|---|
| Simplest mental model — one shared key, peers only | No referential integrity: orphans, garbage keys, silent NULL relations |
| Order-free inserts; native N-leg groups | Cascade is 100% app-side discipline |
| Smallest diff from the historical code | Key collision/reuse merges units undetectably |
| No ordering constraint anywhere | No aggregate object for future transfer features |
| | This failure mode already occurred once (the FK chaos this spec replaces) |

**Verdict**: strictly dominated by A (same flexibility, none of the integrity). Ruled out.

---

## 9. Comparison

| | A — anchor pointer ✅ | B — transfers table | C — group key |
|---|---|---|---|
| Schema change | rename column + `restrictOnDelete`, index swap | new table, FK swap | drop FK, widen column |
| Transfer ↔ transaction relation | `parentTransaction()` BelongsTo + `dependentTransactions()` HasMany | `Transaction::transfer()` BelongsTo + `Transfer::legs()` HasMany | peer `hasOne` matched by shared key, no FK |
| Insert | 1-pass, ordered (out first) | free order | free order |
| Unit resolution | pointer rule (2 relations) | `transfer_id` lookup | shared-key query |
| Ref. integrity | ✅ FK on insert + `restrict` on hard delete | ✅ FK (cascade on hard delete; soft path app-enforced) | ❌ none |
| Fee in unit | ✅ (points at anchor; locked-field direct-edit rule) | ✅ (aggregate field — no edge case) | ✅ |
| Fee edit | unit payload + locked-field API path | single aggregate shape | key query |
| Edit (delete+recreate) | resolve via pointer rule | update aggregate + recreate legs | key query |
| Fold resolution | directional, pre-loaded both ways | direct from aggregate | key query |
| List rendering (1 row/unit) | filter inflow legs in `getTransactions()` | filter non-anchor legs via aggregate | key query |
| First-class transfer object | ❌ implicit | ✅ explicit | ❌ implicit |
| Multi-leg future | informal generalization | first-class | native but unsafe |
| Change surface | small | large | tiny but regressive |

## 10. Decision

**Approach A — anchor pointer via `transfer_parent_id`** — approved 2026-09-11, amended after review with: `restrictOnDelete`, fee-in-unit lifecycle (fee dies with unit), two-layer fee-edit rule, type-based report semantics, one-row-per-unit list rendering, observer account-change fix.

It satisfies every stated requirement (destination pointer, out-first, fee-in-unit, delete-and-recreate, creator-cascade safety, DB-enforced integrity on insert *and* hard delete) with the smallest surface, and degrades gracefully — if transfers later need first-class identity, **A → B is a mechanical upgrade** (the anchor row's facts lift into a `transfers` row; the parent pointer becomes `transfer_id`).

**B's trigger conditions** (any one of these near-term → upgrade): dedicated transfer detail pages, split transfers, cross-user transfers, recurring transfer presets — or friction with A's fee-edit rules in practice.

**C** is ruled out under any near-term scenario.

> **Revision 2026-09-11 (later, pre-implementation):** decision reversed to **Approach B**. Domain-pattern analysis showed B's parent-table shape is where accounting software converges (QuickBooks Online's Transfer entity, double-entry journal + lines, Firefly III's journals), and A had accumulated special cases (directional fold, anchor guards, two-layer fee-edit rule) that all trace to the anchor row's dual role — B separates the roles and dissolves them. A was **never implemented**, so the revision cost was zero. Implementation spec: `2026-09-11-transfer-aggregate-design.md`.

## 11. Explicit behavior changes vs current code

Called out so none of this ships silently:

1. **Fee no longer survives unit deletion.** Today `softDelete()` intentionally leaves the fee ("the fee was really paid" — comment + test at `TransactionService.php:81-83`, `TransactionTest.php:113-124`). Under this spec the fee is deleted with the unit; editing the unit (delete + recreate) restores it if `fee_amount` is still present. The old comment and test are rewritten, not merely adapted.
2. **Dashboard and trend numbers change.** `monthly_income` / trend income stop including `transfer_in`; `monthly_expenses` / trend expense stop including `transfer_out`. Internal transfers between own accounts no longer inflate income/expense summaries (§5.6). Net worth is unaffected — balance math uses `flow`.
3. **Global transactions list shows one row per transfer** (the anchor leg) instead of both legs. Account-scoped lists still show their own leg. List counts and pagination change accordingly.
4. **Transaction row ids churn on unit edits** (delete + recreate). Acceptable: no external references to leg ids exist (no restore route, no webhooks, frontend keys regenerate).
5. **Editing a plain row's account now balances correctly** (observer fix) where it previously corrupted both accounts' balances.

## 12. Open questions

- One deferred (not approach-dependent): unit-level restore behavior if a transaction restore route is ever added.
- Resolved 2026-09-11: column renamed to `transfer_parent_id` (was kept as `transfer_link_id` for churn reasons; review established churn is free pre-launch).
- Revised 2026-09-11 (later): `transfer_parent_id` is moot — with the reversal to Approach B, the linking column is `transfer_id` referencing the `transfers` aggregate.

## 13. Rollout checklist (post-pick)

1. Modify original `create_transactions_table` migration: rename column → `transfer_parent_id`, `restrictOnDelete`, `flow` column, index swap (§5.1); `migrate:fresh`; reseed via `DummyDataSeeder`
2. `TransactionType` narrowed; new `TransactionFlow`; model casts + `parentTransaction`/`dependentTransactions` relations
3. Observer (flow-based multiplier, original-account/original-flow reversal fix) + `BalanceService` → flow-based
4. `TransactionService` → unit resolver, out-first transfer, delete-and-recreate update, fee locked-field edit + 422s, unit soft-delete (dependents first), global-list inflow-leg filter
5. `SaveTransactionRequest` (enum rule, drop pseudo-type, locked-field validation), `TransactionData` (+flow, renamed pointer), `TransactionListData` fold adjustments
6. `DashboardController`, `ReportService`, `SpendingService` → §5.6 semantics (type-based summaries; replace all raw type literals)
7. Delete `resolveKind`/`KIND_BY_TYPE`; frontend per §5.5 (`transaction-list-item`, `transaction-list-filter`, `transaction-detail`, `dashboard`, `accounts/show`, `transaction.schema.ts`)
8. `TransactionFactory` anchor-based states; `DummyDataSeeder` pairs + fee via anchor
9. `php artisan wayfinder:generate` + `composer generate:ts`
10. Tests: adapt transfer-pair tests to parent-pointer semantics; **reverse** the fee-survival test (fee now deleted with unit); new tests — fee direct-edit locked fields (422 on locked changes), unit delete includes fee, global list hides inflow legs, dashboard/trend exclude transfer legs, observer account-change reversal
11. Pint; full suite
