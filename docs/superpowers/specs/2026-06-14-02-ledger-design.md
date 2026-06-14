# Spec: Ledger

**Date:** 2026-06-14
**Status:** Approved
**Depends on:** Foundation spec
**Scope:** Transactions and Budgets — the daily-use core of FinTrack.

---

## 1. Overview

The Ledger spec covers the two entities that make FinTrack useful day-to-day:

- **Transactions** — every inflow, outflow, transfer, and fee movement against an account
- **Budgets** — monthly category-level spending limits with threshold alerts

All financial calculations (balance, spend, budget status) are computed exclusively via SQL aggregate queries. No PHP iteration over collections.

---

## 2. Entities

### 2.1 `transactions`

The authoritative ledger. Soft-deleted only — never hard-deleted.

| Column             | Type                            | Notes                                                               |
| ------------------ | ------------------------------- | ------------------------------------------------------------------- |
| `id`               | bigint PK                       |                                                                     |
| `account_id`       | bigint FK → accounts            |                                                                     |
| `category_id`      | bigint FK → categories nullable | uncategorized transactions are allowed                              |
| `created_by`       | bigint FK → users               | audit trail for joint accounts                                      |
| `amount`           | decimal(15,2)                   | always positive                                                     |
| `type`             | string                          | PHP enum: `income`, `expense`, `transfer_out`, `transfer_in`, `fee` |
| `transfer_link_id` | uuid nullable                   | groups transfer rows (outflow + inflow + optional fee)              |
| `transaction_date` | date                            | effective date chosen by user                                       |
| `description`      | string nullable                 | memo or note                                                        |
| `deleted_at`       | timestamp nullable              | soft delete only                                                    |
| `created_at`       | timestamp                       |                                                                     |
| `updated_at`       | timestamp                       |                                                                     |

**Direction is unambiguous from type — never stored separately:**

| Type           | Direction | Notes                                     |
| -------------- | --------- | ----------------------------------------- |
| `income`       | inflow    | money entering the account                |
| `expense`      | outflow   | money leaving the account                 |
| `transfer_out` | outflow   | the source leg of a transfer              |
| `transfer_in`  | inflow    | the destination leg of a transfer         |
| `fee`          | outflow   | always charged against the source account |

Splitting `transfer` into `transfer_out` / `transfer_in` eliminates the need for a `direction` field or subquery when computing balances. Both legs share the same `transfer_link_id`.

### 2.2 `budgets`

Monthly category-level spending limits per account.

| Column         | Type                   | Notes                |
| -------------- | ---------------------- | -------------------- |
| `id`           | bigint PK              |                      |
| `account_id`   | bigint FK → accounts   |                      |
| `category_id`  | bigint FK → categories |                      |
| `limit_amount` | decimal(15,2)          | monthly spending cap |
| `year`         | smallint               | budget year          |
| `month`        | tinyint                | budget month (1–12)  |
| `deleted_at`   | timestamp nullable     | soft delete          |
| `created_at`   | timestamp              |                      |
| `updated_at`   | timestamp              |                      |

Unique constraint on `(account_id, category_id, year, month)`.

---

## 3. Business Logic

### 3.1 Balance Calculation

Balance is computed entirely in SQL — never in PHP:

```sql
SELECT
    a.initial_balance +
    SUM(CASE
        WHEN t.type IN ('income', 'transfer_in') THEN t.amount
        WHEN t.type IN ('expense', 'transfer_out', 'fee') THEN -t.amount
        ELSE 0
    END) AS balance
FROM accounts a
LEFT JOIN transactions t
    ON t.account_id = a.id AND t.deleted_at IS NULL
WHERE a.id = :account_id
GROUP BY a.id, a.initial_balance
```

In practice this is expressed via Eloquent `selectRaw` / `withSum` scopes on the `Account` model. `BalanceService::forAccount(Account $account): decimal` is the single entry point.

### 3.2 Transfer Flow

Creating a transfer always produces 2–3 rows inside a single DB transaction:

1. **Outflow row** — `type=transfer_out`, `account_id=source`
2. **Inflow row** — `type=transfer_in`, `account_id=destination`
3. **Fee row** (optional) — `type=fee`, `account_id=source`, if `fee > 0`

All rows share the same `transfer_link_id` (UUID generated once per transfer).

Deleting a transfer soft-deletes all rows sharing that `transfer_link_id`.

### 3.3 Budget Spend Calculation

Spend for a budget period is computed in SQL:

```sql
SELECT SUM(t.amount) AS spend
FROM transactions t
WHERE t.account_id = :account_id
  AND t.category_id = :category_id
  AND t.type IN ('expense', 'fee')
  AND YEAR(t.transaction_date) = :year
  AND MONTH(t.transaction_date) = :month
  AND t.deleted_at IS NULL
```

### 3.4 Budget Status Thresholds

| Status        | Condition                     |
| ------------- | ----------------------------- |
| `on_track`    | spend < 80% of `limit_amount` |
| `at_risk`     | 80% ≤ spend < 100%            |
| `over_budget` | spend ≥ 100%                  |

### 3.5 Cache Invalidation

The `BalanceService` caches per-account balances in Redis (fallback: database cache driver).

Cache key: `balance:account:{id}`
Invalidated by: `TransactionSaved` and `TransactionDeleted` events → `InvalidateAccountBalanceCache` listener.

---

## 4. Architecture Patterns

- **Service pattern**: controllers call services, never touch Eloquent directly
- **Event-listener**: `TransactionSaved` / `TransactionDeleted` events fire after writes; listeners handle cache invalidation
- **SQL aggregates only**: balance and budget spend are single aggregate queries, not PHP loops

### Services

| Service              | Responsibilities                                                                        |
| -------------------- | --------------------------------------------------------------------------------------- |
| `TransactionService` | `create()`, `update()`, `softDelete()`, `createTransfer()`                              |
| `BudgetService`      | `upsert()`, `softDelete()`, `calculateStatus(Account, Category, int $year, int $month)` |
| `BalanceService`     | `forAccount(Account): string` — cache-aware balance query                               |

### Events & Listeners

| Event                | Listener                        | Action                       |
| -------------------- | ------------------------------- | ---------------------------- |
| `TransactionSaved`   | `InvalidateAccountBalanceCache` | flush `balance:account:{id}` |
| `TransactionDeleted` | `InvalidateAccountBalanceCache` | flush `balance:account:{id}` |

---

## 5. Routes & Controllers

```
GET    /accounts/{account}/transactions             TransactionsController@index
GET    /accounts/{account}/transactions/create      TransactionsController@create
POST   /accounts/{account}/transactions             TransactionsController@store
GET    /accounts/{account}/transactions/{tx}/edit   TransactionsController@edit
PUT    /accounts/{account}/transactions/{tx}        TransactionsController@update
DELETE /accounts/{account}/transactions/{tx}        TransactionsController@destroy

GET    /accounts/{account}/budgets                  BudgetsController@index
POST   /accounts/{account}/budgets                  BudgetsController@store
PUT    /accounts/{account}/budgets/{budget}         BudgetsController@update
DELETE /accounts/{account}/budgets/{budget}         BudgetsController@destroy
```

---

## 6. Inertia Pages

All files under `resources/js/pages/`:

| File                         | Purpose                                                                     |
| ---------------------------- | --------------------------------------------------------------------------- |
| `transactions/index.svelte`  | Ledger feed — filterable by date, category, type, amount                    |
| `transactions/create.svelte` | Add transaction; doubles as the quick-add bottom sheet target               |
| `transactions/edit.svelte`   | Edit existing transaction                                                   |
| `budgets/index.svelte`       | Budget list per account with status bars (on_track / at_risk / over_budget) |

---

## 7. Data Objects (Spatie Laravel Data)

- `TransactionData` — typed props for transaction pages
- `TransactionListData` — paginated ledger feed
- `BudgetData` — budget with computed spend and status
- `BudgetStatusData` — `limit_amount`, `spend`, `percentage`, `status` enum

Run `composer generate:ts` after any Data class change.

---

## 8. Indexes

```sql
transactions: (account_id), (category_id), (created_by),
              (account_id, transaction_date), (transfer_link_id),
              (account_id, type, transaction_date),
              (deleted_at)
budgets:      UNIQUE (account_id, category_id, year, month),
              (account_id, year, month)
```

---

## 9. Validation Rules

**Transaction store/update:**

- `account_id` — required, exists, user must have access
- `type` — required, in `[income, expense, transfer, fee]`
- `amount` — required, numeric, min 0.01
- `transaction_date` — required, date, not in future (configurable)
- `category_id` — nullable, exists, belongs to user or household
- For `type=transfer`: `destination_account_id` required, must differ from `account_id`

**Budget store/update:**

- `category_id` — required, exists, assigned to account
- `limit_amount` — required, numeric, min 0.01
- `year` / `month` — required, valid calendar values

---

## 10. Out of Scope (MVP)

- Receipt attachments (P2)
- Full-text transaction search (P1 — add after basic ledger is stable)
- CSV import (removed from MVP scope)
- Soft-delete recovery UI (records are preserved but restore action is post-MVP)
