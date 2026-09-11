# Transaction Transfer & Linking — Implementation Spec (Approach B: Transfer Aggregate)

- **Date:** 2026-09-11 (revised same day — relation naming settled: `transactions`, source/destination/fee accessors)
- **Status:** APPROVED — ready for implementation planning
- **Decision record:** `2026-09-10-transaction-transfer-linking-design.md` (approach comparison A/B/C, review findings, full rationale)
- **Supersedes:** `2026-09-11-transaction-transfer-anchor-pointer-design.md` (Approach A — approved but **never implemented**; revised to B after domain-pattern analysis)
- **Scope:** `transfers` aggregate + `transactions` member rows, transfer unit lifecycle, `type`/`flow` normalization, API surface, service + DTO + frontend fallout

---

## 1. Background

Transfers move money between two of a user's accounts and are booked as **two transaction rows** (outflow row on the source, inflow row on the destination), optionally plus a **fee row** on the source.

The linking mechanism churned through three designs before this spec:

1. **UUID group key (deprecated).** Shared `Str::uuid()` per unit. No referential integrity, orphan rows on partial deletes.
2. **Sibling-pointer FK (interim schema).** `transfer_link_id` became a self-FK requiring a real transaction id while the service still wrote UUIDs — the FK correctly rejected every transfer insert until the drift was found.
3. **Mutual pointers (code at time of writing).** Outflow and inflow point at each other. Works, but needs a two-phase write, encodes no ordering, and cannot admit the fee row into the unit.

**Approach history:** the 2026-09-10 decision record initially selected **A (anchor pointer)**. Before any implementation, domain-pattern analysis reversed this to **B** — the parent-table shape is where accounting software converges (QuickBooks Online's Transfer entity, double-entry journal + lines, Firefly III's journals), A had accumulated fee-edit special-casing that B dissolves structurally, and the switch was free pre-implementation. A was never built; this spec starts from the current mutual-pointer codebase directly.

This is the **transfer-specific parent** form of the industry pattern — not full double-entry. Making income/expense journaled as well (the "universal journal" rung) is explicitly out of scope (§3, §13).

## 2. Goals

- A transfer is **one logical unit** — a first-class `Transfer` row with member transactions referencing it. **One row per unit in the global transactions list** (the outflow row, carrying the destination from its inflow counterpart); account-scoped lists show the row belonging to that account. Edited as a whole, deleted as a whole.
- **Referential integrity enforced by the database** — member rows carry an FK to a real `transfers` row; hard-deleting the aggregate cascades its member transactions.
- Unit membership includes the **optional fee** (a member row) and the fee's amount (a field on the aggregate); the fee dies with the unit (explicit behavior change, §11).
- **Edit = update the aggregate + delete-and-recreate its member transactions** through `PUT /transfers/{transfer}`; no row-by-row field sync, no direct unit-member edit paths. The unit's identity (`transfers.id`) is stable across edits.
- **Split write endpoints for plain vs transfer shapes** — unconditional validation rules per endpoint, no `type`-conditional matrix.
- Directional truth (`in`/`out`) becomes a structural column so balance math, sign icons, and cascade logic stop depending on enumerated type lists.

## 3. Non-goals

- Split / multi-row transfers (single source → single destination only; the aggregate shape extends naturally if ever needed).
- Cross-user transfers.
- Restoring deleted transfer rows (no restore route exists; if one is added later it must operate at unit level, keyed off the aggregate — §12).
- **Universal journal / full double-entry** — income/expense stay single-row; only transfers get the parent shape. The upgrade rung beyond B, not a near-term candidate.
- **Atomic type conversion over HTTP** (plain ↔ transfer in one request) — the edit UI fixes the type; conversion is delete + create via the appropriate endpoint.
- Data migration from the current dev schema — `migrate:fresh` + reseed is the strategy (dev data is disposable).

## 4. Settled decisions

| Decision | Value |
|---|---|
| `type` semantics | `income \| expense \| transfer` (3 cases) |
| `flow` semantics | `inflow \| outflow` — the row's effect sign on its account |
| Pairing rule | `income → inflow`, `expense → outflow`, `transfer → either` — **derived server-side in the service, never request validation** (transfer payloads carry no `type`; plain payload `type` implies its `flow`) |
| Linking | `transactions.transfer_id` FK → `transfers.id`, `cascadeOnDelete()` — no row-to-row pointer, no self-FK |
| Aggregate | first-class `Transfer` model: `amount`, `fee_amount`, `transaction_date`, `description`, `created_by`, soft deletes |
| Fee identity | a **member row** (`type = expense`, `flow = outflow`, on the source account, `transfer_id` set) **plus** `fee_amount` on the aggregate (the authoritative shared copy). **Fee detection rule:** `transfer_id !== NULL && type = expense` |
| Relations | `Transfer::transactions()` (all member rows), `sourceTransaction()` / `destinationTransaction()` / `feeTransaction()` (one-of-many accessors), `creator()`; `Transaction::transfer()` |
| Authority rule | shared facts (amount, fee, date, description) exist on the aggregate **and** the member rows; the aggregate is authoritative; consistency is maintained by the update-aggregate-then-recreate-rows rule — **direct unit-member edit paths must not exist** (API-enforced, §7) |
| Unit edit | `PUT /transfers/{transfer}`: update aggregate from payload → soft-delete member transactions → re-create them; `transfers.id` stable |
| Unit-member direct edit | **rejected** — `PUT /transactions/{id}` on any row with `transfer_id` → 422 "edit the transfer instead" (desyncs the aggregate otherwise) |
| Fee lifecycle | fee row is deleted with the unit (reverses today's "fee survives" behavior — §11) |
| Soft-delete discipline | app-enforced: `softDelete()` soft-deletes the aggregate **and** all member transactions explicitly in one `DB::transaction` — the FK cascade covers hard deletes only (B's main discipline cost; a named test case) |
| List rendering | global list = one row per unit (outflow row) + plain rows + fee rows; inflow rows hidden globally, visible on the destination account's list |
| Report semantics | **type-based**: dashboard/trend "income" = `type = income` only, "expense" = `type = expense` only; transfer rows excluded (behavior change — §11). Balance math stays flow-based |
| Plain row edit | `PUT /transactions/{id}`: direct field update (no unit involved) |
| Service ownership | `TransferService` owns the unit lifecycle — `create(User, TransferData)`, `update(Transfer, TransferData)`, `deleteUnit(Transaction)`, fee/Admin-Fees resolution included. `TransactionService` owns plain-row CRUD + list queries. `TransactionController::destroy` routes unit members to `TransferService::deleteUnit` (controller-level branch avoids circular service injection) |
| Frontend organization | transfer UI is part of the **transactions** namespace: create = tab on `transactions/create`, unit edit = `pages/transactions/edit-transfer.svelte` (form in `components/module/transaction/`). No `transfers` pages/module directory. Route URLs remain `/transfers/*` |
| **Endpoint split** | `POST/PUT /transactions` for plain rows; `POST/PUT /transfers` for transfer units (§7). `GET` index/show and `DELETE /transactions/{id}` stay unified — unit resolution in the service |
| **`type` in payloads** | absent from transfer payloads (the endpoint implies it); plain payloads carry `income \| expense` only |
| Authorization | transaction policy unchanged (creator-based); new `TransferPolicy` (`created_by` check) for the transfer endpoints; every member row shares the aggregate's creator, so any unit cascade is creator-initiated |
| Fold | `TransactionListData` carries both ends (`destination_account_id`, `related_transaction`); resolves symmetrically through the aggregate |
| Observer | flow-based multiplier; fix the pre-existing account-change reversal bug (§10) |
| Data strategy | `migrate:fresh` + `DummyDataSeeder` reseed |

## 5. Foundation — `type` / `flow` split

### 5.1 Schema (transactions table)

```php
$table->string('type');                              // TransactionType: income | expense | transfer
$table->string('flow');                              // TransactionFlow: inflow | outflow
$table->index(['account_id', 'transaction_date']);   // replaces [account_id, type, transaction_date]
$table->index(['account_id', 'flow']);               // balance aggregates
```

Note: type-filtered report queries (`type = expense` + date range) lose their dedicated composite index and ride `[account_id, transaction_date]` with a post-index filter — acceptable at personal-finance per-account row counts; revisit only if report queries surface in slow-query logs.

While rewriting the original migration, `transactions.transaction_date` normalizes from `timestamp` to `date` per house rules.

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
- `transfer_link_id` and its self-FK, `relatedTransaction()` as a self-referential relation

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
- `accounts/show.svelte`: client-side inflow/outflow filters on type strings → type-based
- `types/generated.d.ts`: `transfer_id: number | null` (replaces `transfer_link_id`); `type` narrows to 3 values; `flow` added
- **Endpoint routing:** create form's active tab selects the submit URL (`TransactionsController.store.url()` vs `TransfersController.store.url()`); transfer edits post to `TransfersController.update.url({ transfer })` from `pages/transactions/edit-transfer.svelte`; a fee row's edit action links to the **parent transfer's** edit page (§9)

### 5.6 Report & summary semantics (decided: type-based)

All summary/report queries that mean "income" or "expense" filter on `type`, never on `flow`; only balance math uses `flow`.

| Consumer | Today | After |
|---|---|---|
| `DashboardController` monthly_income / monthly_expenses | `type IN (income, transfer_in)` / `type IN (expense, transfer_out)` via helper arrays | `type = income` / `type = expense` — **numbers change** (§11) |
| `ReportService::trend()` | raw literals `('income', 'transfer_in')` / `('expense')` | `type = income` / `type = expense` — literals must be rewritten, not left to drift |
| `ReportService::categorySpending()`, `fixedVsVariable()`, `SpendingService::globalCategorySpending()` | `whereIn('type', ['expense'])` literals | `TransactionType::Expense->value` — semantics unchanged (fee rows still count toward category spending, now by explicit decision) |
| `ReportService::contributionSplit()` | `type = 'income'` | unchanged semantics |

## 6. Data model — `transfers` aggregate

### 6.1 Schema

```php
Schema::create('transfers', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
    $table->decimal('amount', 15, 2);
    $table->decimal('fee_amount', 15, 2)->nullable();
    $table->date('transaction_date');
    $table->string('description')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index('created_by');
});

// transactions table:
$table->foreignId('transfer_id')->nullable()->constrained('transfers')->cascadeOnDelete();
$table->index(['transfer_id', 'flow']);
```

- `transfer_link_id` (and its FK) is **dropped entirely** — the DB relationship becomes transaction → **transfer**, not transaction → transaction.
- Only unit members carry `transfer_id`; plain rows are NULL.
- Member transactions keep their own `amount` (balance math stays per-row); the aggregate's `amount` / `fee_amount` / `transaction_date` / `description` are the authoritative shared copies, kept consistent by the recreate rule (§4 authority rule).
- Hard-deleting a `Transfer` cascades hard-deletes of its member transactions — unit semantics at the DB level. Soft deletes are app-enforced (§8.3).

### 6.2 Unit resolution

`WHERE transfer_id = X` — direct, indexed, symmetric. The unit = the `Transfer` row + its member transactions. Row creation order is irrelevant; every insert is FK-valid as long as the aggregate exists first.

**Member invariants** (what each transaction row may be):

| Row shape | `type` | `flow` | `transfer_id` |
|---|---|---|---|
| Outflow row (source) | `transfer` | `outflow` | transfer id |
| Inflow row (destination) | `transfer` | `inflow` | transfer id |
| Fee row | `expense` | `outflow` | transfer id |
| Plain row | `income`/`expense` | matching flow | NULL |

### 6.3 Model relations

```php
// App\Models\Transfer
public function transactions(): HasMany          // Transaction::class — all unit rows
public function sourceTransaction(): HasOne      // constrained to (transfer, outflow) — on the source account
public function destinationTransaction(): HasOne // constrained to (transfer, inflow) — on the destination account
public function feeTransaction(): HasOne         // constrained to type = expense — may not exist
public function creator(): BelongsTo

// App\Models\Transaction
public function transfer(): BelongsTo            // transfer_id -> transfers.id
```

`relatedTransaction()` (the fold's convenience) resolves **symmetrically through the aggregate**: from either movement row, the counterpart is the opposite-flow member among `transfer->transactions`. The fee row resolves to nothing for the fold — `destination_account_id` is NULL on fee rows.

## 7. API surface — routes & validation

### 7.1 Routes

| Route | Name | Purpose |
|---|---|---|
| `POST /transactions` | `transactions.store` | create a plain row (`income`/`expense`) |
| `PUT /transactions/{transaction}` | `transactions.update` | edit a plain row — **rejects any row with `transfer_id`** (422) |
| `POST /transfers` | `transfers.store` | create a transfer unit (aggregate + member rows + optional fee) |
| `PUT /transfers/{transfer}` | `transfers.update` | edit a transfer unit — **route-model-binds a real `Transfer`**; no anchor-guard machinery |
| `GET /transactions`, `GET /transactions/{transaction}` | `transactions.*` | unified — lists fold units; show works on any row |
| `DELETE /transactions/{transaction}` | `transactions.destroy` | unified — resolves the row's `transfer_id`, deletes the whole unit (§8.3) |

Controllers: a new thin `TransferController` (`store`, `edit`, `update`) delegating to `TransferService`, authorized by a new `TransferPolicy` (`created_by` check). `TransactionController` keeps everything else, loses its `isTransfer()` dispatch branch, and its `destroy()` routes unit members to `TransferService::deleteUnit`.

Because the update endpoints bind real models, the A-spec's anchor guards are unnecessary: `PUT /transfers/{transfer}` cannot receive a transaction id at all, and `PUT /transactions/{transaction}` rejects unit members with a 422 pointing the client at the transfer endpoint — which also enforces the authority rule (no direct unit-member edits, no aggregate desync).

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

No `Rule::requiredIf` anywhere — shape is known from the URL.

### 7.3 Write DTOs

- `TransactionData` (plain): `account_id`, `type: TransactionType` (real enum — the raw-string workaround dies), `amount`, `transaction_date`, `category_id`, `description`. Internally gains `flow: TransactionFlow` — never client-sent.
- `TransferData` (unit): `account_id` (source), `destination_account_id`, `amount`, `fee_amount`, `transaction_date`, `description`. No `type`, no `flow`. Maps naturally onto the `Transfer` model (minus the per-row account facts).

## 8. Service semantics

Two services, one direction of dependency: `TransferService` injects `TransactionService` (it books member rows through it); never the reverse.

### 8.1 `TransferService::create(User, TransferData)` (single `DB::transaction`)

1. create the `Transfer` aggregate: `amount`, `fee_amount`, `transaction_date`, `description`, `created_by`
2. create source row: `(transfer, outflow)` on the source account, `transfer_id` set, `amount` = aggregate amount
3. create destination row: `(transfer, inflow)` on the destination account, `transfer_id` set, `amount` = aggregate amount
4. if `fee_amount`: create fee row: `(expense, outflow)` on the source account, `transfer_id` set, category = Admin Fees ?? null

Free row order after the aggregate exists; every insert FK-valid.

### 8.2 `update()` — per-endpoint semantics

**`updatePlain(Transaction, TransactionData)`** (via `PUT /transactions/{id}`):
- Row must be plain (`transfer_id === NULL`) — unit members are rejected by the endpoint (§7.1)
- Direct field update, row id preserved
- Type flips are rejected on this endpoint (§3) — conversion is delete + create via the right endpoint

**`TransferService::update(Transfer, TransferData)`** (via `PUT /transfers/{transfer}`):
- Update the aggregate from the payload (`amount`, `fee_amount`, `transaction_date`, `description`)
- Soft-delete the existing member transactions (they dispatch `TransactionDeleted`; observers reverse balances) → re-create them from the aggregate (same steps as §8.1). **The payload covers all three rows** — editing the transfer edits the fee too
- `transfers.id` is stable across edits — the unit's identity survives

### 8.3 `TransferService::deleteUnit(Transaction)` — unified delete, app-enforced discipline

Invoked from `DELETE /transactions/{transaction}` (the controller branches: unit member → `deleteUnit`, plain → `TransactionService::softDelete`). From any member (movement or fee row): resolve `transfer_id` → soft-delete the aggregate **and** every member transaction explicitly, in one `DB::transaction`. The FK cascade only fires on hard deletes — the soft-delete path is app code, and this is B's main discipline cost: **a named test case asserts aggregate + all member rows trash together** (and only together).

### 8.4 List queries

**`getTransactions()`** (global list): outflow rows + plain rows + fee rows — hide inflow rows:

```php
->whereNot(fn ($q) => $q->where('type', 'transfer')->where('flow', 'inflow'))
->with(['account', 'category', 'transfer.transactions.account'])
```

`getAccountTransactions()` / `getCategoryTransactions()` keep showing every row that belongs to the scope, with the same eager load for the fold.

`TransactionListData::fromTransaction()`: `destination_account_id` = the opposite-flow member's `account_id` (from pre-loaded `transfer.transactions`); fee rows map to NULL. Output shape is unchanged from today's contract — only the resolution mechanics differ.

## 9. Edge cases

| Case | Behavior |
|---|---|
| Delete the aggregate | member transactions soft-deleted alongside in the same transaction (§8.3); hard delete cascades via FK |
| Delete one member row | `DELETE /transactions/{row}` resolves `transfer_id` → whole unit deleted |
| Delete fee only | **not supported** — fee deletes are unit deletes; the recreated unit can omit the fee (`fee_amount = null`) |
| Edit fee via UI | fee row's edit action routes to the **parent transfer's** edit page — `PUT /transfers/{transfer}` covers the fee (`fee_amount` field) |
| Edit unit member via API directly | `PUT /transactions/{any member row}` → **422 "edit the transfer instead"** — direct member edits desync the aggregate (authority rule, §4) |
| Aggregate/member drift | prevented by construction: the aggregate is the only edit surface; no direct member-edit paths exist |
| Restore | no transaction restore route exists; out of scope (§12). If added: key off the aggregate — unit-level restore is natural under B |
| Row ordering | free order once the aggregate exists; nothing to violate |
| Hard-delete the aggregate | FK cascades member transactions — no orphans possible at the DB level |
| Concurrency | unit deletes/edits run in `DB::transaction`; last-write-wins (acceptable for personal-finance volume) |
| `TransactionData` internals | plain DTO gains `flow: TransactionFlow` — never client-sent; `TransferData` carries no type/flow/pointer at all |

## 10. Code impact inventory

Migrations (new `transfers` table; transactions: drop `transfer_link_id` + self-FK, add `transfer_id` FK `cascadeOnDelete` + composite index, `flow` column, index swap, `transaction_date` → `date`), enums (new `TransactionFlow`, narrowed `TransactionType`), models (`Transfer` new incl. `transactions`/`sourceTransaction`/`destinationTransaction`/`feeTransaction`/`creator`; `Transaction`: casts + `transfer()` relation, `relatedTransaction()` self-relation deleted), observer + `BalanceService` (flow-based; **observer `updated()` also fixed to reverse the old impact against the *original* account using `getOriginal('account_id')` / `getOriginal('flow')`** — pre-existing balance-corruption bug), routes (+ `transfers.store`/`transfers.edit`/`transfers.update`), `TransferController` + `TransferPolicy` (new, thin), `TransactionController` (drops `isTransfer()` branch; update rejects unit members), `SaveTransactionRequest` (plain-only rules) + `SaveTransferRequest` (new), `TransactionData` (+flow, real enum) + `TransferData` (new), `TransferService` (new — unit lifecycle: aggregate-first create, update = update aggregate + recreate member transactions, `deleteUnit` soft-delete discipline, fee/Admin-Fees resolution), `TransactionService` (plain-row CRUD, list queries incl. global-list inflow-row filter), `TransactionController::destroy` unit/plain dispatch, `TransactionListData::fromTransaction` (aggregate-based fold), `DashboardController` + `ReportService` + `SpendingService` (§5.6), `resolveKind`/frontend cleanup (§5.5 list), `TransactionFactory` (transfer states via aggregate) + new `TransferFactory`, `DummyDataSeeder` (aggregates + member rows + fee), test adaptations (§14).

## 11. Explicit behavior changes vs current code

Called out so none of this ships silently:

1. **Fee no longer survives unit deletion.** Today `softDelete()` intentionally leaves the fee ("the fee was really paid" — comment + test at `TransactionService.php:81-83`, `TransactionTest.php:113-124`). Under this spec the fee row is deleted with the unit; editing the unit restores it if `fee_amount` is still present. The old comment and test are rewritten, not merely adapted.
2. **Dashboard and trend numbers change.** `monthly_income` / trend income stop including `transfer_in`; `monthly_expenses` / trend expense stop including `transfer_out`. Internal transfers between own accounts no longer inflate income/expense summaries (§5.6). Net worth is unaffected — balance math uses `flow`.
3. **Global transactions list shows one row per transfer** (the outflow row) instead of both rows. Account-scoped lists still show their own row. List counts and pagination change accordingly.
4. **Transfer creation moves to `POST /transfers`.** `POST /transactions` with `type = 'transfer'` (today's pseudo-type) is no longer accepted — the frontend's transfer tab posts to the new endpoint. Plain payloads lose `destination_account_id`/`fee_amount` acceptance.
5. **Member row ids churn on unit edits** (rows recreated), **but the transfer's identity is now stable** — `transfers.id` survives edits, enabling stable transfer URLs/references later (an improvement over both today and Approach A).
6. **Editing a plain row's account now balances correctly** (observer fix) where it previously corrupted both accounts' balances.

## 12. Open questions

- One deferred: unit-level restore behavior if a transaction restore route is ever added — under B it keys naturally off the aggregate.
- Resolved 2026-09-11: endpoint split (plain vs transfer writes, unified delete); direct unit-member API edits rejected (422) to protect aggregate authority; approach revised A → B before implementation (domain-pattern analysis); relation naming — `transactions()` + source/destination/fee accessors; service ownership — `TransferService` owns the unit lifecycle; frontend — transfer UI folded into the transactions namespace (`transactions/edit-transfer` page, `module/transaction/transfer-form`).

## 13. Alternatives considered

Full schemas, relations, and the three-way comparison live in the decision record (`2026-09-10-transaction-transfer-linking-design.md`).

- **A — anchor pointer via `transfer_parent_id`** (superseded pre-implementation): outflow row doubles as unit head; dependents point at it. Initially approved for its smaller surface, but it accumulated special cases (directional fold, anchor guards, two-layer fee-edit rule) that all trace to the anchor's dual role. B separates the roles — the aggregate is *only* the unit, member rows are *only* money movements — and those special cases dissolve. Never implemented; revision cost ≈ zero (see decision record §10 addendum).
- **C — group key without FK** (ruled out): shared opaque key per unit, no FK. Strictly dominated — same flexibility as B's membership, none of the integrity; its failure mode (orphan rows, silent key collisions) already occurred once in this codebase's history.
- **Universal journal / full double-entry** (future rung, not a candidate now): make *every* transaction a journal with balanced lines (GnuCash/Firefly III shape). Buys zero-sum integrity and eliminates all special cases, at the cost of multi-row writes for simple events. B is the transfer-specific degenerate form; the ladder is A → B → universal journal.

## 14. Rollout checklist

1. Migrations: new `transfers` table; rewrite original `create_transactions_table` (drop `transfer_link_id` + self-FK, `transfer_id` FK `cascadeOnDelete` + `['transfer_id', 'flow']` index, `flow` column, index swap per §5.1, `transaction_date` → `date`); `migrate:fresh`; reseed via `DummyDataSeeder`
2. `TransactionType` narrowed; new `TransactionFlow`; `Transfer` model (casts + relations incl. source/destination/fee accessors); `Transaction` casts + `transfer()` relation
3. Observer (flow-based multiplier, original-account/original-flow reversal fix) + `BalanceService` → flow-based
4. Routes: add `transfers.store` / `transfers.edit` / `transfers.update`; new thin `TransferController` + `TransferPolicy`; `TransactionController` drops `isTransfer()` branch; update endpoint rejects unit members (422)
5. `SaveTransactionRequest` (plain-only rules) + new `SaveTransferRequest` per the §7.2 matrix; `TransactionData` (+flow, real enum) + new `TransferData`
6. `TransactionService` → aggregate-first `createTransfer`, `updatePlain`, `updateTransfer` (update aggregate + recreate member transactions), unit `softDelete` (aggregate + member rows in one transaction), global-list inflow-row filter
7. `DashboardController`, `ReportService`, `SpendingService` → §5.6 semantics (type-based summaries; replace all raw type literals)
8. Delete `resolveKind`/`KIND_BY_TYPE`; frontend per §5.5 (`transaction-list-item`, `transaction-list-filter`, `transaction-detail`, `dashboard`, `accounts/show`, `transaction.schema.ts`, endpoint routing for create/edit forms)
9. `TransactionFactory` transfer states + new `TransferFactory`; `DummyDataSeeder` aggregates + member rows + fee
10. `php artisan wayfinder:generate` + `composer generate:ts`
11. Tests: adapt transfer tests to `transfers.*` routes + aggregate semantics; **reverse** the fee-survival test (fee now deleted with unit); new tests — **soft-delete atomicity (aggregate + all member rows together, named discipline case)**, 422 on direct unit-member edits via `PUT /transactions`, unit delete from any member, global list hides inflow rows, dashboard/trend exclude transfer rows, observer account-change reversal, FK cascade on hard delete
12. Pint; full suite
