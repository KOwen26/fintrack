# Spec: Server-side Search, Filter & Sort — Transaction List

> Status: draft for evaluation · 2026-10-21
> Context: follows the cursor-pagination work (`TransactionListService::getTransactionsByCursorPage`, `TransactionListCursor`, merge-append load-more, replace-on-period-change) and the `transactionListHandler` service extraction (`resources/js/services/transaction-list.svelte.ts`).

## 1. Problem

At 5–10 transactions/day the 6-month window holds ~900–1,800 rows (15–30 pages at 60/page). Client-side search/sort only see *loaded* pages, so a mid-window search misses most results and "highest" sorts a slice. Period+pagination are already server-side; search/filter/sort still run in a client TanStack layer that must be maintained in parallel with the server query.

## 2. Goals

1. Search, account/category/type filters, and sort reflect the **entire period window**.
2. Keep cursor-append pagination; any query change (search/filters/sort/period) = **replace reload starting at page 1**.
3. Delete the client TanStack layer for this screen; the handler becomes a query-state controller.
4. Keep day grouping + collapse client-side (pure rendering).

**Non-goals:** virtualization, external API, summary semantics (stays period-scoped), URL-sync of filters.

## 3. Request contract

`GET /transactions` gains (alongside existing `period`, `cursor`):

|Param|Type|Notes|
|---|---|---|
|`q`|`string?`|max ~100 chars; matches description, amount-as-text, account name, transfer destination name, category name|
|`account_ids[]`|`int[]`|narrow to owned/joint accounts already in the base query|
|`category_ids[]`|`string[]`|enum values|
|`types[]`|`string[]`|`TransactionType` enum values|
|`sort`|`newest\|oldest\|highest\|lowest`|default `newest`|

Validation moves to a new **`TransactionIndexRequest`** (repo rule: all validation in Form Requests), **strict** (unknown enum values → 422): the only client is our own UI, and silent coercion hides bugs. `period` keeps its existing lenient `tryFrom` fallback for continuity — or tightens to match (open point 2).

## 4. Backend

**`TransactionListFilters`** — readonly Spatie Data object in `app/Data/Transaction/` (sibling of `TransactionListCursor`) carrying `search, accountIds, categoryIds, types, sort`. `TransactionListFilters::fromRequest(TransactionIndexRequest)` translates the validated table-state payload; the service consumes the semantic form.

**`TransactionListService::getTransactionsByCursorPage(user, cursor, period, filters)`** composes:

- Base query unchanged (createdBy, no transfer-inflow rows, `withinPeriod`).
- `search` → `WHERE (description LIKE ? OR amount::text LIKE ? OR account name LIKE ? OR transfer-destination LIKE ?)`. Category names come from a PHP enum (`Category::label()`), so name-search resolves to candidate `category_id`s **in PHP first**, then `whereIn` — no SQL-side label mapping.
- `account_ids` / `category_ids` / `types` → `whereIn`.
- Sort + keyset cursor (the load-bearing detail):

|`sort`|ORDER BY|Cursor predicate|
|---|---|---|
|`newest`|`transaction_date DESC, id DESC`|`(date, id) <`|
|`oldest`|`transaction_date ASC, id ASC`|`(date, id) >`|
|`highest`|`amount DESC, id DESC`|`(amount, id) <`|
|`lowest`|`amount ASC, id ASC`|`(amount, id) >`|

**`TransactionListCursor` becomes sort-aware.** Cursor wire carries its sort discriminator (e.g. `date|<v>|<id>` vs `amount|<v>|<id>`); a mismatched cursor/sort pair → 400.

**Client stops deriving cursors.** Today the client constructs `` `${transaction_date}|${id}` `` from the last row — under amount-sort it would need the amount too, which is fragile. Instead the server emits `next_cursor` in `transactions_meta`:

```ts
transactions_meta: { total: number; has_more: boolean; next_cursor: string | null }
```

The client echoes it verbatim on load-more. This removes the last place the client understands cursor internals.

**Merge semantics unchanged:** cursor present → `Inertia::merge` (append); absent → replace (this is what makes any query change reset cleanly to page 1).

**Indexes:** verify during implementation (via `database-schema`): keyset on `(amount, id)` wants a composite index if the table grows; confirm the existing `(transaction_date, id)` index. Add what's missing.

## 5. Frontend

**`transactionListHandler` rework** — same construction site (`transactionListHandler({ transactions, meta })`):

|Removed|Replaced by|
|---|---|
|`filtered` / client `count`|`rows` (= server prop) / `total` (= `meta.total`)|
|Hand-built query-state object|TanStack table **stays** as the canonical state container (`TableBrain`); its state — `global_filter`, `filters[]`, `sorting[]` — is sent structurally on the wire|
|Client-side filter execution|server does the filtering; setters write table state and coalesce into one reload|
|`toSortKey`, `getArrayFilter`|`SORT_STATE` map (exported); server maps the sort descriptor onto `TransactionSort`|

**Reload semantics (all single-request):**

|Interaction|Behavior|
|---|---|
|Search typing|debounced 300 ms live-apply → replace reload, page 1|
|Search clear (X)|instant clear → replace reload|
|Drawer Apply|period + accounts + categories + types + sort **composed into one** replace reload (today's apply fires a period reload *plus* client sets — that must merge into one request now)|
|Tag/chip clear|one replace reload|
|Load more|append reload with `next_cursor`, merge — unchanged|

`hasActiveFilters` = applied query differs from defaults (any of q/ids/types/sort/period). `resetAllFilters` = reset query state + one reload. `groups`/`dayOpen`/collapse untouched. The list component needs **no changes** (it already renders `groups` and reads `meta.total`); the filter component keeps its temp-state drawer, its setters just call the handler.

## 6. Tests (backend feature tests, per testing rules)

- Each filter narrows rows; combined filter+period composes.
- Search hits description / account name / transfer destination / category name / amount text.
- **Cursor walk under each sort**: appending pages reconstructs the full window in order, no dupes/missing (this is where sort-aware keyset bugs surface).
- Query change after deep scroll → replace at page 1, no merge artifacts.
- Malformed params → 422; stale-sort cursor → 400.
- Update the existing `TransactionPaginationTest` expectations for `next_cursor`.

## 7. Open points

1. **Amount search**: substring match on the stored decimal text (`150000`, `150000.00`) — no thousand-separator magic. Acceptable?
2. **Validation strictness**: strict 422 everywhere (recommended), or keep `period`'s lenient fallback?
3. **Polish**: dim/pulse the list while a replace reload is in flight (`applying` state) — include or skip?
