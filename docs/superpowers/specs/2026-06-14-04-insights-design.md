# Spec: Insights

**Date:** 2026-06-14
**Status:** Approved
**Depends on:** Foundation spec, Ledger spec
**Scope:** Reports and cache layer — read-only analytics over existing ledger data.

---

## 1. Overview

Insights is a fully read-only spec. It introduces no new writable entities. All report data is computed via SQL aggregate queries on `transactions`, `accounts`, `budgets`, `categories`, and `household_members`.

Reports are cached using Laravel's cache abstraction (Redis preferred, database cache driver as fallback). Past months are permanently cached since their data is immutable. The current month uses a short TTL invalidated on transaction writes.

---

## 2. No New Tables

All data comes from tables defined in Foundation and Ledger specs. Insights only adds:

- Cache key conventions (documented below)
- `ReportService` class
- Report controller and Svelte pages

---

## 3. Reports

### 3.1 Income vs Expense Trend

**Purpose:** Dual-bar chart comparing monthly inflow and outflow. Shows surplus/deficit badge and net savings rate.

**Query:** Aggregate `transactions` grouped by `type` and `(YEAR, MONTH)` of `transaction_date` for the selected account, last N months.

```sql
SELECT
    YEAR(transaction_date)  AS year,
    MONTH(transaction_date) AS month,
    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS total_income,
    SUM(CASE WHEN type IN ('expense', 'fee') THEN amount ELSE 0 END) AS total_expense
FROM transactions
WHERE account_id = :account_id
  AND deleted_at IS NULL
  AND transaction_date >= :from_date
GROUP BY YEAR(transaction_date), MONTH(transaction_date)
ORDER BY year ASC, month ASC
```

**Output:** `TrendReportData` — array of monthly rows with `income`, `expense`, `net`, `surplus_rate`.

### 3.2 Category Leak Report

**Purpose:** Donut chart showing expense share by category. Highlights top accelerating categories.

**Query:** Aggregate `expense` + `fee` transactions by `category_id` for the selected period.

```sql
SELECT
    c.name,
    c.color,
    c.icon,
    SUM(t.amount) AS total,
    ROUND(SUM(t.amount) / :period_total * 100, 2) AS percentage
FROM transactions t
JOIN categories c ON c.id = t.category_id
WHERE t.account_id = :account_id
  AND t.type IN ('expense', 'fee')
  AND t.transaction_date BETWEEN :from AND :to
  AND t.deleted_at IS NULL
GROUP BY t.category_id, c.name, c.color, c.icon
ORDER BY total DESC
```

**Output:** `CategoryLeakReportData` — ranked list with name, color, icon, total, percentage.

### 3.3 Joint Contribution Split

**Purpose:** Side-by-side gauge showing inflow share by household member on joint accounts.

**Query:** Aggregate `income` transactions by `created_by` on a joint account.

```sql
SELECT
    u.name,
    SUM(t.amount) AS contributed
FROM transactions t
JOIN users u ON u.id = t.created_by
WHERE t.account_id = :account_id
  AND t.type = 'income'
  AND t.transaction_date BETWEEN :from AND :to
  AND t.deleted_at IS NULL
GROUP BY t.created_by, u.name
```

Only relevant for `access_type = 'joint'` accounts. For personal accounts, `ReportsController::contributionSplit()` returns a `ContributionSplitData` with `is_joint: false` and an empty members array — the Svelte page renders a "This report is only available for joint accounts" empty state rather than an error.

**Output:** `ContributionSplitData` — member name + amount + percentage of total.

### 3.4 Credit Utilization

**Purpose:** Gauge per credit card account showing current utilization against `credit_card_limit`.

**Query:** Uses the balance formula from the Ledger spec (via `BalanceService::forAccount()`). No separate query needed.

```
utilization = (credit_card_limit - current_balance) / credit_card_limit * 100
```

Alert thresholds:

- Normal: < 30%
- Warning: 30% – 69%
- High risk: ≥ 70%

**Note:** Credit utilization is always live — it is never cached, since it reflects the current balance which changes with every transaction.

**Output:** `CreditUtilizationData` — `limit`, `used`, `available`, `utilization_pct`, `alert_level`.

### 3.5 Fixed vs Variable Calculator

**Purpose:** Compares recurring fixed costs (`is_fixed_cost = true`) against variable spending for the selected period.

**Query:** Join `transactions` with `categories` on `is_fixed_cost`.

```sql
SELECT
    c.is_fixed_cost,
    SUM(t.amount) AS total
FROM transactions t
JOIN categories c ON c.id = t.category_id
WHERE t.account_id = :account_id
  AND t.type IN ('expense', 'fee')
  AND t.transaction_date BETWEEN :from AND :to
  AND t.deleted_at IS NULL
GROUP BY c.is_fixed_cost
```

**Output:** `FixedVariableData` — `fixed_total`, `variable_total`, `fixed_pct`, `variable_pct`, `safety_margin`.

---

## 4. Cache Strategy

### 4.1 Cache Key Convention

```
reports:{account_id}:{report_slug}:{year}:{month}
```

Examples:

```
reports:42:trend:2026:06
reports:42:category-leak:2026:05
reports:42:contribution-split:2026:06
```

### 4.2 TTL Rules

| Period             | TTL                     | Reason                                     |
| ------------------ | ----------------------- | ------------------------------------------ |
| Past months        | Permanent (`ttl: null`) | Immutable — past transactions never change |
| Current month      | 5 minutes               | Short TTL + event-based invalidation       |
| Credit utilization | No cache                | Always live                                |

### 4.3 Cache Invalidation

Tag-based: `Cache::tags(['account:'.$accountId])->flush()`

Triggered by:

- `TransactionSaved` listener: `InvalidateAccountReportCache`
- `TransactionDeleted` listener: `InvalidateAccountReportCache`

Both listeners are registered alongside `InvalidateAccountBalanceCache` from the Ledger spec.

### 4.4 Cache Driver

```
CACHE_STORE=redis       # preferred — supports tags natively
CACHE_STORE=database    # fallback — supports tags from Laravel 11+
```

The `database` cache uses the `cache` table already created by the existing `create_cache_table.php` migration.

---

## 5. Architecture Patterns

- **Service pattern**: `ReportService` is the single entry point; controller calls it and passes typed Data objects to Inertia
- **Event-listener**: cache invalidation is handled by listeners on existing `TransactionSaved` / `TransactionDeleted` events — no new events needed
- **SQL aggregates only**: every metric is a single aggregate query; no PHP iteration over transaction collections
- `ReportService` is strictly read-only — it never writes to any domain table

### Services

| Service         | Responsibilities                                                                                                                                                                |
| --------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `ReportService` | `trend(Account, int $months)`, `categoryLeak(Account, DateRange)`, `contributionSplit(Account, DateRange)`, `creditUtilization(Account)`, `fixedVsVariable(Account, DateRange)` |

Each method: check cache → on miss, run aggregate query → store result → return typed Data.

### Events & Listeners

No new events. Reuses `TransactionSaved` and `TransactionDeleted` from the Ledger spec.

| Event                | Listener                       | Action                                   |
| -------------------- | ------------------------------ | ---------------------------------------- |
| `TransactionSaved`   | `InvalidateAccountReportCache` | `Cache::tags(['account:'.$id])->flush()` |
| `TransactionDeleted` | `InvalidateAccountReportCache` | `Cache::tags(['account:'.$id])->flush()` |

---

## 6. Routes & Controllers

```
GET  /accounts/{account}/reports                      ReportsController@index
GET  /accounts/{account}/reports/trend                ReportsController@trend
GET  /accounts/{account}/reports/category-leak        ReportsController@categoryLeak
GET  /accounts/{account}/reports/contribution-split   ReportsController@contributionSplit
GET  /accounts/{account}/reports/credit-utilization   ReportsController@creditUtilization
GET  /accounts/{account}/reports/fixed-vs-variable    ReportsController@fixedVsVariable
```

All routes are GET (read-only). All require the authenticated user to have access to the account (via policy).

---

## 7. Inertia Pages

All files under `resources/js/pages/`:

| File                                | Purpose                                                                       |
| ----------------------------------- | ----------------------------------------------------------------------------- |
| `reports/index.svelte`              | Mobile-first dashboard: stacked trend + category leak + budget status summary |
| `reports/credit-utilization.svelte` | Credit card gauges (only shown if user has credit card accounts)              |
| `reports/fixed-vs-variable.svelte`  | Fixed vs variable breakdown with safety margin                                |

Report pages accept `account_id` and optional `from` / `to` date range as query parameters. Date range defaults to current month.

---

## 8. Data Objects (Spatie Laravel Data)

- `TrendReportData` — monthly income/expense rows + net
- `CategoryLeakReportData` — ranked categories with totals and percentages
- `ContributionSplitData` — per-member contribution amounts and percentages
- `CreditUtilizationData` — utilization percentage and alert level
- `FixedVariableData` — fixed vs variable totals and safety margin

Run `composer generate:ts` after any Data class change.

---

## 9. Report Design Principles

- Mobile-first layout: charts and gauges stack vertically
- Badge states for budget health: `On track`, `At risk`, `Over budget`
- Drill-through: tapping a category in the leak report navigates to a filtered transaction list
- Persist selected date range in URL query params (`?from=2026-05-01&to=2026-05-31`)

---

## 10. Out of Scope (MVP)

- PDF statements (P2)
- Email delivery of reports (P2)
- Report snapshots / materialized tables (upgrade path if query complexity grows)
- Cross-account aggregate dashboard (P1 — add after per-account reports are stable)
- Chart library choice is a frontend decision; spec is chart-library-agnostic
