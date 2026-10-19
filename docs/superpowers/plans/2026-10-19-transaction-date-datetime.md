# Change `transactions.transaction_date` to `datetime` — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use better-executing-plans to implement this plan task-by-task — or better-parallel-subagents-executions if the plan splits into independent slices. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Promote `transactions.transaction_date` from day precision (`date`) to full timestamp (`datetime`) so a transaction carries its time-of-day, and propagate that change through the model, factory, validation, services, and frontend.

**Architecture:** In-place migration edit (app is not live; `migrate:fresh --seed`), matching the project's established pattern for non-live schema changes. Cast change on `Transaction`. Frontend switches the form input to the existing `date-time` variant and normalizes day-grouping to the date portion so a day still groups as one block.

**Tech Stack:** Laravel 12 (PHP 8.4), Eloquent, Pest, Svelte 5, DataComposer, Flatpickr `date-time` input.

**Scope note — transfers:** `transfers.transaction_date` is a sibling column with identical shape and is written from the same `transaction_date` value in `TransferService`. Changing only `transactions` leaves the two out of sync. This plan changes **both** in the same pass (Tasks 1–3 and 5) for consistency. If you want only `transactions`, skip the `transfers` lines, which are marked `[transfers]`.

**Workflow Mode:** `direct` (user reviews each change; testing, pint, and commits handled by the user between tasks).

**Complexity:** Medium (5 tasks: migration/model, factory+request+service, frontend, rules+regen, verification).

## Global Constraints

- PHP 8.4: explicit param types + return types; curly braces on all control structures
- Keep column name `transaction_date` — type-only change, no renames ripple to forms/filters/queries
- SQL aggregates (`DATE()`, `DATE_FORMAT()`, `WEEKDAY()`) keep working on `datetime` columns — no query rewrites needed
- `DatePeriod::toRange()` already emits `Y-m-d H:i:s` bounds — no change
- After backend changes: `php artisan wayfinder:generate`, then `composer generate:ts`

## Review Focus

The four failure modes most likely to bite:

1. **Stale model cast** — leaving `'transaction_date' => 'date'` truncates the time on read even though the column stores it → pinned by checking a created row round-trips its time (Task 5).
2. **Factory still day-only** — `format('Y-m-d')` produces midnight times, so datetime behavior is never exercised in tests → Task 2 step 3 (now emits `Y-m-d H:i:s`).
3. **Validation rejects time** — `before_or_equal:today` / `date`-only assumptions; switch to `now` so a later-today timestamp validates → Task 2 step 2.
4. **Frontend inputProps missing** — forgetting `inputProps: { type: 'date-time' }` keeps the picker day-only, so the column stores midnight despite the datetime type → Task 3 step 1.

---

### Task 1: Migration — `date` → `datetime`

**Complexity:** Small

**Files:**
- Modify: `database/migrations/2026_06_16_161919_create_transactions_table.php`
- Modify: `database/migrations/2026_06_16_161918_create_transfers_table.php` `[transfers]`

**Interfaces:**
- Produces: `transaction_date` as `DATETIME` on both tables; index `['account_id', 'transaction_date']` retained.

- [ ] **Step 1: Edit the transactions migration in place**

In `2026_06_16_161919_create_transactions_table.php`, replace:

```php
$table->date('transaction_date');
```

with:

```php
$table->dateTime('transaction_date');
```

Nothing else in the file changes. The `$table->index(['account_id', 'transaction_date']);` line stays.

- [ ] **Step 2: Edit the transfers migration in place `[transfers]`**

In `2026_06_16_161918_create_transfers_table.php`, same replacement:

```php
$table->date('transaction_date');   // before
$table->dateTime('transaction_date'); // after
```

- [ ] **Step 3: Hand off for review**

---

### Task 2: Model cast, factory, request, service

**Complexity:** Medium

**Files:**
- Modify: `app/Models/Transaction.php`
- Modify: `app/Models/Transfer.php` `[transfers]`
- Modify: `app/Services/TransactionService.php`
- Modify: `app/Services/TransferService.php` `[transfers]`
- Modify: `app/Http/Requests/SaveTransactionRequest.php`
- Modify: `app/Http/Requests/SaveTransferRequest.php` `[transfers]`
- Modify: `app/Services/AccountService.php`

- Produces: `Transaction::$transaction_date` cast to `'datetime'`; frontend sends full datetime, stored as-is; factory emits real times; validation accepts a datetime up to `now`.

- [ ] **Step 1: Model casts**

`app/Models/Transaction.php`:

```php
'transaction_date' => 'datetime',
```

`app/Models/Transfer.php` `[transfers]`:

```php
'transaction_date' => 'datetime',
```

- [ ] **Step 2: Form requests**

`SaveTransactionRequest.php`:

```php
'transaction_date' => ['required', 'date', 'before_or_equal:now'],
```

`SaveTransferRequest.php` `[transfers]`: same.

- [ ] **Step 3: Factories — emit a real time**

`TransactionFactory.php` and `TransferFactory.php` `[transfers]`:

```php
'transaction_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d H:i:s'),
```

so tests exercise the datetime column instead of midnight-only rows.

- [ ] **Step 4: Store the submitted value as-is (no normalization)**

The frontend now sends a full datetime, so the service layer just persists `$data->transaction_date` directly (Eloquent's `datetime` cast handles parsing). No `Date::parse`/`setTimeFrom` normalization is needed in `TransactionService` or `TransferService`.

`AccountService::syncInitialBalance` is unchanged — it keeps midnight (opening balance is a day-level fact), writing `->toDateString()` (cast stores midnight).
---

### Task 3: Frontend — capture date + time

**Complexity:** Medium

**Files:**
- Modify: `resources/js/schema/transaction.schema.ts`
- Modify: `resources/js/components/module/transaction/transaction-form.svelte`
- Modify: `resources/js/components/module/transaction/transaction-list.svelte`
- Modify: `resources/js/components/module/transaction/transaction-detail.svelte`
- Modify: `resources/js/pages/dev/table.svelte` (if present)

**Interfaces:**
- Produces: form input captures date + time; day grouping still groups by calendar day; detail/dev views show datetime where useful.

- [ ] **Step 1: Schema — capture time**

In `transaction.schema.ts`, change the field definition:

```ts
transaction_date: {
    label: 'Date & Time',
    form: () => ({
        type: 'date',
        required: true,
        inputProps: { type: 'date-time' },
    }),
},
```

The `TransactionFormData.transaction_date: string` type stays `string` (canonical `YYYY-MM-DDTHH:mm`).

- [ ] **Step 1b: `transaction-form.svelte` — direct DateInput**

This form uses a raw `<DateInput>` (not FormGenerator), so update it directly:

```svelte
<DateInput
    type="date-time"
    id="transaction-date"
    placeholder="Pick a date & time"
    bind:value={form.transaction_date} />
```

and the field title to "Date & Time". Also ensure the server-supplied `transaction.transaction_date` (Carbon → ISO) is normalized to `YYYY-MM-DDTHH:mm` before binding, since the `date-time` picker's `dateFormat` is `Y-m-d\\TH:i` (no seconds). Add a small formatter in `buildInitialData()`:

```ts
transaction_date: transaction.transaction_date?.slice(0, 16) ?? '',
```

(`slice(0,16)` yields `YYYY-MM-DDTHH:mm`.)

---
- [ ] **Step 2: List grouping — normalize to day**

In `transaction-list.svelte`, change:

```ts
const group = groups.get(transaction.transaction_date);
...
groups.set(transaction.transaction_date, [transaction]);
```

to:

```ts
const day = transaction.transaction_date.slice(0, 10);
const group = groups.get(day);
...
groups.set(day, [transaction]);
```

and the returned `date` becomes `day`.

- [ ] **Step 3: Detail view**

`transaction-detail.svelte`: change `'date'` → `'datetime'` in `DateTimeHelper.format(...)` if the time should be shown; otherwise leave `'date'`.

- [ ] **Step 4: Dev table**

`pages/dev/table.svelte`: same choice — `'date'` → `'datetime'` for the date column cell.

- [ ] **Step 5: Hand off for review**

---


### Task 4: Rules + type regeneration

**Complexity:** Small

**Files:**
- Modify: `.ai/rules/app.md`

- [ ] **Step 1: Update the date idiom rule**

The rule currently says day-precision columns cast to `'date'` (`transaction_date`). Move `transaction_date` out of that example (it is now `datetime`); keep `date` example generic, e.g. `created_on`/a genuinely day-only column. Add a line noting `transaction_date` is `datetime`.

- [ ] **Step 2: Regenerate types**

```bash
php artisan wayfinder:generate
composer generate:ts
```

- [ ] **Step 3: Hand off for review**

---

### Task 5: Verification

**Complexity:** Small

- [ ] **Step 1: Run migrations fresh**

```bash
php artisan migrate:fresh --seed
```

- [ ] **Step 2: Run the affected test suite**

```bash
vendor/bin/pest --filter='Transaction|Transfer|Spending|Account'
```

Fix any assertions that assumed day precision or date-only formatting (e.g. `toDateString()` comparisons, factory-dependent period windows). Per `tests.md`, pin `transaction_date` explicitly where a period window depends on it.


- [ ] **Step 3: Smoke check**

- Create one transaction via the form with a non-midnight time and confirm the stored row retains it (`php artisan tinker` or a quick query). Confirm the list still groups by calendar day.

- [ ] **Step 4: Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Hand off for review**