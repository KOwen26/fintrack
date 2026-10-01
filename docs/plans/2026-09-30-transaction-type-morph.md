# Deferred Plan: Cross-Type Morphing on Transaction Edit (Approach A)

**Date:** 2026-09-30
**Status:** Deferred — Approach B (income/expense toggle only) shipped first
**Scope:** `app/Http/Requests`, `app/Services`, `app/Http/Controllers`, `resources/js/components/module/transaction/mobile-transaction-form.svelte`

---

## Context

On edit, the mobile transaction form currently allows:

- **Create:** full type freedom (income / expense / transfer)
- **Plain-row edit:** income ↔ expense toggle (already supported end-to-end —
  `SaveTransactionRequest` accepts either type on PUT, and
  `TransactionObserver::updated()` reverses the original flow via
  `getOriginal('flow')` before applying the new one, so balances stay correct)
- **Transfer-unit edit:** type fixed (tabs hidden)

Approach A removes the last restriction: editing may cross the plain ↔ transfer
boundary in either direction.

## Why it was deferred

Cross-type conversion is not an update — it is a **morph between aggregates**:

| Plain row | Transfer unit |
|---|---|
| 1 `Transaction` row | `Transfer` + 2–3 member rows (source, destination, optional fee) |

The endpoints are shape-locked: `SaveTransactionRequest` marks
`destination_account_id`/`fee_amount` as `prohibited`;
`SaveTransferRequest` marks `type`/`category_id` as `prohibited`. Approach B
needed zero backend work, so it shipped first. Everything below is the design
for when the morph becomes worth its cost.

## What already works in our favor

1. **The frontend payload is already unified.** `mobile-transaction-form.svelte`
   emits one type-conditional union shape via `form.transform()` — the backend
   would be catching up to the client, not the reverse.
2. **`TransactionFormData` already carries both ids.** `transaction.id` (source/outflow
   row id) + `transaction.transfer_id` (aggregate id, null for plain rows) are enough
   to route the morph without extra props.
3. **Delete-and-recreate is an established pattern.**
   `TransferService::update()` already soft-deletes member rows and re-creates
   them inside one DB transaction; observers keep balances consistent.
4. **`handleTypeChange()` already preserves shared fields** (amount, date, notes,
   account) and resets type-specific ones (category ↔ destination + fee).

## Design

### 1. Validation — one morph-aware write request

Replace the edit-path usage of both `Save*Request`s with a conditional union
request (create can keep the existing pair):

```php
// type=transfer  → account_id, destination_account_id (different), amount,
//                   transaction_date required; fee_amount nullable;
//                   category_id prohibited
// type=income|expense → account_id, type, amount, transaction_date,
//                   category_id required; destination_account_id and
//                   fee_amount prohibited
```

This is exactly the shape `form.transform()` already submits.

### 2. Endpoint — key the morph by the source row id

Preferred: one canonical update route per source row, e.g.
`PUT /transactions/{transaction}` where the payload's `type` decides the target
shape:

- plain payload on a plain row → `TransactionService::update()` (unchanged)
- transfer payload on a plain row → **morph to unit**
- plain payload on a unit source row → **morph to plain**
- transfer payload on a unit → `TransferService::update()` (unchanged)

Routing by `transaction.id` keeps `TransactionFormData.id` stable across morphs and
gives `destroy` a single delete target (already true today).

### 3. Service — morph = delete + create in one transaction

```php
// plain → unit (inside DB::transaction):
//   softDelete($row)                       // observer reverses balance
//   transferService->create(...)           // observers book unit rows

// unit → plain (inside DB::transaction):
//   transferService->deleteUnit($row)      // observers reverse all rows
//   transactionService->create(...)        // observer books new row
```

No new balance logic — the existing observers do the math; the morph only
orchestrates them atomically.

### 4. Frontend — enable the tabs

- Remove the `!(isEdit && isTransfer)` guard and the `disabled={isEdit}` on the
  Transfer trigger.
- `formAction` becomes type-driven instead of edit-driven (unit update keyed by
  `transaction.id` per §2).

## Known costs (accepted when this ships)

- **Identity instability:** morphs mint new ids (new aggregate and/or new rows).
  Deep links to the old edit URL die (redirect after save goes to index, so the
  blast radius is small).
- **Audit semantics:** history records delete + create, not "edited". The
  soft-deleted originals remain queryable.
- **Data loss at the boundary:** plain → transfer drops `category_id`;
  transfer → plain drops `fee_amount` (decide: discard silently vs. surface a
  confirm notice when a value would be lost).
- **Balance invariants:** the delete/create event ordering across both observers
  must be covered by tests before shipping.

## Verification (when implemented)

1. Balance assertions: morph in every direction leaves account balances equal to
   hand-computed totals (source, destination, fee cases).
2. Morph with fee → plain: confirm the fee decision from above is applied.
3. Plain → transfer → plain round-trip leaves balances unchanged net of fee.
4. Old edit URLs for morphed rows 404/redirect gracefully.
