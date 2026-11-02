# Scheduled Transactions — Design

Date: 2026-11-02
Status: Approved design, pending implementation plan
Supersedes: `2026-10-27-transaction-templates-design.md` §12 (forward-compatibility sketch — schedules are now a separate table, not template columns)

## Problem

Money moves on a rhythm — a Netflix subscription, monthly salary, a fortnightly tank fill, a yearly insurance premium, a one-off bill landing on the 28th. Templates removed the retyping but not the remembering: someone still has to open the form on the right day. And the books drift when nobody does.

The app needs schedules that fire template-based transactions automatically, or queue them for a one-tap confirmation, and catch up honestly when the app wasn't running.

## Decision

A schedule is a **user-owned recurrence bound to exactly one template** (`unique template_id` — at most one schedule row per template, ever; a canceled row is reactivated by editing it, a deleted row frees the template for a fresh schedule).

Ratified decisions from the design session:

| Question | Decision |
| --- | --- |
| Schedule ↔ template | Separate `transaction_schedules` table, one row per template |
| Frequencies | `Once · Daily · Weekly · Every 2 weeks · Monthly · Monthly on dates · Yearly` — fortnightly is `Weekly` with `interval: 2` |
| Execution | Lazy on visit + `schedules:process` command on `Schedule` — one idempotent engine |
| Confirmation surface | Dashboard due card |
| Unconfirmed runs | Auto-skip after a global `GRACE_DAYS = 7` (per-schedule knob rejected) |
| Template edits | Live template — occurrences and confirms read the template's current values |
| Confirm-time editing | None — Confirm posts as-is; corrections happen on the resulting transaction |
| End conditions | Never / on date (`ends_at`) / after N runs (`max_runs`) — first bound hit wins |
| Run ledger | Every occurrence gets a run row; `Skipped` is a single status, auto-causes live in `skip_reason` |
| Management UI | Settings → Scheduled Transactions page (index + shared form) |

Key semantic: **`max_runs` counts occurrences (run rows), not postings** — a 12-installment schedule stays 12 occurrences even if one is skipped, mirroring how `ends_at` bounds the occurrence stream rather than the postings.

## Schedule lifecycle

```
Settings (manage)                Execution (materialize)               Dashboard (decide)
----------------                ----------------------               -----------------
form --POST/PUT--> transaction_schedules
pause / resume / cancel / delete    middleware / schedules:process
                                          |
                                          v
                                   ScheduleProcessor (per schedule, one DB transaction)
                                     auto-post --> Posted   (transaction created, dated occurrence_date)
                                     confirm   --> Pending ----> due card: Confirm posts as-is,
                                     grace     --> Skipped                Skip leaves it out
                                     account   --> Skipped (unavailable_account)
```

A schedule never touches balances itself — the processor routes through the existing `TransactionService::create()` / `TransferService::create()`, exactly as a user-submitted form would.

### Frontend loop — how the user experiences it

```
1 · Create                    2 · Time passes (no UI)         3 · Decide
Settings -> Schedules -> Add  middleware + schedules:process  dashboard due card
  or template detail ----------> auto-post -> straight to     Confirm -> posts as-is
     "Schedule this template"      history (nothing asks)     Skip -----> modal, then skipped
     (?template= deep-link)    --> ask-first -> pending run    Upcoming 7 days: read-only
                                  (past +7d grace -> skipped,
                                   never surfaces)
        ^
        |  4 · Manage - Settings index: edit / pause / resume / cancel / delete,
            last-run line makes auto-skips visible
```

Flow rules:

- **Schedules never route through the transaction create form.** Manual template apply (`?template=` → create form, user edits, submits) and scheduled posting (server-side, no form) are disjoint paths that share only the template as their source of truth.
- **Exactly two interactive surfaces**: the settings form and the card actions. Confirm / Skip / Pause / Resume / Cancel are plain POSTs to Wayfinder controller URLs — the page reload carries the new state; no optimistic UI in v1.
- The card renders only server-computed props (`DashboardController`'s `scheduled`: pending + upcoming). There is zero client-side schedule logic — no date math, no frequency evaluation in JS (the `frequencySummary()` util only labels parameters the server already computed).
- §9–§10 are the component inventories for these surfaces.

## Design

### 1. Schema

**`transaction_schedules`**

| Column | Type | Notes |
| --- | --- | --- |
| `id` | id | |
| `user_id` | FK → users | owner; index |
| `template_id` | FK → transaction_templates | **unique**; `cascadeOnDelete` — deleting a template removes its schedule and runs; created transactions survive |
| `frequency` | string | `ScheduleFrequency` cast |
| `params` | json nullable | per-frequency parameters (§2) |
| `start_date` | date | first occurrence ≥ this; `after_or_equal:today` on create |
| `requires_confirmation` | boolean default false | false = post automatically |
| `status` | string | `ScheduleStatus` cast: `Active / Paused / Canceled / Completed` |
| `ends_at` | date nullable | inclusive last-possible occurrence date |
| `max_runs` | unsigned int nullable | occurrence cap |
| `next_run_at` | date nullable | denormalized due-check key; null when completed |
| `last_run_at` | date nullable | informational — last materialized occurrence date |
| timestamps | | no soft deletes |

Indexes: `unique(template_id)`, `index(user_id, status, next_run_at)` (the due-check), `index(next_run_at)`.

**`transaction_schedule_runs`** — the ledger. One row per occurrence.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | id | |
| `schedule_id` | FK → transaction_schedules | `cascadeOnDelete`; index |
| `user_id` | FK → users | denormalized for owner-scoped pending queries; index |
| `occurrence_date` | date | the scheduled date — becomes the transaction date |
| `status` | string | `RunStatus` cast: `Pending / Posted / Confirmed / Skipped` |
| `skip_reason` | string nullable | `SkipReason` cast: `grace_expired / unavailable_account / canceled` — **null = manual user skip** |
| `transaction_id` | FK → transactions nullable | `nullOnDelete`; the template↔transaction linkage |
| `processed_at` | timestamp nullable | when posted / confirmed / skipped |
| timestamps | | |

Index: **`unique(schedule_id, occurrence_date)`** — the idempotency guard. Two tabs lazily firing the processor concurrently cannot double-post; the duplicate insert loses and is swallowed.

### 2. Frequency engine — `App\Enums\ScheduleFrequency`

Backed string enum: `Once='once'`, `Daily='daily'`, `Weekly='weekly'`, `Monthly='monthly'`, `MonthlyOnDates='monthly_on_dates'`, `Yearly='yearly'`. All occurrence math lives on the enum as one pure, unit-tested method:

```php
public function nextOccurrence(CarbonInterface $after, CarbonInterface $startDate, ?array $params): ?CarbonInterface
// next occurrence strictly after $after, never earlier than $startDate; null when the frequency is exhausted (Once)
```

| Frequency | `params` | Next-occurrence rule |
| --- | --- | --- |
| Once | — | `startDate` if strictly after `$after`, else null |
| Daily | — | `$after + 1 day` |
| Weekly | `days_of_week: int[]` (ISO 1=Mon…7=Sun), `interval: 1\|2` | next date whose weekday ∈ `days_of_week` **and** whose week is an "on" week — `weeksBetween(Monday(candidate), Monday(startDate)) % interval === 0`. The fortnight phase anchors on `start_date`'s week. |
| Monthly | `day_of_month: int` | next month's `min(day, last_day_of_month)` after `$after` |
| MonthlyOnDates | `days_of_month: int[]` | soonest `min(day, last_day)` across the set, this month or next |
| Yearly | `month: int`, `day: int` | next year's `(month, min(day, month_length))` after `$after` |

**Clamping**: a picked day shortens to the month's last day (31st → Feb 28; yearly Feb 29 → Feb 28 off-leap). Days that can never exist (Feb 30) are rejected at validation, not clamped.

### 3. Models

- `TransactionSchedule` — `$guarded = []`; casts: `frequency`, `status`, `params: array`, dates; relations: `user`, `template`, `runs()`, `lastRun()`.
- `TransactionScheduleRun` — `$guarded = []`; casts: `status`, `skip_reason` (nullable enum), dates; relations: `schedule`, `transaction`, `user`.

### 4. Policy

`TransactionSchedulePolicy` — owner-only: `viewAny`/`create` for any authenticated user; `view`/`update`/`pause`/`resume`/`cancel`/`delete` via a private `canAccess(User, TransactionSchedule)` extractor. Run actions (`confirm`/`skip`) authorize through `$run->schedule` ownership — no separate run policy.

### 5. Validation — `SaveTransactionScheduleRequest`

- `template_id` — required (store only, immutable on update), exists, **owned**, and **schedulable**: `account_id` set and selectable (owned + not archived + not soft-deleted — `TransactionTemplateService::selectableAccountIds()` reuse); transfer templates additionally need a selectable destination (guaranteed by template validation, re-checked at fire time).
- `frequency` — required, `Rule::enum(ScheduleFrequency)`.
- Per-frequency params (validated conditionally):
  - Weekly — `days_of_week` required array, min 1, each int 1–7; `interval` in `{1, 2}` (default 1).
  - Monthly — `day_of_month` int 1–31.
  - MonthlyOnDates — `days_of_month` required array, min 1, each 1–31.
  - Yearly — `month` 1–12, `day` 1–31 valid for that month (Feb 30 rejected).
  - Once / Daily — no params.
- `start_date` — required, date, `after_or_equal:today` (no accidental backfill; deliberate backfill is out of scope).
- `ends_at` — nullable, date, `after:start_date`.
- `max_runs` — nullable, integer, min 1 (sane ceiling 999).
- `requires_confirmation` — boolean.

### 6. Services

**`TransactionScheduleService`** — CRUD + reads (house split: static reads, instance writes):
- `getSchedulesByUser(User)` — with `template` eager; each carrying `pending_count` and `last_run` summary.
- `getPendingRuns(User)` / `getUpcomingOccurrences(User, int $days = 7)` — upcoming is **computed** from frequencies via the enum engine, never pre-stored.
- `create / update / delete / pause / resume / cancel` — writes; every write recomputes `next_run_at` (and `status` transitions per §7).

**`ScheduleProcessor`** — the materializer engine. `processDue(?User $user = null): array{posted: int, pending: int, skipped: int}`:

1. Select schedules `status = Active` and `next_run_at <= today` (app timezone; **date granularity only** — no time-of-day), optionally scoped to a user.
2. Per schedule, inside one `DB::transaction`:
   - Generate occurrences from `next_run_at` through today (**catch-up loop** — the app being off doesn't cancel reality):
     - bound checks before each occurrence: `candidate > ends_at` → complete; the schedule's existing run-row count `>= max_runs` → complete; `Once` generates exactly one;
     - insert the run row — a unique-key violation means another process already handled it; swallow and continue;
     - confirmation mode → row stays `Pending`, **unless** already past grace (§7), then `Skipped(grace_expired)` without ever surfacing;
     - auto-post mode → re-validate template accounts against selectable ids; unavailable → `Skipped(unavailable_account)`; else create the transaction (§"posting") and mark `Posted` + `transaction_id`;
     - advance to the next occurrence.
   - After the loop: `next_run_at` = first occurrence strictly after today; none remaining → `status = Completed`, `next_run_at = null`. Update `last_run_at`.

**Posting** — the processor builds the same payload a form submit would: `TransactionService::create($user, TransactionData)` for income/expense, `TransferService::create($user, TransferData)` for transfers — type, category, amount, note as description, tags, and **`occurrence_date` as the transaction date** (not the materialization timestamp — honest books under catch-up). Values are read from the live template at fire time.

**Triggers — one engine, two callers:**

- `ProcessDueSchedules` middleware, appended to the `web` group: if `$request->user()` exists and a cheap indexed exists-check finds due schedules, run `processDue($user)` in a `try/catch` — a materialization failure logs, never 500s the page. Requests without schedules pay one indexed query.
- `schedules:process` artisan command: `processDue()` across all users; `self::SUCCESS/FAILURE` with counts; registered in `routes/console.php` — `Schedule::command('schedules:process')->everyMinute()->withoutOverlapping()`. Idempotent, so double coverage with the middleware is harmless.

### 7. Lifecycle & grace semantics

- **Active** — due query picks it up; `next_run_at` drives.
- **Paused** — excluded from the due query. Occurrences during a pause **never exist** — resume recomputes `next_run_at` as the first occurrence strictly after today; the paused window is not backfilled. Existing `Pending` runs survive a pause (you already owe those decisions).
- **Canceled** — future occurrences stop; remaining `Pending` runs flip to `Skipped(canceled)`. Row retained (history), and scheduling the same template again **reactivates this row** through its edit form (the `unique(template_id)` constraint makes it the only row).
- **Completed** — `ends_at` passed, `max_runs` consumed, or `Once` fired. `next_run_at = null`.
- **Deleted** — row + runs removed (`cascadeOnDelete`); transactions survive untouched.
- **Grace** — `GRACE_DAYS = 7` service constant. A pending run expires when `today > occurrence_date + 7`; expiry is applied at materialization time, so an occurrence discovered already-late never surfaces on the dashboard.
- **Confirm** — posts the run as-is against the live template, re-validating account availability first; failure flashes an error pointing at the template. `Pending → Confirmed`.
- **Skip** — manual, reason stays null. `Pending → Skipped`.

### 8. Controllers, routes, dashboard

- `Settings/TransactionScheduleController` — `index / create / edit / store / update / destroy` + `pause / resume / cancel` action endpoints. Route names `transaction-schedules.*` in `routes/settings.php`; the `settings` prefix in `app.ts`'s layout switch already covers the nested pages.
- `TransactionScheduleRunController` — `confirm` / `skip` (POST), routes `transaction-schedule-runs.confirm` / `.skip` on the app surface (the dashboard is the caller).
- `DashboardController` — adds one `scheduled` prop: `{ pending: Run[], upcoming: {date, template, requires_confirmation}[] }` via the service.
- `TransactionTemplateController::show` — adds `schedule_id` (nullable) so the template detail page can link out.
- Template delete modal copy warns when a schedule exists ("deleting also removes its schedule and run history — created transactions stay").

### 9. Frontend — Settings management

- `pages/app/settings/transaction-schedules/index.svelte` — `schedule-list` rows (the template-list pattern): decoration + template name, frequency summary ("Every 2 weeks on Fri", "Monthly on 1st", "Monthly on 1, 15", "Yearly on 3 Mar", "Once on 28 Nov"), next run, `ScheduleStatus` badge, pending-decision count, and a **last-run line** ("last: posted 5 Jan" / "last: skipped — grace expired") so auto-skips are never silent. Actions: Edit, Pause/Resume, Cancel and Delete behind `ConfirmationModal`.
- `pages/app/settings/transaction-schedules/form.svelte` — shared create/edit, `MobilePageLayout` + `BottomActionBar` + `FormAction` (templates-form pattern):
  - create: template combobox (decoration, type, amount) filtered to schedulable templates — unschedulable ones greyed with "set a default account first"; deep-linkable via `?template=` from the template detail page;
  - start date; frequency `Select`: Once / Daily / Weekly / **Every 2 weeks** / Monthly / Monthly on dates / Yearly (the fortnightly option maps to `Weekly` + `interval: 2`);
  - per-frequency params: weekday chip multiselect · day-of-month input · 1–31 chip grid · month + day selects;
  - `requires_confirmation` switch ("Ask before posting" ↔ "Post automatically");
  - **Ends** segmented choice: Never / On date (date input) / After N runs (number input);
  - template immutable on edit.
- `components/module/schedule/` — `schedule-form.svelte`, `schedule-list.svelte`, `schedule-status-badge.svelte`, `run-status-badge.svelte` (every displayed PHP enum gets its badge component; frequency renders as text, not a badge). `resources/js/utilities/schedule-frequency.ts` — `frequencySummary(frequency, params): string` shared by list and card.
- Settings nav: "Scheduled Transactions", solar calendar icon (`solar--calendar-minimalistic-duotone`, verified against the enabled Solar set in plan).

### 10. Frontend — Dashboard due card

`components/module/schedule/schedule-due-card.svelte`, rendered on `app/dashboard`:

- **Needs confirmation** — pending rows (decoration, template name, amount, occurrence date); **Confirm** posts as-is (`router.post`, reload); **Skip** behind a `ConfirmationModal` ("skipping leaves this entry out of your books").
- **Upcoming · next 7 days** — read-only computed rows (date, name, ask/auto hint).
- The card hides entirely when both sections are empty.

### 11. Edge cases & rules

- Weekend and holiday dates post on the date — bookkeeping, not banking. No shifting.
- Catch-up bursts are correct by design: 90 days offline on a daily auto-post schedule yields 90 backdated postings; confirm-mode occurrences past grace auto-skip at materialization without surfacing.
- `next_run_at` advance + run inserts share one DB transaction per schedule; `unique(schedule_id, occurrence_date)` is the race guard — no locks.
- Pending confirms read the **live template**; a template edited between occurrence and confirm posts the new values, re-validated — an account archived meanwhile rejects with a flash error.
- All three completion causes (`ends_at`, `max_runs`, `Once`) are checked in one order per occurrence; first hit wins.
- App timezone, dates only.

### 12. Linkage & audit

`transaction_schedule_runs.transaction_id` is the deferred template↔transaction linkage the templates feature reserved. `transactions` gains no column, no badge, no list-surface marker in v1 — the ledger makes any future "auto" indicator a join away.

### 13. Codegen & docs

- `php artisan wayfinder:generate --fresh` (cache quirk) after backend changes; frontend imports from `@wayfinder` only.
- Amend templates-spec §12 with a supersession pointer (done alongside this spec).
- README roadmap: move "Scheduled Transactions" into the implemented narrative once shipped.

### 14. Tests (Pest)

- `tests/Unit/Enums/ScheduleFrequencyTest.php` — occurrence matrix: fortnight anchoring across month/year boundaries and `interval` phase, weekly multiset, 31st clamping, on-dates wrap, yearly leap handling, Once exhaustion.
- `tests/Integration/Services/ScheduleProcessorTest.php` — auto-post with occurrence-date transaction; pending creation; grace auto-skip (boundary: `today == occurrence + 7` still pending, `+8` skips); catch-up batch; all three completion causes; unavailable-account skip in both modes; transfer path; idempotent double-process; paused schedules excluded; cancel flips pendings.
- `tests/Integration/Services/TransactionScheduleServiceTest.php` — CRUD, next-run recompute on every write, pause/resume semantics, reactivation of a canceled row.
- `tests/Feature/App/Settings/TransactionScheduleTest.php` — route matrix owner-only (403s), validation matrix (per-frequency params, Feb-30 rejection, schedulability rejection, `ends_at` ordering), redirect + flash targets.
- `tests/Feature/App/TransactionScheduleRunTest.php` — confirm/skip endpoints with policy; middleware materializes on an authed GET; dashboard `scheduled` prop shapes (pending + upcoming).
- Factories for both models (states: paused, canceled, completed; frequency states); follow the `testing-best-practices` skill.

## Out of scope

- Email / push notifications — dashboard card only.
- Full RRULE semantics — nth-weekday ("2nd Tuesday"), every-N-months, arbitrary intervals beyond 2 weeks.
- Confirm-time editing — Confirm posts as-is.
- Run-history page — the ledger's last-run line covers v1 visibility.
- Per-schedule grace period.
- An "auto" badge or marker on transactions in lists (linkage exists in the DB for a future UI).
- Deliberate backfill — `start_date` cannot precede today.
- Scheduling on behalf of shareable-account viewers.
