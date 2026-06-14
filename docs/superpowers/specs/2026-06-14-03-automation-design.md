# Spec: Automation

**Date:** 2026-06-14
**Status:** Approved
**Depends on:** Foundation spec, Ledger spec
**Scope:** Transaction Presets (called "templates" by the user) and Recurring Presets — reducing repetitive entry.

---

## 1. Overview

Automation covers two mechanisms for reducing transaction entry friction:

- **Transaction Presets** — quick-entry blueprints the user calls "templates". Pre-fill the transaction form with a saved type, amount, category, and account. Shown in the quick-add carousel.
- **Recurring Presets** — scheduled rules that auto-generate transactions on a fixed frequency (rent, salary, subscriptions).

These entities do not replace transactions; they generate or pre-fill them.

---

## 2. Entities

### 2.1 `transaction_presets`

Quick-entry blueprints. The user refers to these as "templates". Pre-fill the transaction form; user can adjust any field before saving.

| Column                           | Type                            | Notes                                     |
| -------------------------------- | ------------------------------- | ----------------------------------------- |
| `id`                             | bigint PK                       |                                           |
| `user_id`                        | bigint FK → users               |                                           |
| `name`                           | string                          | shown in quick-add carousel               |
| `type`                           | string                          | PHP enum: `income`, `expense`, `transfer` |
| `default_amount`                 | decimal(15,2) nullable          |                                           |
| `default_description`            | string nullable                 |                                           |
| `default_category_id`            | bigint FK → categories nullable |                                           |
| `default_source_account_id`      | bigint FK → accounts nullable   |                                           |
| `default_destination_account_id` | bigint FK → accounts nullable   | transfer target                           |
| `default_transfer_fee`           | decimal(15,2) nullable          |                                           |
| `deleted_at`                     | timestamp nullable              |                                           |
| `created_at`                     | timestamp                       |                                           |
| `updated_at`                     | timestamp                       |                                           |

### 2.2 `transaction_recurring_presets`

Scheduled auto-generation rules. A daily artisan command fires them on their `next_run_date`.

| Column                | Type                            | Notes                                                           |
| --------------------- | ------------------------------- | --------------------------------------------------------------- |
| `id`                  | bigint PK                       |                                                                 |
| `account_id`          | bigint FK → accounts            |                                                                 |
| `category_id`         | bigint FK → categories nullable |                                                                 |
| `created_by`          | bigint FK → users               |                                                                 |
| `name`                | string                          | e.g. "Monthly Rent"                                             |
| `type`                | string                          | PHP enum: `income`, `expense`                                   |
| `amount`              | decimal(15,2)                   |                                                                 |
| `description`         | string nullable                 |                                                                 |
| `frequency`           | string                          | PHP enum: `daily`, `weekly`, `fortnightly`, `monthly`, `yearly` |
| `next_run_date`       | date                            | next scheduled generation date                                  |
| `recurrence_end_date` | date nullable                   | stop generating after this date                                 |
| `last_run_date`       | date nullable                   | last date a transaction was generated                           |
| `is_active`           | boolean default true            |                                                                 |
| `deleted_at`          | timestamp nullable              |                                                                 |
| `created_at`          | timestamp                       |                                                                 |
| `updated_at`          | timestamp                       |                                                                 |

---

## 3. Business Logic

### 3.1 Preset Selection (Quick-Add)

When a user taps a preset in the quick-add carousel:

1. The transaction form is pre-filled with the preset's default values
2. The user can adjust any field (amount, description, category, account, date)
3. Saving creates a normal transaction row — the preset is not modified

Presets are suggestions only; they never lock values.

### 3.2 Recurring Preset Execution

A scheduled Artisan command runs daily:

```
php artisan presets:run-recurring
```

Algorithm:

1. Query `transaction_recurring_presets WHERE next_run_date <= today AND is_active = true AND deleted_at IS NULL`
2. For each match, inside a DB transaction:
   a. Call `TransactionService::create()` to insert the transaction
   b. Set `last_run_date = today`
   c. Advance `next_run_date` based on `frequency`:
    - `daily` → +1 day
    - `weekly` → +7 days
    - `fortnightly` → +14 days
    - `monthly` → +1 month (same day-of-month)
    - `yearly` → +1 year
      d. If `recurrence_end_date` is set and new `next_run_date > recurrence_end_date`: set `is_active = false`

If the command runs after a missed date (e.g. server was down), it generates **one** transaction and advances from today — it does not backfill missed occurrences.

---

## 4. Architecture Patterns

- **Service pattern**: all logic in services; controllers dispatch only
- **Event-listener**: `RecurringPresetExecuted` event fired after each auto-generated transaction; listener invalidates balance cache
- **SQL aggregates only**: no PHP iteration over collections for computed values

### Services

| Service                    | Responsibilities                                                           |
| -------------------------- | -------------------------------------------------------------------------- |
| `TransactionPresetService` | `create()`, `update()`, `softDelete()`                                     |
| `RecurringPresetService`   | `create()`, `update()`, `softDelete()`, `toggle(preset, bool)`, `runDue()` |

`RecurringPresetService::runDue()` is called by the Artisan command. Wrapped in a try/catch per preset so one failure does not block the rest.

### Events & Listeners

| Event                     | Listener                        | Action                       |
| ------------------------- | ------------------------------- | ---------------------------- |
| `RecurringPresetExecuted` | `InvalidateAccountBalanceCache` | flush `balance:account:{id}` |

---

## 5. Routes & Controllers

```
GET    /transaction-presets                    TransactionPresetsController@index
POST   /transaction-presets                    TransactionPresetsController@store
PUT    /transaction-presets/{preset}           TransactionPresetsController@update
DELETE /transaction-presets/{preset}           TransactionPresetsController@destroy

GET    /recurring-presets                      RecurringPresetsController@index
POST   /recurring-presets                      RecurringPresetsController@store
PUT    /recurring-presets/{preset}             RecurringPresetsController@update
DELETE /recurring-presets/{preset}             RecurringPresetsController@destroy
POST   /recurring-presets/{preset}/toggle      RecurringPresetsController@toggle
```

---

## 6. Inertia Pages

All files under `resources/js/pages/`:

| File                               | Purpose                                                                     |
| ---------------------------------- | --------------------------------------------------------------------------- |
| `transaction-presets/index.svelte` | Manage presets ("templates") — create, edit, reorder for quick-add carousel |
| `recurring-presets/index.svelte`   | List active recurring rules with next run date, amount, and toggle          |

---

## 7. Data Objects (Spatie Laravel Data)

- `TransactionPresetData` — preset with resolved account/category names
- `RecurringPresetData` — recurring rule with next run date and status

Run `composer generate:ts` after any Data class change.

---

## 8. Indexes

```sql
transaction_presets:            (user_id), (deleted_at)
transaction_recurring_presets:  (account_id), (next_run_date, is_active),
                                (created_by), (deleted_at)
```

The `(next_run_date, is_active)` compound index on `transaction_recurring_presets` is critical for the daily command query.

---

## 9. Scheduled Command Registration

Register in `routes/console.php`:

```php
Schedule::command('presets:run-recurring')->dailyAt('00:05');
```

Run at 00:05 to avoid midnight edge cases.

---

## 10. Out of Scope (MVP)

- Recurring transfers (only `income` and `expense` types supported in MVP)
- Backfill for missed recurring preset runs
- Preset ordering / pinning beyond the default list order
