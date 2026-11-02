# Scheduled Transactions — Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use better-executing-plans to implement this plan task-by-task — or better-parallel-subagents-executions if the plan splits into independent slices, or superpowers:subagent-driven-development if workflow mode is `remote`. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the server half of scheduled transactions — schedules table + run ledger, the frequency engine, the materializing processor, dual triggers (middleware + command), settings CRUD, run confirm/skip endpoints, and the dashboard `scheduled` prop.

**Architecture:** One schedule row per template (`unique template_id`) drives an idempotent processor that materializes run rows (`unique(schedule_id, occurrence_date)`), posting through the existing `TransactionService`/`TransferService`. All occurrence math is one pure enum method. Spec: `docs/superpowers/specs/2026-11-02-scheduled-transactions-design.md`.

**Tech Stack:** Laravel 12 (PHP 8.5), Pest, MySQL, Wayfinder codegen.

**Workflow Mode:** `direct` — write tests + implementation only. No run/verify/commit steps anywhere in this plan; the user runs tests/builds on request and commits personally.

**Complexity:** Medium

## Global Constraints

- Spec rules, verbatim: `GRACE_DAYS = 7` — a pending run expires when `today > occurrence_date + 7`. `max_runs` counts occurrences (run rows), not postings. First bound hit wins among `ends_at` / `max_runs` / `Once`. Transaction date = `occurrence_date`, never materialization time. App timezone, date granularity only.
- Statuses: schedule `Active/Paused/Canceled/Completed` (`active`,`paused`,`canceled`,`completed`); run `Pending/Posted/Confirmed/Skipped` (`pending`,`posted`,`confirmed`,`skipped`); skip_reason `grace_expired/unavailable_account/canceled`, **null = manual skip**.
- Pause: occurrences during a pause never exist (resume = first occurrence strictly after today; existing `Pending` runs survive). Cancel: remaining pendings flip to `Skipped(canceled)`. Update reactivates a canceled/completed row (status → `Active`, next run recomputed). Delete: row + runs cascade; transactions survive.
- House PHP: curly braces everywhere, explicit return types, constructor property promotion, backed string enums with TitleCase cases, `protected function casts(): array`, `$guarded = []`, `#[Scope]` for scopes, no `$table->enum()`, enum-`->value` for column defaults, explicit FK indexes, no soft deletes on these tables.
- Validation lives in `app/Http/Requests/` — never inline. Controllers are thin dispatchers (`$this->authorize`, one service call, `to_route(...)->flash(...)`).
- `php artisan wayfinder:generate --fresh` after enums/models/routes land (cache quirk). `resources/js/wayfinder/` is gitignored — never hand-edit.
- Do NOT run Pint/formatters, do NOT commit — the user owns both. Tests/builds run only when the user asks: `php artisan test --compact <file>`.
- The dead `app/Enums/RecurringFrequency.php` stub (zero consumers — only Wayfinder's generated mirror) is deleted in Task 1 and replaced by `ScheduleFrequency`.

---

### Task 1: `ScheduleFrequency` enum — the occurrence engine

**Complexity:** Medium

**Files:**
- Create: `app/Enums/ScheduleFrequency.php`
- Delete: `app/Enums/RecurringFrequency.php` (dead stub, zero consumers)
- Test: `tests/Unit/Enums/ScheduleFrequencyTest.php`

**Interfaces:**
- Consumes: nothing (leaf).
- Produces: `ScheduleFrequency::nextOccurrence(CarbonInterface $after, CarbonInterface $startDate, ?array $params = null): ?CarbonInterface` — next occurrence **strictly after** `$after` **and never earlier than** `$startDate`; `null` when exhausted. Cases: `Once='once'`, `Daily='daily'`, `Weekly='weekly'`, `Monthly='monthly'`, `MonthlyOnDates='monthly_on_dates'`, `Yearly='yearly'`. Params shapes: Weekly `{days_of_week: list<int> (ISO 1=Mon..7=Sun), interval: 1|2}`, Monthly `{day_of_month: int}`, MonthlyOnDates `{days_of_month: list<int>}`, Yearly `{month: int, day: int}`; Once/Daily `null`. Day-of-month clamps to the month's last day (31 → Feb 28; yearly Feb 29 → Feb 28 off-leap). Fortnight phase anchors on `startDate`'s week.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\ScheduleFrequency;
use Carbon\CarbonImmutable;

it('returns the start date for once when still ahead, null once past', function (string $today, ?string $expected): void {
    $startDate = new CarbonImmutable('2026-12-01');

    $result = ScheduleFrequency::Once->nextOccurrence(new CarbonImmutable($today), $startDate);

    expect($result?->toDateString())->toBe($expected);
})->with([
    'before the date' => ['2026-11-25', '2026-12-01'],
    'on the date'     => ['2026-12-01', null],
    'after the date'  => ['2026-12-02', null],
]);

it('advances daily one day at a time, never before the start date', function (): void {
    $startDate = new CarbonImmutable('2026-11-10');

    expect(ScheduleFrequency::Daily->nextOccurrence(new CarbonImmutable('2026-11-25'), $startDate)?->toDateString())->toBe('2026-11-26')
        ->and(ScheduleFrequency::Daily->nextOccurrence(new CarbonImmutable('2026-11-05'), $startDate)?->toDateString())->toBe('2026-11-10');
});

it('picks the next selected weekday for weekly', function (): void {
    $startDate = new CarbonImmutable('2026-11-04'); // Wednesday
    $params = ['days_of_week' => [1, 5], 'interval' => 1]; // Mon + Fri

    // 2026-11-05 is Thursday → next is Friday 06
    expect(ScheduleFrequency::Weekly->nextOccurrence(new CarbonImmutable('2026-11-05'), $startDate, $params)?->toDateString())->toBe('2026-11-06')
        // from Friday 06 → Monday 09
        ->and(ScheduleFrequency::Weekly->nextOccurrence(new CarbonImmutable('2026-11-06'), $startDate, $params)?->toDateString())->toBe('2026-11-09');
});

it('honours the fortnight phase anchored on the start week for interval 2', function (): void {
    // Start Monday 2026-11-02 → "on" weeks begin 11-02, 11-16, 11-30
    $startDate = new CarbonImmutable('2026-11-02');
    $params = ['days_of_week' => [5], 'interval' => 2]; // Fridays

    // Friday 11-06 falls in the anchor week → on
    expect(ScheduleFrequency::Weekly->nextOccurrence(new CarbonImmutable('2026-11-05'), $startDate, $params)?->toDateString())->toBe('2026-11-06')
        // Friday 11-13 is an "off" week → jumps to 11-20
        ->and(ScheduleFrequency::Weekly->nextOccurrence(new CarbonImmutable('2026-11-13'), $startDate, $params)?->toDateString())->toBe('2026-11-20')
        // crossing a month boundary keeps the phase: after 11-20 → 12-04
        ->and(ScheduleFrequency::Weekly->nextOccurrence(new CarbonImmutable('2026-11-20'), $startDate, $params)?->toDateString())->toBe('2026-12-04');
});

it('clamps the monthly day to the months last day', function (string $after, string $expected): void {
    $startDate = new CarbonImmutable('2026-01-31');

    expect(ScheduleFrequency::Monthly->nextOccurrence(new CarbonImmutable($after), $startDate, ['day_of_month' => 31])?->toDateString())->toBe($expected);
})->with([
    'jan 31 → feb 28 (2027 non-leap path 2026)' => ['2026-01-31', '2026-02-28'],
    'feb 28 → mar 31'                          => ['2026-02-28', '2026-03-31'],
    'before the start date snaps to it'        => ['2026-01-01', '2026-01-31'],
]);

it('picks the soonest of the selected days each month for monthly on dates', function (): void {
    $startDate = new CarbonImmutable('2026-11-01');
    $params = ['days_of_month' => [1, 15]];

    expect(ScheduleFrequency::MonthlyOnDates->nextOccurrence(new CarbonImmutable('2026-11-07'), $startDate, $params)?->toDateString())->toBe('2026-11-15')
        ->and(ScheduleFrequency::MonthlyOnDates->nextOccurrence(new CarbonImmutable('2026-11-15'), $startDate, $params)?->toDateString())->toBe('2026-12-01');
});

it('walks to the next yearly date and clamps feb 29 off-leap', function (): void {
    $startDate = new CarbonImmutable('2024-02-29'); // leap start

    expect(ScheduleFrequency::Yearly->nextOccurrence(new CarbonImmutable('2024-02-29'), $startDate, ['month' => 2, 'day' => 29])?->toDateString())->toBe('2025-02-28')
        ->and(ScheduleFrequency::Yearly->nextOccurrence(new CarbonImmutable('2025-03-01'), $startDate, ['month' => 2, 'day' => 29])?->toDateString())->toBe('2026-02-28')
        ->and(ScheduleFrequency::Yearly->nextOccurrence(new CarbonImmutable('2027-12-31'), $startDate, ['month' => 2, 'day' => 29])?->toDateString())->toBe('2028-02-29');
});
```

- [ ] **Step 2: Implement the enum**

```php
<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Recurrence vocabulary for transaction schedules. All occurrence math
 * lives here as one pure function so the processor, the read projections
 * and the tests share a single definition of "next".
 *
 * Weekly carries an `interval` param (1|2): 2 models the fortnightly
 * rhythm, its phase anchored on the schedule's start-date week. Picked
 * days clamp to a month's last day (31 → Feb 28; Feb 29 → Feb 28 in
 * non-leap years) — they are never skipped forward.
 */
enum ScheduleFrequency: string
{
    case Once = 'once';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case MonthlyOnDates = 'monthly_on_dates';
    case Yearly = 'yearly';

    /**
     * The next occurrence strictly after $after, never earlier than
     * $startDate. Null when the frequency is exhausted (Once past its
     * date). Date granularity only — times are never considered.
     *
     * @param  array{days_of_week?: list<int>, interval?: int, day_of_month?: int, days_of_month?: list<int>, month?: int, day?: int}|null  $params
     */
    public function nextOccurrence(CarbonInterface $after, CarbonInterface $startDate, ?array $params = null): ?CarbonInterface
    {
        $params ??= [];

        $after = $after->toImmutable()->startOfDay();
        $startDate = $startDate->toImmutable()->startOfDay();

        // Walking from one day before the start keeps weekday and
        // month-day math frequency-true when $after predates the stream.
        $after = $after->greaterThan($startDate) ? $after : $startDate->subDay();

        $candidate = match ($this) {
            self::Once => $startDate,

            self::Daily => $after->addDay(),

            self::Weekly => $this->nextWeekly($after, $startDate, $params),

            self::Monthly => $this->nextMonthlyOnDay($after, (int) ($params['day_of_month'] ?? 1)),

            self::MonthlyOnDates => $this->nextMonthlyOnDates(
                $after,
                array_map(intval(...), $params['days_of_month'] ?? [1]),
            ),

            self::Yearly => $this->nextYearly($after, (int) ($params['month'] ?? 1), (int) ($params['day'] ?? 1)),
        };

        return $candidate !== null && $candidate->greaterThan($after) ? $candidate : null;
    }

    /**
     * Next selected weekday in an "on" week. The fortnight phase is the
     * whole-week distance from the start date's Monday, in interval units.
     *
     * @param  array{days_of_week?: list<int>, interval?: int}  $params
     */
    private function nextWeekly(CarbonImmutable $after, CarbonImmutable $startDate, array $params): ?CarbonImmutable
    {
        $days = array_map(intval(...), $params['days_of_week'] ?? []);
        $interval = max(1, (int) ($params['interval'] ?? 1));

        if ($days === []) {
            return null;
        }

        $anchorMonday = $startDate->startOfWeek(CarbonInterface::MONDAY);
        $cursor = $after->addDay();
        $limit = $cursor->addDays($interval * 7 + 7);

        while ($cursor->lessThanOrEqualTo($limit)) {
            $cursorMonday = $cursor->startOfWeek(CarbonInterface::MONDAY);
            $weeksFromAnchor = intdiv($anchorMonday->diffInDays($cursorMonday), 7);

            if (in_array($cursor->dayOfWeekIso, $days, true) && $weeksFromAnchor % $interval === 0) {
                return $cursor;
            }

            $cursor = $cursor->addDay();
        }

        return null;
    }

    /** Next clamped occurrence of one day-of-month, this month or the next. */
    private function nextMonthlyOnDay(CarbonImmutable $after, int $day): ?CarbonImmutable
    {
        return $this->nextMonthlyOnDates($after, [$day]);
    }

    /**
     * Soonest clamped occurrence across the selected days, this month or
     * the next. Short months clamp (31 → 30/28), never skip.
     *
     * @param  list<int>  $days
     */
    private function nextMonthlyOnDates(CarbonImmutable $after, array $days): ?CarbonImmutable
    {
        $month = $after->startOfMonth();

        for ($i = 0; $i < 3; $i++) {
            foreach (array_unique($days, SORT_REGULAR) as $day) {
                $candidate = $month->setUnitNoOverflow('day', min($day, $month->daysInMonth), 'month');

                if ($candidate->greaterThan($after)) {
                    return $candidate;
                }
            }

            $month = $month->addMonth()->startOfMonth();
        }

        return null;
    }

    /** Next clamped (month, day) in this year or the next. */
    private function nextYearly(CarbonImmutable $after, int $month, int $day): ?CarbonImmutable
    {
        $year = $after->year;

        for ($i = 0; $i < 2; $i++) {
            $monthStart = $after->setDate($year, $month, 1)->startOfMonth();
            $candidate = $monthStart->setUnitNoOverflow('day', min($day, $monthStart->daysInMonth), 'month');

            if ($candidate->greaterThan($after)) {
                return $candidate;
            }

            $year++;
        }

        return null;
    }
}
```

Then delete `app/Enums/RecurringFrequency.php` — it has zero consumers (only its own file and Wayfinder's generated mirror, which the Task 8 regen rewrites).

---

### Task 2: Migrations, models, factories

**Complexity:** Medium

**Files:**
- Create: `database/migrations/2026_11_02_000001_create_transaction_schedules_table.php` (both tables in one migration)
- Create: `app/Enums/ScheduleStatus.php`, `app/Enums/RunStatus.php`, `app/Enums/SkipReason.php`
- Create: `app/Models/TransactionSchedule.php`, `app/Models/TransactionScheduleRun.php`
- Modify: `app/Models/TransactionTemplate.php` (add `schedule()` relation)
- Create: `database/factories/TransactionScheduleFactory.php`, `database/factories/TransactionScheduleRunFactory.php`
- Test: none as a dedicated file — casts, relations and factories are exercised by Tasks 3–7's tests (deliberate: house has no standalone model tests for this shape; `tests/Integration/Models` covers only cast-objects like Tags).

**Interfaces:**
- Consumes: `ScheduleFrequency` (Task 1).
- Produces:
  - `TransactionSchedule` — casts `frequency: ScheduleFrequency`, `status: ScheduleStatus`, `params: array`, `start_date/ends_at/next_run_at/last_run_at: date`, `requires_confirmation: boolean`; relations `user`, `template`, `runs(): HasMany`, `lastRun(): HasOne` (latest occurrence_date), and on `TransactionTemplate`: `schedule(): HasOne` (inverse of unique). Public domain methods: `advanceNextRun(CarbonInterface $occurrencesAfter, ?int $runCount = null): void` (applies `ends_at`/`max_runs` bounds, completes when exhausted) and `complete(): void`.
  - `TransactionScheduleRun` — casts `status: RunStatus`, `skip_reason: SkipReason` (nullable), `occurrence_date: date`, `processed_at: datetime`; relations `schedule`, `transaction`, `user`.
  - Enums: `ScheduleStatus: Active='active', Paused='paused', Canceled='canceled', Completed='completed'`; `RunStatus: Pending='pending', Posted='posted', Confirmed='confirmed', Skipped='skipped'`; `SkipReason: GraceExpired='grace_expired', UnavailableAccount='unavailable_account', Canceled='canceled'`.
  - Factory states used by later tasks: `TransactionScheduleFactory::daily()`, `::weekly()`, `::monthlyOnDates()`, `::yearly()`, `::once()`, `::confirmation()`, `::paused()`, `::canceled()`, `::due()`, `::startsInPast()`; `TransactionScheduleRunFactory` plain.

- [ ] **Step 1: Write the migration**

```php
<?php

use App\Enums\ScheduleStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('template_id')->unique()->constrained('transaction_templates')->cascadeOnDelete();
            $table->string('frequency');                 // ScheduleFrequency cast
            $table->json('params')->nullable();
            $table->date('start_date');
            $table->boolean('requires_confirmation')->default(false);
            $table->string('status')->default(ScheduleStatus::Active->value);
            $table->date('ends_at')->nullable();
            $table->unsignedInteger('max_runs')->nullable();
            $table->date('next_run_at')->nullable();
            $table->date('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'next_run_at']);
            $table->index('next_run_at');
        });

        Schema::create('transaction_schedule_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('schedule_id')->constrained('transaction_schedules')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('occurrence_date');
            $table->string('status');                    // RunStatus cast
            $table->string('skip_reason')->nullable();   // SkipReason cast; null = manual skip
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['schedule_id', 'occurrence_date']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_schedule_runs');
        Schema::dropIfExists('transaction_schedules');
    }
};
```

- [ ] **Step 2: Write the three status enums**

`app/Enums/ScheduleStatus.php`:

```php
<?php

namespace App\Enums;

enum ScheduleStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Canceled = 'canceled';
    case Completed = 'completed';
}
```

`app/Enums/RunStatus.php`:

```php
<?php

namespace App\Enums;

enum RunStatus: string
{
    case Pending = 'pending';
    case Posted = 'posted';
    case Confirmed = 'confirmed';
    case Skipped = 'skipped';
}
```

`app/Enums/SkipReason.php`:

```php
<?php

namespace App\Enums;

/** Null on the run row = the user skipped it manually. */
enum SkipReason: string
{
    case GraceExpired = 'grace_expired';
    case UnavailableAccount = 'unavailable_account';
    case Canceled = 'canceled';
}
```

- [ ] **Step 3: Write the models**

`app/Models/TransactionSchedule.php`:

```php
<?php

namespace App\Models;

use App\Enums\ScheduleFrequency;
use App\Enums\ScheduleStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class TransactionSchedule extends Model
{
    /** @use HasFactory<TransactionScheduleFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'frequency' => ScheduleFrequency::class,
            'status' => ScheduleStatus::class,
            'params' => 'array',
            'start_date' => 'date',
            'ends_at' => 'date',
            'next_run_at' => 'date',
            'last_run_at' => 'date',
            'requires_confirmation' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TransactionTemplate::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(TransactionScheduleRun::class);
    }

    public function lastRun(): HasOne
    {
        return $this->hasOne(TransactionScheduleRun::class)->latestOfMany('occurrence_date');
    }

    /**
     * Recompute next_run_at as the first occurrence after $occurrencesAfter
     * (pass today for "strictly after today", yesterday for "from today"),
     * applying the end bounds — ends_at inclusive, max_runs counting run
     * rows. Completes the schedule when exhausted.
     */
    public function advanceNextRun(CarbonInterface $occurrencesAfter, ?int $runCount = null): void
    {
        if ($this->max_runs !== null && ($runCount ?? $this->runs()->count()) >= $this->max_runs) {
            $this->complete();

            return;
        }

        $next = $this->frequency->nextOccurrence($occurrencesAfter, $this->start_date, $this->params);

        if ($next === null || ($this->ends_at !== null && $next->isAfter($this->ends_at))) {
            $this->complete();

            return;
        }

        $this->next_run_at = $next;
    }

    public function complete(): void
    {
        $this->status = ScheduleStatus::Completed;
        $this->next_run_at = null;
    }
}
```

`app/Models/TransactionScheduleRun.php`:

```php
<?php

namespace App\Models;

use App\Enums\RunStatus;
use App\Enums\SkipReason;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TransactionScheduleRun extends Model
{
    /** @use HasFactory<TransactionScheduleRunFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'skip_reason' => SkipReason::class,
            'occurrence_date' => 'date',
            'processed_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TransactionSchedule::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

Add to `app/Models/TransactionTemplate.php` (below `destinationAccount()`):

```php
    /** The schedule bound to this template, when one exists — at most one row (unique). */
    public function schedule(): HasOne
    {
        return $this->hasOne(TransactionSchedule::class);
    }
```

(with `use Illuminate\Database\Eloquent\Relations\HasOne;` added to the imports).

- [ ] **Step 4: Write the factories**

`database/factories/TransactionScheduleFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\ScheduleFrequency;
use App\Enums\ScheduleStatus;
use App\Models\TransactionSchedule;
use App\Models\TransactionTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionSchedule>
 */
class TransactionScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => fn (array $attributes) => User::factory(),
            'template_id' => fn (array $attributes) => TransactionTemplate::factory()->create([
                'user_id' => $attributes['user_id'],
            ])->id,
            'frequency' => ScheduleFrequency::Monthly,
            'params' => ['day_of_month' => 1],
            'start_date' => now()->toDateString(),
            'requires_confirmation' => false,
            'status' => ScheduleStatus::Active,
            'ends_at' => null,
            'max_runs' => null,
            'next_run_at' => now()->toDateString(),
            'last_run_at' => null,
        ];
    }

    /** Pending an explicit user_id in the test, keep definition honest. */
    public function forUser(int $userId): static
    {
        return $this->state([
            'user_id' => $userId,
            'template_id' => fn (array $attributes) => TransactionTemplate::factory()->create([
                'user_id' => $userId,
            ])->id,
        ]);
    }

    public function daily(): static
    {
        return $this->state([
            'frequency' => ScheduleFrequency::Daily,
            'params' => null,
            'next_run_at' => now()->toDateString(),
        ]);
    }

    /** Fortnightly Fridays — interval 2, phase anchored on the start week. */
    public function weekly(): static
    {
        return $this->state([
            'frequency' => ScheduleFrequency::Weekly,
            'params' => ['days_of_week' => [5], 'interval' => 2],
        ]);
    }

    public function monthlyOnDates(): static
    {
        return $this->state([
            'frequency' => ScheduleFrequency::MonthlyOnDates,
            'params' => ['days_of_month' => [1, 15]],
        ]);
    }

    public function yearly(): static
    {
        return $this->state([
            'frequency' => ScheduleFrequency::Yearly,
            'params' => ['month' => 3, 'day' => 3],
        ]);
    }

    public function once(): static
    {
        return $this->state([
            'frequency' => ScheduleFrequency::Once,
            'params' => null,
        ]);
    }

    public function confirmation(): static
    {
        return $this->state(['requires_confirmation' => true]);
    }

    public function paused(): static
    {
        return $this->state(['status' => ScheduleStatus::Paused]);
    }

    public function canceled(): static
    {
        return $this->state(['status' => ScheduleStatus::Canceled, 'next_run_at' => null]);
    }

    /** Due now — next_run_at today, start_date yesterday (default starts today). */
    public function due(): static
    {
        return $this->state([
            'start_date' => now()->subDay()->toDateString(),
            'next_run_at' => now()->toDateString(),
        ]);
    }

    /** start_date + next_run_at a week back — catch-up window materializes several occurrences. */
    public function startsInPast(): static
    {
        return $this->state([
            'start_date' => now()->subDays(7)->toDateString(),
            'next_run_at' => now()->subDays(7)->toDateString(),
        ]);
    }
}
```

Add `use App\Models\User;` to the imports.

`database/factories/TransactionScheduleRunFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\RunStatus;
use App\Models\TransactionSchedule;
use App\Models\TransactionScheduleRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionScheduleRun>
 */
class TransactionScheduleRunFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (TransactionScheduleRun $run): void {
            $run->user_id ??= $run->schedule->user_id;
        });
    }

    public function definition(): array
    {
        return [
            'schedule_id' => TransactionSchedule::factory(),
            'user_id' => null,
            'occurrence_date' => now()->toDateString(),
            'status' => RunStatus::Pending,
            'skip_reason' => null,
            'transaction_id' => null,
            'processed_at' => null,
        ];
    }
}
```

---

### Task 3: `TransactionScheduleService` — CRUD, lifecycle, read projections

**Complexity:** Medium

**Files:**
- Create: `app/Services/TransactionScheduleService.php`
- Test: `tests/Integration/Services/TransactionScheduleServiceTest.php`

**Interfaces:**
- Consumes: models + enums (Task 2), `ScheduleFrequency::nextOccurrence` (Task 1), `AccountService::getAccountIdsByUser(User): Collection`, `TransactionTemplateService::getTemplatesByUser(User): Collection`.
- Produces:
  - Static reads: `getSchedulesByUser(User $user): Collection` (template + lastRun eager, `pending_runs_count`), `getSchedulableTemplates(User $user): Collection`, `getPendingRuns(User $user): Collection`, `getUpcomingOccurrences(User $user, int $days = 7): array` — list of `['date' => string, 'template' => TransactionTemplate, 'requires_confirmation' => bool]` sorted by date.
  - Instance writes: `create(User $creator, array $data): TransactionSchedule`, `update(TransactionSchedule $schedule, array $data): TransactionSchedule` (always reactivates → Active), `delete(TransactionSchedule $schedule): void`, `pause(...)`, `resume(...)`, `cancel(...)` (flips pendings to `Skipped(canceled)`).
  - `$data` shape (from `SaveTransactionScheduleRequest::schedulePayload()`, Task 6): `frequency: string, params: ?array, start_date: string, ends_at: ?string, max_runs: ?int, requires_confirmation: bool` (+ `template_id` on create only).

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Enums\RunStatus;
use App\Enums\ScheduleFrequency;
use App\Enums\ScheduleStatus;
use App\Enums\SkipReason;
use App\Models\TransactionSchedule;
use App\Models\TransactionScheduleRun;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\TransactionScheduleService;
use Carbon\CarbonImmutable;

it('creates an active schedule with next_run_at on its first occurrence', function (): void {
    CarbonImmutable::setTestNow('2026-11-02');
    $user = User::factory()->create();
    $template = TransactionTemplate::factory()->create(['user_id' => $user->id]);

    $schedule = resolve(TransactionScheduleService::class)->create($user, [
        'template_id' => $template->id,
        'frequency' => ScheduleFrequency::Monthly->value,
        'params' => ['day_of_month' => 15],
        'start_date' => '2026-11-15',
        'ends_at' => null,
        'max_runs' => null,
        'requires_confirmation' => true,
    ]);

    expect($schedule->status)->toBe(ScheduleStatus::Active)
        ->and($schedule->next_run_at?->toDateString())->toBe('2026-11-15')
        ->and($schedule->requires_confirmation)->toBeTrue();

    CarbonImmutable::setTestNow();
});

it('completes a once schedule immediately when its single date is the only occurrence', function (): void {
    CarbonImmutable::setTestNow('2026-11-02');
    $user = User::factory()->create();
    $template = TransactionTemplate::factory()->create(['user_id' => $user->id]);

    $schedule = resolve(TransactionScheduleService::class)->create($user, [
        'template_id' => $template->id,
        'frequency' => ScheduleFrequency::Once->value,
        'params' => null,
        'start_date' => '2026-11-28',
        'ends_at' => null,
        'max_runs' => null,
        'requires_confirmation' => false,
    ]);

    // Not completed yet — its single occurrence is still ahead.
    expect($schedule->status)->toBe(ScheduleStatus::Active)
        ->and($schedule->next_run_at?->toDateString())->toBe('2026-11-28');

    CarbonImmutable::setTestNow();
});

it('completes on create when max_runs is already consumed', function (): void {
    CarbonImmutable::setTestNow('2026-11-02');
    $user = User::factory()->create();

    $schedule = TransactionSchedule::factory()->forUser($user->id)->create(['max_runs' => 1]);
    TransactionScheduleRun::factory()->for($schedule)->create();

    $updated = resolve(TransactionScheduleService::class)->update($schedule, [
        'frequency' => ScheduleFrequency::Daily->value,
        'params' => null,
        'start_date' => '2026-11-01',
        'ends_at' => null,
        'max_runs' => 1,
        'requires_confirmation' => false,
    ]);

    expect($updated->status)->toBe(ScheduleStatus::Completed)
        ->and($updated->next_run_at)->toBeNull();

    CarbonImmutable::setTestNow();
});

it('pauses without touching pendings, resumes strictly after today', function (): void {
    CarbonImmutable::setTestNow('2026-11-10');
    $user = User::factory()->create();
    $service = resolve(TransactionScheduleService::class);

    $schedule = TransactionSchedule::factory()->forUser($user->id)->monthlyOnDates()->create([
        'start_date' => '2026-11-01',
        'next_run_at' => '2026-11-15',
    ]);
    TransactionScheduleRun::factory()->for($schedule)->create(['occurrence_date' => '2026-11-01']);

    $paused = $service->pause($schedule);
    expect($paused->status)->toBe(ScheduleStatus::Paused)
        ->and($schedule->runs()->where('status', RunStatus::Pending)->count())->toBe(1);

    $resumed = $service->resume($paused);
    // Today is Nov 10; the 15th is the next occurrence AFTER today.
    expect($resumed->status)->toBe(ScheduleStatus::Active)
        ->and($resumed->next_run_at?->toDateString())->toBe('2026-11-15');

    CarbonImmutable::setTestNow();
});

it('canceling skips remaining pendings with the canceled reason', function (): void {
    $user = User::factory()->create();
    $schedule = TransactionSchedule::factory()->forUser($user->id)->create();
    TransactionScheduleRun::factory()->for($schedule)->create(['occurrence_date' => '2026-11-01']);
    TransactionScheduleRun::factory()->for($schedule)->create(['occurrence_date' => '2026-11-05']);

    $canceled = resolve(TransactionScheduleService::class)->cancel($schedule);

    expect($canceled->status)->toBe(ScheduleStatus::Canceled)
        ->and($canceled->next_run_at)->toBeNull()
        ->and($schedule->runs()->where('status', RunStatus::Skipped)->where('skip_reason', SkipReason::Canceled)->count())->toBe(2);
});

it('updating reactivates a canceled row and recomputes from today', function (): void {
    CarbonImmutable::setTestNow('2026-11-10');
    $user = User::factory()->create();
    $schedule = TransactionSchedule::factory()->forUser($user->id)->canceled()->create();

    $updated = resolve(TransactionScheduleService::class)->update($schedule, [
        'frequency' => ScheduleFrequency::Daily->value,
        'params' => null,
        'start_date' => '2026-11-10',
        'ends_at' => null,
        'max_runs' => null,
        'requires_confirmation' => false,
    ]);

    expect($updated->status)->toBe(ScheduleStatus::Active)
        ->and($updated->next_run_at?->toDateString())->toBe('2026-11-10');

    CarbonImmutable::setTestNow();
});

it('lists schedules with pending counts and eager template', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $mine = TransactionSchedule::factory()->forUser($user->id)->create();
    TransactionScheduleRun::factory()->for($mine)->create();
    TransactionSchedule::factory()->forUser($other->id)->create();

    $result = TransactionScheduleService::getSchedulesByUser($user);

    expect($result)->toHaveCount(1)
        ->and($result->first()->pending_runs_count)->toBe(1)
        ->and($result->first()->template->id)->toBe($mine->template_id);
});

it('offers only templates with a selectable default source as schedulable', function (): void {
    $user = User::factory()->create();

    $withAccount = TransactionTemplate::factory()->forAccountOf($user)->create();
    TransactionTemplate::factory()->create(['user_id' => $user->id]); // no default account
    TransactionTemplate::factory()->create(); // another user's, with account

    $schedulable = TransactionScheduleService::getSchedulableTemplates($user);

    expect($schedulable->pluck('id'))->toContain($withAccount->id)
        ->and($schedulable)->toHaveCount(1);
});

it('projects upcoming occurrences from frequencies without storing them', function (): void {
    CarbonImmutable::setTestNow('2026-11-10');
    $user = User::factory()->create();

    TransactionSchedule::factory()->forUser($user->id)->monthlyOnDates()->create([
        'start_date' => '2026-11-01',
        'next_run_at' => '2026-11-15',
    ]);

    $upcoming = TransactionScheduleService::getUpcomingOccurrences($user, 7);

    // Nov 15 lands in the window; Dec 1 does not.
    expect($upcoming)->toHaveCount(1)
        ->and($upcoming[0]['date'])->toBe('2026-11-15')
        ->and($upcoming[0]['requires_confirmation'])->toBeFalse();

    CarbonImmutable::setTestNow();
});
```

Note: the `forAccountOf` template-factory state does not exist yet — add it in this task to `TransactionTemplateFactory`:

```php
    /** Default source account owned by the given user — the schedulable shape. */
    public function forAccountOf(User $user): static
    {
        return $this->state([
            'user_id' => $user->id,
            'account_id' => Account::factory()->create(['owner_id' => $user->id])->id,
        ]);
    }
```

- [ ] **Step 2: Implement the service**

```php
<?php

namespace App\Services;

use App\Enums\RunStatus;
use App\Enums\ScheduleStatus;
use App\Enums\SkipReason;
use App\Models\TransactionSchedule;
use App\Models\TransactionScheduleRun;
use App\Models\TransactionTemplate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Schedule CRUD, lifecycle transitions, and read projections. Static
 * reads and instance writes mirror TransactionTemplateService. The
 * materializing engine lives in ScheduleProcessor; occurrence math lives
 * on ScheduleFrequency. No events, no caching.
 */
final readonly class TransactionScheduleService
{
    /** Management list: newest first, template + last run eager for display. */
    public static function getSchedulesByUser(User $user): Collection
    {
        return TransactionSchedule::query()
            ->where('user_id', $user->id)
            ->with(['template.account:id,name', 'lastRun'])
            ->withCount(['runs as pending_runs_count' => fn ($query) => $query->where('status', RunStatus::Pending)])
            ->latest('id')
            ->get();
    }

    /** Templates that may be scheduled: owned, with a selectable default source account. */
    public static function getSchedulableTemplates(User $user): Collection
    {
        $selectableIds = AccountService::getAccountIdsByUser($user);

        return TransactionTemplateService::getTemplatesByUser($user)
            ->filter(fn (TransactionTemplate $template): bool => $template->account_id !== null
                && $selectableIds->contains($template->account_id));
    }

    /** Confirmation queue for the dashboard due card. */
    public static function getPendingRuns(User $user): Collection
    {
        return TransactionScheduleRun::query()
            ->where('user_id', $user->id)
            ->where('status', RunStatus::Pending)
            ->with(['schedule.template'])
            ->orderBy('occurrence_date')
            ->get();
    }

    /**
     * Read-only projection of the next $days of occurrences — computed
     * from frequencies, never stored. Honors ends_at; ignores pause
     * exclusion (paused schedules have no live next_run_at anyway).
     *
     * @return list<array{date: string, template: TransactionTemplate, requires_confirmation: bool}>
     */
    public static function getUpcomingOccurrences(User $user, int $days = 7): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $windowEnd = $today->addDays($days);

        $upcoming = [];

        TransactionSchedule::query()
            ->where('user_id', $user->id)
            ->where('status', ScheduleStatus::Active)
            ->with('template')
            ->get()
            ->each(function (TransactionSchedule $schedule) use ($today, $windowEnd, &$upcoming): void {
                $cursor = $schedule->frequency->nextOccurrence($today->subDay(), $schedule->start_date, $schedule->params);

                while ($cursor !== null
                    && $cursor->lessThanOrEqualTo($windowEnd)
                    && ($schedule->ends_at === null || $cursor->lessThanOrEqualTo($schedule->ends_at))) {
                    $upcoming[] = [
                        'date' => $cursor->toDateString(),
                        'template' => $schedule->template,
                        'requires_confirmation' => $schedule->requires_confirmation,
                    ];

                    $cursor = $schedule->frequency->nextOccurrence($cursor, $schedule->start_date, $schedule->params);
                }
            });

        return collect($upcoming)->sortBy('date')->values()->all();
    }

    public function create(User $creator, array $data): TransactionSchedule
    {
        return DB::transaction(function () use ($creator, $data): TransactionSchedule {
            $schedule = TransactionSchedule::create([
                ...$data,
                'user_id' => $creator->id,
                'status' => ScheduleStatus::Active->value,
            ]);

            $schedule->advanceNextRun(CarbonImmutable::now()->startOfDay()->subDay());
            $schedule->save();

            return $schedule->fresh();
        });
    }

    /** Field updates always reactivate — this is the reactivation path for canceled rows. */
    public function update(TransactionSchedule $schedule, array $data): TransactionSchedule
    {
        return DB::transaction(function () use ($schedule, $data): TransactionSchedule {
            $schedule->update([
                ...$data,
                'status' => ScheduleStatus::Active->value,
            ]);

            $schedule->advanceNextRun(CarbonImmutable::now()->startOfDay()->subDay());
            $schedule->save();

            return $schedule->fresh();
        });
    }

    public function delete(TransactionSchedule $schedule): void
    {
        $schedule->delete();
    }

    /** Future occurrences stop generating; existing pendings survive — the decisions are still owed. */
    public function pause(TransactionSchedule $schedule): TransactionSchedule
    {
        $schedule->update(['status' => ScheduleStatus::Paused->value]);

        return $schedule->fresh();
    }

    /** Resume = first occurrence strictly after today; the paused window is never backfilled. */
    public function resume(TransactionSchedule $schedule): TransactionSchedule
    {
        return DB::transaction(function () use ($schedule): TransactionSchedule {
            $schedule->update(['status' => ScheduleStatus::Active->value]);

            $schedule->advanceNextRun(CarbonImmutable::now()->startOfDay());
            $schedule->save();

            return $schedule->fresh();
        });
    }

    /** Future occurrences stop; remaining pendings flip to skipped(canceled); history retained. */
    public function cancel(TransactionSchedule $schedule): TransactionSchedule
    {
        return DB::transaction(function () use ($schedule): TransactionSchedule {
            $schedule->runs()
                ->where('status', RunStatus::Pending)
                ->update([
                    'status' => RunStatus::Skipped->value,
                    'skip_reason' => SkipReason::Canceled->value,
                    'processed_at' => now(),
                ]);

            $schedule->update([
                'status' => ScheduleStatus::Canceled->value,
                'next_run_at' => null,
            ]);

            return $schedule->fresh();
        });
    }
}
```

---

### Task 4: `ScheduleProcessor` — the materializing engine

**Complexity:** Medium

**Files:**
- Create: `app/Services/ScheduleProcessor.php`
- Test: `tests/Integration/Services/ScheduleProcessorTest.php`

**Interfaces:**
- Consumes: models + enums (Task 2), `ScheduleFrequency` (Task 1), `TransactionService::create(User, TransactionData): Transaction`, `TransferService::create(User, TransferData): Transfer`, `AccountService::getAccountIdsByUser(User): Collection`. DTO constructors: `TransactionData(account_id: int, type: TransactionType, amount: float, transaction_date: string, category_id: ?Category, description: ?string, tags: ?array, ...)` and `TransferData(account_id: int, destination_account_id: int, amount: float, transaction_date: string, fee_amount: ?float, description: ?string, tags: ?array)`.
- Produces: `ScheduleProcessor::GRACE_DAYS = 7`; `processDue(?User $user = null): array{posted: int, pending: int, skipped: int}`; `postRun(TransactionScheduleRun $run, RunStatus $as = RunStatus::Posted): ?Transaction` (public — the confirm endpoint reuses it; null = template accounts unselectable and the run already skipped `unavailable_account`).

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Enums\Category;
use App\Enums\RunStatus;
use App\Enums\ScheduleStatus;
use App\Enums\SkipReason;
use App\Models\Transaction;
use App\Models\TransactionSchedule;
use App\Models\TransactionScheduleRun;
use App\Models\User;
use App\Services\ScheduleProcessor;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    CarbonImmutable::setTestNow(new CarbonImmutable('2026-11-10')->startOfDay());
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('auto-posts a due occurrence dated its occurrence date, not today', function (): void {
    $user = User::factory()->create();
    $schedule = TransactionSchedule::factory()->forUser($user->id)->due()->create([
        'start_date' => '2026-11-10',
        'next_run_at' => '2026-11-10',
        'params' => ['day_of_month' => 10],
    ]);

    $result = resolve(ScheduleProcessor::class)->processDue($user);

    $run = $schedule->runs()->first();
    $transaction = Transaction::query()->first();

    expect($result)->toBe(['posted' => 1, 'pending' => 0, 'skipped' => 0])
        ->and($run->status)->toBe(RunStatus::Posted)
        ->and($run->transaction_id)->toBe($transaction->id)
        ->and($transaction->transaction_date->toDateString())->toBe('2026-11-10')
        ->and($transaction->category_id)->toBe(Category::DiningOut) // template default
        ->and($schedule->fresh()->next_run_at?->toDateString())->toBe('2026-12-10');
});

it('queues a confirmation occurrence as pending, posting nothing', function (): void {
    $user = User::factory()->create();
    $schedule = TransactionSchedule::factory()->forUser($user->id)->due()->confirmation()->create();

    $result = resolve(ScheduleProcessor::class)->processDue($user);

    expect($result)->toBe(['posted' => 0, 'pending' => 1, 'skipped' => 0])
        ->and($schedule->runs()->first()->status)->toBe(RunStatus::Pending)
        ->and(Transaction::count())->toBe(0);
});

it('silently skips confirm occurrences already past grace at materialization', function (): void {
    // Due Nov 1; grace (7) expired Nov 9; today Nov 10 — must never surface.
    $user = User::factory()->create();
    $schedule = TransactionSchedule::factory()->forUser($user->id)->confirmation()->create([
        'start_date' => '2026-11-01',
        'next_run_at' => '2026-11-01',
    ]);

    $result = resolve(ScheduleProcessor::class)->processDue($user);

    $run = $schedule->runs()->first();

    expect($result)->toBe(['posted' => 0, 'pending' => 0, 'skipped' => 1])
        ->and($run->status)->toBe(RunStatus::Skipped)
        ->and($run->skip_reason)->toBe(SkipReason::GraceExpired);
});

it('keeps a confirm occurrence pending on the last grace day', function (): void {
    // Due Nov 3; today Nov 10 == occurrence + 7 — still actionable.
    $user = User::factory()->create();
    TransactionSchedule::factory()->forUser($user->id)->confirmation()->create([
        'start_date' => '2026-11-03',
        'next_run_at' => '2026-11-03',
    ]);

    $result = resolve(ScheduleProcessor::class)->processDue($user);

    expect($result)->toBe(['posted' => 0, 'pending' => 1, 'skipped' => 0]);
});

it('catches up through every missed occurrence in one run', function (): void {
    // Daily, missed Nov 3..10 = 8 occurrences.
    $user = User::factory()->create();
    $schedule = TransactionSchedule::factory()->forUser($user->id)->daily()->startsInPast()->create([
        'start_date' => '2026-11-03',
        'next_run_at' => '2026-11-03',
    ]);

    $result = resolve(ScheduleProcessor::class)->processDue($user);

    expect($result['posted'])->toBe(8)
        ->and($schedule->runs()->count())->toBe(8)
        ->and($schedule->fresh()->next_run_at?->toDateString())->toBe('2026-11-11');
});

it('completes a once schedule after its single occurrence', function (): void {
    $user = User::factory()->create();
    $schedule = TransactionSchedule::factory()->forUser($user->id)->once()->create([
        'start_date' => '2026-11-05',
        'next_run_at' => '2026-11-05',
    ]);

    resolve(ScheduleProcessor::class)->processDue($user);

    expect($schedule->fresh()->status)->toBe(ScheduleStatus::Completed)
        ->and($schedule->runs()->count())->toBe(1);
});

it('stops at max_runs occurrences consumed', function (): void {
    $user = User::factory()->create();
    $schedule = TransactionSchedule::factory()->forUser($user->id)->daily()->create([
        'start_date' => '2026-11-08',
        'next_run_at' => '2026-11-08',
        'max_runs' => 2,
    ]);

    $result = resolve(ScheduleProcessor::class)->processDue($user);

    // Nov 8, 9, 10 are due but only 2 occurrences are allowed.
    expect($result['posted'])->toBe(2)
        ->and($schedule->fresh()->status)->toBe(ScheduleStatus::Completed);
});

it('does not materialize occurrences past ends_at', function (): void {
    $user = User::factory()->create();
    $schedule = TransactionSchedule::factory()->forUser($user->id)->daily()->create([
        'start_date' => '2026-11-08',
        'next_run_at' => '2026-11-08',
        'ends_at' => '2026-11-09',
    ]);

    $result = resolve(ScheduleProcessor::class)->processDue($user);

    expect($result['posted'])->toBe(2) // Nov 8 + 9; ends_at inclusive
        ->and($schedule->fresh()->status)->toBe(ScheduleStatus::Completed);
});

it('skips with unavailable_account when the template source is archived', function (): void {
    $user = User::factory()->create();
    $template = TransactionTemplate::factory()->forAccountOf($user)->create();
    $template->account->update(['archived_at' => now()]);

    $schedule = TransactionSchedule::factory()->forUser($user->id)->forTemplate($template)->due()->create();

    $result = resolve(ScheduleProcessor::class)->processDue($user);
    $run = $schedule->runs()->first();

    expect($result)->toBe(['posted' => 0, 'pending' => 0, 'skipped' => 1])
        ->and($run->skip_reason)->toBe(SkipReason::UnavailableAccount)
        ->and(Transaction::count())->toBe(0);
});

it('books a transfer unit with fee from a transfer template', function (): void {
    $user = User::factory()->create();
    $template = TransactionTemplate::factory()->transfer()->forAccountOf($user)->create([
        'user_id' => $user->id,
        'destination_account_id' => Account::factory()->create(['owner_id' => $user->id])->id,
        'fee_amount' => 5_500,
    ]);

    $schedule = TransactionSchedule::factory()->forUser($user->id)->forTemplate($template)->due()->create();

    $result = resolve(ScheduleProcessor::class)->processDue($user);

    // Source outflow + destination inflow + fee expense.
    expect($result['posted'])->toBe(1)
        ->and(Transaction::count())->toBe(3)
        ->and(Transaction::where('category_id', Category::AdminBankFees)->count())->toBe(1)
        ->and($schedule->runs()->first()->transaction_id)->not->toBeNull();
});

it('is idempotent — processing twice never duplicates runs', function (): void {
    $user = User::factory()->create();
    $schedule = TransactionSchedule::factory()->forUser($user->id)->due()->create();

    $processor = resolve(ScheduleProcessor::class);
    $processor->processDue($user);
    $result = $processor->processDue($user);

    expect($result)->toBe(['posted' => 0, 'pending' => 0, 'skipped' => 0])
        ->and($schedule->runs()->count())->toBe(1);
});

it('ignores paused schedules', function (): void {
    $user = User::factory()->create();
    TransactionSchedule::factory()->forUser($user->id)->paused()->due()->create();

    $result = resolve(ScheduleProcessor::class)->processDue($user);

    expect($result)->toBe(['posted' => 0, 'pending' => 0, 'skipped' => 0]);
});

it('postRun confirms against the live template with the Confirmed status', function (): void {
    $user = User::factory()->create();
    $schedule = TransactionSchedule::factory()->forUser($user->id)->confirmation()->create([
        'start_date' => '2026-11-10',
        'next_run_at' => '2026-11-11',
    ]);
    $run = TransactionScheduleRun::factory()->for($schedule)->create(['occurrence_date' => '2026-11-10']);

    $transaction = resolve(ScheduleProcessor::class)->postRun($run, RunStatus::Confirmed);

    expect($transaction)->not->toBeNull()
        ->and($run->fresh()->status)->toBe(RunStatus::Confirmed)
        ->and($run->fresh()->transaction_id)->toBe($transaction->id)
        ->and($transaction->transaction_date->toDateString())->toBe('2026-11-10');
});
```

Two factory helpers referenced above — add to `TransactionScheduleFactory` in this task:

```php
    /** Bind to an explicit template (ownership test setups). */
    public function forTemplate(TransactionTemplate $template): static
    {
        return $this->state([
            'template_id' => $template->id,
            'user_id' => $template->user_id,
        ]);
    }
```

And to `TransactionTemplateFactory` (transfer state already exists; `forAccountOf` came from Task 3):

```php
    /** Transfer shape fully owned by one user: source + destination. */
    public function transferFor(User $user): static
    {
        return $this->state([
            'user_id' => $user->id,
            'account_id' => Account::factory()->create(['owner_id' => $user->id])->id,
            'type' => TransactionType::Transfer,
            'category_id' => null,
            'destination_account_id' => Account::factory()->create(['owner_id' => $user->id])->id,
            'amount' => 1_000_000,
        ]);
    }
```

(The transfer test above then simplifies to `TransactionTemplate::factory()->transferFor($user)->create(['fee_amount' => 5_500])` — use that form.)

- [ ] **Step 2: Implement the processor**

```php
<?php

namespace App\Services;

use App\Data\Transaction\TransactionData;
use App\Data\Transaction\TransferData;
use App\Enums\RunStatus;
use App\Enums\ScheduleStatus;
use App\Enums\SkipReason;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\TransactionSchedule;
use App\Models\TransactionScheduleRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The materializing engine. Finds active schedules whose next_run_at has
 * arrived and walks every missed occurrence through today (catch-up),
 * recording one ledger row per occurrence and posting through the same
 * services a form submit uses — so observers, balances and validation
 * behave identically. Idempotent by the (schedule_id, occurrence_date)
 * unique key; safe under concurrent triggers (middleware + command).
 */
final readonly class ScheduleProcessor
{
    public const int GRACE_DAYS = 7;

    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly TransferService $transferService,
    ) {}

    /** @return array{posted: int, pending: int, skipped: int} */
    public function processDue(?User $user = null): array
    {
        $counts = ['posted' => 0, 'pending' => 0, 'skipped' => 0];
        $today = CarbonImmutable::now()->startOfDay();

        TransactionSchedule::query()
            ->where('status', ScheduleStatus::Active)
            ->whereNotNull('next_run_at')
            ->whereDate('next_run_at', '<=', $today->toDateString())
            ->when($user !== null, fn ($query) => $query->where('user_id', $user->id))
            ->with('template')
            ->get()
            ->each(function (TransactionSchedule $schedule) use ($today, &$counts): void {
                $result = $this->processSchedule($schedule, $today);

                $counts['posted'] += $result['posted'];
                $counts['pending'] += $result['pending'];
                $counts['skipped'] += $result['skipped'];
            });

        return $counts;
    }

    /**
     * Post a run's transaction from the LIVE template, dated the
     * occurrence date. Null (with the run already skipped
     * unavailable_account) when the template's accounts are no longer
     * selectable — the confirm endpoint surfaces that as a flash error.
     */
    public function postRun(TransactionScheduleRun $run, RunStatus $as = RunStatus::Posted): ?Transaction
    {
        $schedule = $run->schedule;
        $template = $schedule->template;
        $selectableIds = AccountService::getAccountIdsByUser($schedule->user);

        if ($template->account_id === null || ! $selectableIds->contains($template->account_id)) {
            $this->skipUnavailable($run);

            return null;
        }

        if ($template->type === TransactionType::Transfer) {
            if ($template->destination_account_id === null
                || ! $selectableIds->contains($template->destination_account_id)) {
                $this->skipUnavailable($run);

                return null;
            }

            $transfer = $this->transferService->create($schedule->user, new TransferData(
                account_id: $template->account_id,
                destination_account_id: $template->destination_account_id,
                amount: (float) $template->amount,
                transaction_date: $run->occurrence_date->toDateString(),
                fee_amount: $template->fee_amount !== null ? (float) $template->fee_amount : null,
                description: $template->description,
                tags: $template->tags?->toArray(),
            ));

            $transaction = $transfer->sourceTransaction;
            $run->update([
                'status' => $as,
                'transaction_id' => $transaction?->id,
                'processed_at' => now(),
            ]);

            return $transaction;
        }

        if ($template->category_id === null) {
            // Enum drift guard — the prune projection nulls removed slugs.
            $this->skipUnavailable($run);

            return null;
        }

        $transaction = $this->transactionService->create($schedule->user, new TransactionData(
            account_id: $template->account_id,
            type: $template->type,
            amount: (float) $template->amount,
            transaction_date: $run->occurrence_date->toDateString(),
            category_id: $template->category_id,
            description: $template->description,
            tags: $template->tags?->toArray(),
        ));

        $run->update([
            'status' => $as,
            'transaction_id' => $transaction->id,
            'processed_at' => now(),
        ]);

        return $transaction;
    }

    /** @return array{posted: int, pending: int, skipped: int} */
    private function processSchedule(TransactionSchedule $schedule, CarbonImmutable $today): array
    {
        return DB::transaction(function () use ($schedule, $today): array {
            $counts = ['posted' => 0, 'pending' => 0, 'skipped' => 0];
            $runCount = $schedule->runs()->count();
            $lastOccurrence = null;
            $cursor = $schedule->next_run_at->toImmutable();

            while ($cursor->lessThanOrEqualTo($today)) {
                if ($schedule->ends_at !== null && $cursor->isAfter($schedule->ends_at)) {
                    break;
                }

                if ($schedule->max_runs !== null && $runCount >= $schedule->max_runs) {
                    break;
                }

                $run = $this->recordRun($schedule, $cursor);

                if ($run !== null) {
                    $runCount++;
                    $lastOccurrence = $cursor;

                    if ($schedule->requires_confirmation) {
                        // Grace: pending expires when today > occurrence + GRACE_DAYS —
                        // an occurrence discovered already-late never surfaces.
                        if ($cursor->addDays(self::GRACE_DAYS)->lessThan($today)) {
                            $run->update([
                                'status' => RunStatus::Skipped->value,
                                'skip_reason' => SkipReason::GraceExpired->value,
                                'processed_at' => now(),
                            ]);
                            $counts['skipped']++;
                        } else {
                            $counts['pending']++;
                        }
                    } elseif ($this->postRun($run) !== null) {
                        $counts['posted']++;
                    } else {
                        $counts['skipped']++;
                    }
                }

                $next = $schedule->frequency->nextOccurrence($cursor, $schedule->start_date, $schedule->params);

                if ($next === null) {
                    break;
                }

                $cursor = $next;
            }

            $schedule->advanceNextRun($today, $runCount);

            if ($lastOccurrence !== null) {
                $schedule->last_run_at = $lastOccurrence->toDateString();
            }

            $schedule->save();

            return $counts;
        });
    }

    /** Insert the ledger row; null when a concurrent worker already recorded it. */
    private function recordRun(TransactionSchedule $schedule, CarbonImmutable $occurrence): ?TransactionScheduleRun
    {
        try {
            return $schedule->runs()->create([
                'user_id' => $schedule->user_id,
                'occurrence_date' => $occurrence->toDateString(),
                'status' => RunStatus::Pending->value,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    private function skipUnavailable(TransactionScheduleRun $run): void
    {
        $run->update([
            'status' => RunStatus::Skipped->value,
            'skip_reason' => SkipReason::UnavailableAccount->value,
            'processed_at' => now(),
        ]);
    }
}
```

---

### Task 5: Triggers — `schedules:process` command + lazy middleware

**Complexity:** Low

**Files:**
- Create: `app/Console/Commands/ProcessDueSchedulesCommand.php`, `app/Http/Middleware/ProcessDueSchedules.php`
- Modify: `routes/console.php` (register on Schedule), `bootstrap/app.php` (append middleware)
- Modify: `tests/Pest.php` (register the `module:schedule` groups)
- Test: `tests/Feature/App/Schedule/ScheduleMaterializationTest.php` (new domain dir, the `Feature/App/Template/` precedent)

**Interfaces:**
- Consumes: `ScheduleProcessor::processDue(?User $user): array{posted, pending, skipped}` (Task 4).
- Produces: artisan command `schedules:process` (exit `self::SUCCESS`, prints counts); middleware `ProcessDueSchedules` appended to the `web` group after `HandleInertiaRequests` — authed requests with due schedules materialize before the page renders, failures are reported and swallowed.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Enums\RunStatus;
use App\Models\Transaction;
use App\Models\TransactionSchedule;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

beforeEach(function (): void {
    Route::get('/_probe/schedules', fn () => Inertia::render('app/dashboard'))->middleware('web');
});

it('materializes due schedules lazily on an authed page load', function (): void {
    $user = User::factory()->create();
    TransactionSchedule::factory()->forUser($user->id)->due()->create();

    $this->actingAs($user)->get('/_probe/schedules');

    expect(Transaction::count())->toBe(1)
        ->and(TransactionScheduleRun::where('status', RunStatus::Posted)->count())->toBe(1);
});

it('skips the query work for guests and clean accounts', function (): void {
    $user = User::factory()->create();

    // Authed but nothing due — page renders, nothing materializes.
    $this->actingAs($user)->get('/_probe/schedules')->assertOk();
    expect(Transaction::count())->toBe(0);

    // Guest — no user, no processing, no crash.
    $this->get('/_probe/schedules')->assertOk();
});

it('processes every user via the artisan command', function (): void {
    $one = User::factory()->create();
    $two = User::factory()->create();
    TransactionSchedule::factory()->forUser($one->id)->due()->create();
    TransactionSchedule::factory()->forUser($two->id)->due()->create();

    $this->artisan('schedules:process')->assertSuccessful();

    expect(Transaction::count())->toBe(2);
});
```

- [ ] **Step 2: Implement the command**

```php
<?php

namespace App\Console\Commands;

use App\Services\ScheduleProcessor;
use Illuminate\Console\Command;

final class ProcessDueSchedulesCommand extends Command
{
    protected $signature = 'schedules:process';

    protected $description = 'Materialize due scheduled transaction occurrences (auto-post or queue for confirmation)';

    public function __construct(private readonly ScheduleProcessor $processor)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->processor->processDue();

        $this->info(sprintf(
            'Posted: %d  Pending: %d  Skipped: %d',
            $result['posted'],
            $result['pending'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
```

- [ ] **Step 3: Implement the middleware**

```php
<?php

namespace App\Http\Middleware;

use App\Enums\ScheduleStatus;
use App\Models\TransactionSchedule;
use App\Services\ScheduleProcessor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * Lazy trigger for schedule materialization — the local/Herded reality
 * where no cron runs. One indexed exists-check per authed request; due
 * schedules materialize before the page renders. A materialization
 * failure is reported and swallowed: schedules must never 500 a page.
 */
final class ProcessDueSchedules
{
    public function __construct(private readonly ScheduleProcessor $processor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && TransactionSchedule::query()
            ->where('user_id', $user->id)
            ->where('status', ScheduleStatus::Active)
            ->whereNotNull('next_run_at')
            ->whereDate('next_run_at', '<=', now()->toDateString())
            ->exists()) {
            try {
                $this->processor->processDue($user);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Register both**

`routes/console.php` — add inside the existing file (after the telescope block; unconditional — the engine is idempotent):

```php
Schedule::command('schedules:process')->everyMinute()->withoutOverlapping();
```

`bootstrap/app.php` — extend the web append list:

```php
        $middleware->web(append: [
            HandleInertiaRequests::class,
            ProcessDueSchedules::class,
        ]);
```

with `use App\Http\Middleware\ProcessDueSchedules;` added to the imports.

- [ ] **Step 5: Register the schedule module groups**

In `tests/Pest.php`, after the existing `module:` integration mappings (all referenced files exist by this task), add:

```php
pest()->group('module:schedule')->in('Feature/App/Schedule');
pest()->group('module:schedule')->in(
    'Integration/Services/TransactionScheduleServiceTest.php',
    'Integration/Services/ScheduleProcessorTest.php',
);
```

---

### Task 6: Validation, policies, settings controller, routes

**Complexity:** Medium

**Files:**
- Create: `app/Http/Requests/SaveTransactionScheduleRequest.php`
- Create: `app/Policies/TransactionSchedulePolicy.php`
- Create: `app/Http/Controllers/Settings/TransactionScheduleController.php`
- Modify: `routes/settings.php` (resource + action routes)
- Test: `tests/Feature/App/Settings/TransactionScheduleTest.php`

**Interfaces:**
- Consumes: `TransactionScheduleService` writes (Task 3); page `app/settings/transaction-schedules/{index,form}` (frontend plan).
- Produces: routes `transaction-schedules.{index,create,store,edit,update,pause,resume,cancel,destroy}` under `settings/transaction-schedules`; request `schedulePayload(): array` (`frequency`, `params`, `start_date`, `ends_at`, `max_runs`, `requires_confirmation` — `template_id` via `validated('template_id')` on store); policy methods `viewAny/create/view/update/pause/resume/cancel/delete` owner-scoped.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Enums\ScheduleFrequency;
use App\Enums\ScheduleStatus;
use App\Models\TransactionSchedule;
use App\Models\TransactionTemplate;
use App\Models\User;

function schedulePayload(array $overrides = []): array
{
    return [
        'frequency' => ScheduleFrequency::Monthly->value,
        'day_of_month' => 15,
        'start_date' => now()->toDateString(),
        'requires_confirmation' => true,
        ...$overrides,
    ];
}

test('lists only the authenticated user schedules', function (): void {
    $user = User::factory()->create();
    TransactionSchedule::factory()->forUser($user->id)->create();
    TransactionSchedule::factory()->create();

    $this->actingAs($user)->get(route('transaction-schedules.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/settings/transaction-schedules/index')
            ->has('schedules', 1)
        );
});

test('renders the create form with schedulable templates', function (): void {
    $user = User::factory()->create();
    TransactionTemplate::factory()->forAccountOf($user)->create();

    $this->actingAs($user)->get(route('transaction-schedules.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/settings/transaction-schedules/form')
            ->where('schedule', null)
            ->has('templates', 1)
        );
});

test('renders the edit form with the schedule', function (): void {
    $user = User::factory()->create();
    $schedule = TransactionSchedule::factory()->forUser($user->id)->create();

    $this->actingAs($user)->get(route('transaction-schedules.edit', $schedule))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/settings/transaction-schedules/form')
            ->where('schedule.id', $schedule->id)
        );
});

test('stores a schedule and redirects to the index', function (): void {
    $user = User::factory()->create();
    $template = TransactionTemplate::factory()->forAccountOf($user)->create();

    $this->actingAs($user)->post(route('transaction-schedules.store'), [
        'template_id' => $template->id,
        ...schedulePayload(),
    ])->assertRedirect(route('transaction-schedules.index'));

    expect(TransactionSchedule::query()->where('template_id', $template->id)->exists())->toBeTrue();
});

test('rejects storing a second schedule for the same template', function (): void {
    $user = User::factory()->create();
    $template = TransactionTemplate::factory()->forAccountOf($user)->create();
    TransactionSchedule::factory()->forUser($user->id)->forTemplate($template)->create();

    $this->actingAs($user)->post(route('transaction-schedules.store'), [
        'template_id' => $template->id,
        ...schedulePayload(),
    ])->assertInvalid('template_id');
});

test('rejects a template without a selectable default account', function (): void {
    $user = User::factory()->create();
    $template = TransactionTemplate::factory()->create(['user_id' => $user->id]); // account_id null

    $this->actingAs($user)->post(route('transaction-schedules.store'), [
        'template_id' => $template->id,
        ...schedulePayload(),
    ])->assertInvalid('template_id');
});

test('rejects a foreign template', function (): void {
    $user = User::factory()->create();
    $template = TransactionTemplate::factory()->forAccountOf(User::factory()->create())->create();

    $this->actingAs($user)->post(route('transaction-schedules.store'), [
        'template_id' => $template->id,
        ...schedulePayload(),
    ])->assertInvalid('template_id');
});

test('rejects a start date before today', function (): void {
    $user = User::factory()->create();
    $template = TransactionTemplate::factory()->forAccountOf($user)->create();

    $this->actingAs($user)->post(route('transaction-schedules.store'), [
        'template_id' => $template->id,
        ...schedulePayload(['start_date' => now()->subDay()->toDateString()]),
    ])->assertInvalid('start_date');
});

test('rejects missing per-frequency parameters', function (string $frequency, string $errorField): void {
    $user = User::factory()->create();
    $template = TransactionTemplate::factory()->forAccountOf($user)->create();

    $this->actingAs($user)->post(route('transaction-schedules.store'), [
        'template_id' => $template->id,
        ...schedulePayload(['frequency' => $frequency, 'day_of_month' => null]),
    ])->assertInvalid($errorField);
})->with([
    'weekly without days' => [ScheduleFrequency::Weekly->value, 'days_of_week'],
    'monthly without day' => [ScheduleFrequency::Monthly->value, 'day_of_month'],
]);

test('rejects feb 30 for yearly', function (): void {
    $user = User::factory()->create();
    $template = TransactionTemplate::factory()->forAccountOf($user)->create();

    $this->actingAs($user)->post(route('transaction-schedules.store'), [
        'template_id' => $template->id,
        ...schedulePayload(['frequency' => ScheduleFrequency::Yearly->value, 'month' => 2, 'day' => 30]),
    ])->assertInvalid('day');
});

test('update ignores template_id and recomputes', function (): void {
    Carbon\CarbonImmutable::setTestNow('2026-11-10');
    $user = User::factory()->create();
    $schedule = TransactionSchedule::factory()->forUser($user->id)->create();

    $this->actingAs($user)->put(route('transaction-schedules.update', $schedule), [
        'template_id' => 999, // ignored — immutable after create
        ...schedulePayload(['frequency' => ScheduleFrequency::Daily->value, 'day_of_month' => null]),
    ])->assertRedirect(route('transaction-schedules.index'));

    $schedule->refresh();
    expect($schedule->template_id)->not->toBe(999)
        ->and($schedule->frequency)->toBe(ScheduleFrequency::Daily);

    Carbon\CarbonImmutable::setTestNow();
});

test('pause, resume and cancel act on own schedules only', function (): void {
    $user = User::factory()->create();
    $foreign = User::factory()->create();
    $own = TransactionSchedule::factory()->forUser($user->id)->create();
    $theirs = TransactionSchedule::factory()->forUser($foreign->id)->create();

    $this->actingAs($user)->post(route('transaction-schedules.pause', $own))->assertRedirect();
    $this->actingAs($user)->post(route('transaction-schedules.pause', $theirs))->assertForbidden();

    $this->actingAs($user)->post(route('transaction-schedules.resume', $own))->assertRedirect();
    $this->actingAs($user)->post(route('transaction-schedules.cancel', $own))->assertRedirect();
    expect($own->fresh()->status)->toBe(ScheduleStatus::Canceled);
});

test('destroy deletes the schedule but not its transactions', function (): void {
    $user = User::factory()->create();
    $schedule = TransactionSchedule::factory()->forUser($user->id)->create();

    $this->actingAs($user)->delete(route('transaction-schedules.destroy', $schedule))
        ->assertRedirect(route('transaction-schedules.index'));

    expect(TransactionSchedule::find($schedule->id))->toBeNull();
});

test('guests are redirected to login', function (): void {
    $this->get(route('transaction-schedules.index'))->assertRedirect(route('auth.login'));
});
```

- [ ] **Step 2: Implement the request**

```php
<?php

namespace App\Http\Requests;

use App\Enums\ScheduleFrequency;
use App\Enums\TransactionType;
use App\Models\TransactionTemplate;
use App\Services\AccountService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Store + update validation for schedules. Per-frequency params arrive
 * flat from the form and are folded into the `params` JSON column by
 * schedulePayload(). template_id is store-only — a schedule's template
 * is immutable after create (update() drops it).
 */
final class SaveTransactionScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schedule = $this->route('schedule');

        return [
            'template_id' => [
                'required',
                'integer',
                Rule::unique('transaction_schedules', 'template_id')->ignore($schedule),
                $this->schedulableTemplate(),
            ],
            'frequency' => ['required', Rule::enum(ScheduleFrequency)],
            'interval' => ['nullable', 'integer', 'in:1,2'],
            'days_of_week' => ['required_if:frequency,' . ScheduleFrequency::Weekly->value, 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'day_of_month' => ['required_if:frequency,' . ScheduleFrequency::Monthly->value, 'nullable', 'integer', 'between:1,31'],
            'days_of_month' => ['required_if:frequency,' . ScheduleFrequency::MonthlyOnDates->value, 'array', 'min:1'],
            'days_of_month.*' => ['integer', 'between:1,31'],
            'month' => ['required_if:frequency,' . ScheduleFrequency::Yearly->value, 'nullable', 'integer', 'between:1,12'],
            'day' => ['required_if:frequency,' . ScheduleFrequency::Yearly->value, 'nullable', 'integer', 'between:1,31', $this->validYearlyDate()],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'ends_at' => ['nullable', 'date', 'after:start_date'],
            'max_runs' => ['nullable', 'integer', 'min:1', 'max:999'],
            'requires_confirmation' => ['boolean'],
        ];
    }

    /**
     * Service-ready payload: frequency + folded params + bounds. The
     * unique(template_id) constraint makes create fail on a repeat;
     * reactivation goes through update, which ignores template_id.
     *
     * @return array{frequency: string, params: array|null, start_date: string, ends_at: string|null, max_runs: int|null, requires_confirmation: bool}
     */
    public function schedulePayload(): array
    {
        $frequency = ScheduleFrequency::from($this->validated('frequency'));

        $params = match ($frequency) {
            ScheduleFrequency::Weekly => [
                'days_of_week' => array_values(array_unique(array_map(intval(...), (array) $this->validated('days_of_week')))),
                'interval' => (int) ($this->validated('interval') ?? 1),
            ],
            ScheduleFrequency::Monthly => ['day_of_month' => (int) $this->validated('day_of_month')],
            ScheduleFrequency::MonthlyOnDates => [
                'days_of_month' => array_values(array_sort(array_unique(array_map(intval(...), (array) $this->validated('days_of_month'))))),
            ],
            ScheduleFrequency::Yearly => [
                'month' => (int) $this->validated('month'),
                'day' => (int) $this->validated('day'),
            ],
            ScheduleFrequency::Once,
            ScheduleFrequency::Daily => null,
        };

        return [
            'frequency' => $frequency->value,
            'params' => $params,
            'start_date' => $this->validated('start_date'),
            'ends_at' => $this->validated('ends_at'),
            'max_runs' => $this->validated('max_runs'),
            'requires_confirmation' => (bool) $this->validated('requires_confirmation', false),
        ];
    }

    /** Owned template with a selectable default source (and destination for transfers). */
    private function schedulableTemplate(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $template = TransactionTemplate::query()
                ->where('user_id', $this->user()->id)
                ->find($value);

            if ($template === null) {
                $fail('The selected template is invalid.');

                return;
            }

            $selectableIds = AccountService::getAccountIdsByUser($this->user());

            if ($template->account_id === null || ! $selectableIds->contains($template->account_id)) {
                $fail('The template needs a selectable default account before it can be scheduled.');

                return;
            }

            if ($template->type === TransactionType::Transfer
                && ($template->destination_account_id === null
                    || ! $selectableIds->contains($template->destination_account_id))) {
                $fail('The transfer template needs a selectable destination account.');
            }
        };
    }

    /** Feb 29 is a valid pick (runtime clamps off-leap); Feb 30 never is. */
    private function validYearlyDate(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $month = (int) $this->input('month');

            if ($value !== null && $month >= 1 && $month <= 12 && ! checkdate($month, (int) $value, 2024)) {
                $fail('The selected day does not exist in that month.');
            }
        };
    }
}
```

- [ ] **Step 3: Implement the policy**

```php
<?php

namespace App\Policies;

use App\Models\TransactionSchedule;
use App\Models\User;

final class TransactionSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, TransactionSchedule $schedule): bool
    {
        return $this->canAccess($user, $schedule);
    }

    public function update(User $user, TransactionSchedule $schedule): bool
    {
        return $this->canAccess($user, $schedule);
    }

    public function pause(User $user, TransactionSchedule $schedule): bool
    {
        return $this->canAccess($user, $schedule);
    }

    public function resume(User $user, TransactionSchedule $schedule): bool
    {
        return $this->canAccess($user, $schedule);
    }

    public function cancel(User $user, TransactionSchedule $schedule): bool
    {
        return $this->canAccess($user, $schedule);
    }

    public function delete(User $user, TransactionSchedule $schedule): bool
    {
        return $this->canAccess($user, $schedule);
    }

    private function canAccess(User $user, TransactionSchedule $schedule): bool
    {
        return $user->id === $schedule->user_id;
    }
}
```

- [ ] **Step 4: Implement the controller**

```php
<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTransactionScheduleRequest;
use App\Models\TransactionSchedule;
use App\Models\User;
use App\Services\TransactionScheduleService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

final class TransactionScheduleController extends Controller
{
    public function __construct(
        private readonly TransactionScheduleService $scheduleService,
        #[CurrentUser] private readonly ?User $user,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', TransactionSchedule::class);

        return Inertia::render('app/settings/transaction-schedules/index', [
            'schedules' => TransactionScheduleService::getSchedulesByUser($this->user),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', TransactionSchedule::class);

        return Inertia::render('app/settings/transaction-schedules/form', [
            'schedule' => null,
            'templates' => TransactionScheduleService::getSchedulableTemplates($this->user),
            'template_id' => $request->integer('template') ?: null,
        ]);
    }

    public function edit(TransactionSchedule $schedule): Response
    {
        $this->authorize('update', $schedule);

        return Inertia::render('app/settings/transaction-schedules/form', [
            'schedule' => $schedule->load(['template.account:id,name']),
            'templates' => [$schedule->template],
            'template_id' => $schedule->template_id,
        ]);
    }

    public function store(SaveTransactionScheduleRequest $request): RedirectResponse
    {
        $this->scheduleService->create($this->user, [
            ...$request->schedulePayload(),
            'template_id' => (int) $request->validated('template_id'),
        ]);

        return to_route('transaction-schedules.index')->flash('Schedule created.');
    }

    public function update(SaveTransactionScheduleRequest $request, TransactionSchedule $schedule): RedirectResponse
    {
        $this->authorize('update', $schedule);

        $this->scheduleService->update($schedule, $request->schedulePayload());

        return to_route('transaction-schedules.index')->flash('Schedule updated.');
    }

    public function pause(TransactionSchedule $schedule): RedirectResponse
    {
        $this->authorize('pause', $schedule);

        $this->scheduleService->pause($schedule);

        return back()->flash('Schedule paused.');
    }

    public function resume(TransactionSchedule $schedule): RedirectResponse
    {
        $this->authorize('resume', $schedule);

        $this->scheduleService->resume($schedule);

        return back()->flash('Schedule resumed.');
    }

    public function cancel(TransactionSchedule $schedule): RedirectResponse
    {
        $this->authorize('cancel', $schedule);

        $this->scheduleService->cancel($schedule);

        return to_route('transaction-schedules.index')->flash('Schedule canceled.');
    }

    public function destroy(TransactionSchedule $schedule): RedirectResponse
    {
        $this->authorize('delete', $schedule);

        $this->scheduleService->delete($schedule);

        return to_route('transaction-schedules.index')->flash('Schedule deleted.');
    }
}
```

- [ ] **Step 5: Add the routes**

In `routes/settings.php`, inside the `auth` group after the transaction-templates block (with `use App\Http\Controllers\Settings\TransactionScheduleController;` imported):

```php
    Route::prefix('settings/transaction-schedules')->name('transaction-schedules.')->group(function (): void {
        Route::controller(TransactionScheduleController::class)->group(function (): void {
            Route::get('', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('', 'store')->name('store');
            Route::get('{schedule}/edit', 'edit')->name('edit');
            Route::put('{schedule}', 'update')->name('update');
            Route::post('{schedule}/pause', 'pause')->name('pause');
            Route::post('{schedule}/resume', 'resume')->name('resume');
            Route::post('{schedule}/cancel', 'cancel')->name('cancel');
            Route::delete('{schedule}', 'destroy')->name('destroy');
        });
    });
```

---

### Task 7: Run confirm/skip endpoints + dashboard `scheduled` prop

**Complexity:** Medium

**Files:**
- Create: `app/Policies/TransactionScheduleRunPolicy.php`, `app/Http/Controllers/TransactionScheduleRunController.php`
- Modify: `routes/web.php` (run action routes, inside the auth+verified group), `app/Http/Controllers/DashboardController.php` (`scheduled` prop)
- Test: `tests/Feature/App/Schedule/TransactionScheduleRunTest.php` (domain dir — the dashboard `scheduled` prop is schedule-domain even though its surface is the dashboard, the seeding-test precedent)

**Interfaces:**
- Consumes: `ScheduleProcessor::postRun(run, RunStatus)` (Task 4), `TransactionScheduleService::getPendingRuns/getUpcomingOccurrences` (Task 3).
- Produces: POST `transaction-schedule-runs.confirm` / `.skip` (route names `transaction-schedule-runs.confirm` / `.skip`); dashboard prop `scheduled = ['pending' => TransactionScheduleRun[] (schedule.template eager), 'upcoming' => list<{date, template, requires_confirmation}>]`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Enums\RunStatus;
use App\Models\Transaction;
use App\Models\TransactionSchedule;
use App\Models\TransactionScheduleRun;
use App\Models\User;

it('confirms a pending run, posting the transaction as-is', function (): void {
    $user = User::factory()->create();
    $schedule = TransactionSchedule::factory()->forUser($user->id)->confirmation()->create();
    $run = TransactionScheduleRun::factory()->for($schedule)->create();

    $this->actingAs($user)->post(route('transaction-schedule-runs.confirm', $run))
        ->assertRedirect();

    expect($run->fresh()->status)->toBe(RunStatus::Confirmed)
        ->and(Transaction::count())->toBe(1);
});

it('rejects a foreign users run', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $schedule = TransactionSchedule::factory()->forUser($owner->id)->create();
    $run = TransactionScheduleRun::factory()->for($schedule)->create();

    $this->actingAs($intruder)->post(route('transaction-schedule-runs.confirm', $run))
        ->assertForbidden();
});

it('flashes a warning when the template accounts became unavailable', function (): void {
    $user = User::factory()->create();

    $schedule = TransactionSchedule::factory()->forUser($user->id)->confirmation()->create();
    $schedule->template->update(['account_id' => null]); // template edited live — no source anymore
    $run = TransactionScheduleRun::factory()->for($schedule)->create();

    $this->actingAs($user)->post(route('transaction-schedule-runs.confirm', $run))
        ->assertRedirect();

    expect($run->fresh()->skip_reason?->value)->toBe('unavailable_account');
});

it('skips a pending run manually with a null reason', function (): void {
    $user = User::factory()->create();
    $schedule = TransactionSchedule::factory()->forUser($user->id)->create();
    $run = TransactionScheduleRun::factory()->for($schedule)->create();

    $this->actingAs($user)->post(route('transaction-schedule-runs.skip', $run))
        ->assertRedirect();

    expect($run->fresh()->status)->toBe(RunStatus::Skipped)
        ->and($run->fresh()->skip_reason)->toBeNull()
        ->and(Transaction::count())->toBe(0);
});

it('ships pending and upcoming on the dashboard', function (): void {
    Carbon\CarbonImmutable::setTestNow('2026-11-10');
    $user = User::factory()->create();

    $schedule = TransactionSchedule::factory()->forUser($user->id)->monthlyOnDates()->create([
        'start_date' => '2026-11-01',
        'next_run_at' => '2026-11-15',
        'requires_confirmation' => true,
    ]);
    TransactionScheduleRun::factory()->for($schedule)->create(['occurrence_date' => '2026-11-01']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('scheduled.pending', 1)
            ->has('scheduled.upcoming', 1)
            ->where('scheduled.upcoming.0.date', '2026-11-15')
        );

    Carbon\CarbonImmutable::setTestNow();
});
```

- [ ] **Step 2: Implement the run policy**

```php
<?php

namespace App\Policies;

use App\Models\TransactionScheduleRun;
use App\Models\User;

final class TransactionScheduleRunPolicy
{
    public function confirm(User $user, TransactionScheduleRun $run): bool
    {
        return $this->canAccess($user, $run);
    }

    public function skip(User $user, TransactionScheduleRun $run): bool
    {
        return $this->canAccess($user, $run);
    }

    private function canAccess(User $user, TransactionScheduleRun $run): bool
    {
        // user_id is denormalized on runs precisely for this owner check.
        return $user->id === $run->user_id;
    }
}
```

- [ ] **Step 3: Implement the run controller**

```php
<?php

namespace App\Http\Controllers;

use App\Enums\RunStatus;
use App\Models\TransactionScheduleRun;
use App\Services\ScheduleProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TransactionScheduleRunController extends Controller
{
    public function __construct(private readonly ScheduleProcessor $processor) {}

    /** Posts the run as-is from the live template — no edit step (spec: confirm-time editing out of scope). */
    public function confirm(Request $request, TransactionScheduleRun $run): RedirectResponse
    {
        $this->authorize('confirm', $run);

        $transaction = $this->processor->postRun($run, RunStatus::Confirmed);

        if ($transaction === null) {
            return back()->flash('The template accounts are no longer available — edit the template first.', 'warning');
        }

        return to_route('dashboard')->flash('Scheduled transaction posted.');
    }

    public function skip(Request $request, TransactionScheduleRun $run): RedirectResponse
    {
        $this->authorize('skip', $run);

        $run->update([
            'status' => RunStatus::Skipped->value,
            'processed_at' => now(),
        ]);

        return back()->flash('Occurrence skipped.');
    }
}
```

Note: verify the `flash()` redirect macro's signature — if it takes no type argument, use the single-string form for the warning case (`->flash('The template accounts are no longer available — edit the template first.')`). Check `app/Providers/AppServiceProvider.php` (or wherever the macro lives) before writing the two-argument call.

- [ ] **Step 4: Add routes + dashboard prop**

`routes/web.php`, inside the `auth` + `verified` group after the transfers block (with the controller imported):

```php
    // Scheduled-run decisions (dashboard due card)
    Route::controller(TransactionScheduleRunController::class)
        ->prefix('transaction-schedule-runs')
        ->name('transaction-schedule-runs.')
        ->group(function (): void {
            Route::post('{run}/confirm', 'confirm')->name('confirm');
            Route::post('{run}/skip', 'skip')->name('skip');
        });
```

`DashboardController::index` — add to the `Inertia::render` props (with `use App\Services\TransactionScheduleService;` imported):

```php
            'scheduled' => [
                'pending' => TransactionScheduleService::getPendingRuns($this->user),
                'upcoming' => TransactionScheduleService::getUpcomingOccurrences($this->user),
            ],
```

---

### Task 8: Template detail linkage + codegen

**Complexity:** Low

**Files:**
- Modify: `app/Http/Controllers/Settings/TransactionTemplateController.php` (`show` gains `schedule_id`)
- Modify: `tests/Feature/App/Settings/TransactionTemplateTest.php` (assert the new prop)
- Run once: `php artisan wayfinder:generate --fresh`

**Interfaces:**
- Consumes: `TransactionTemplate::schedule()` (Task 2).
- Produces: template show page prop `schedule_id: int|null` — the frontend plan's detail-page "Scheduled — manage" link uses it.

- [ ] **Step 1: Extend the show test**

In `tests/Feature/App/Settings/TransactionTemplateTest.php`, find `renders the template detail page` and extend the Inertia assertion:

```php
    $this->actingAs($user)->get(route('transaction-templates.show', $template))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/settings/transaction-templates/index') // keep whatever the existing test asserts
            ->has('schedule_id')
        );
```

(Add `->has('schedule_id')` to the existing assertion block rather than rewriting it.)

- [ ] **Step 2: Add the prop**

In `TransactionTemplateController::show`, add to the render props:

```php
            'schedule_id' => $template->schedule()->value('id'),
```

- [ ] **Step 3: Regenerate Wayfinder**

Run: `php artisan wayfinder:generate --fresh`
Expected: `resources/js/wayfinder/` now mirrors `ScheduleFrequency`, `ScheduleStatus`, `RunStatus`, `SkipReason`, both controllers' routes, and the new model interfaces; the dead `RecurringFrequency.ts` mirror disappears.

---

## Self-Review

1. **Spec coverage** — §1 schema (Task 2), §2 engine (Task 1), §3 models (Task 2), §4 policy (Tasks 6–7), §5 validation (Task 6), §6 services + triggers (Tasks 3–5), §7 lifecycle (Tasks 3–4), §8 controllers/routes/dashboard (Tasks 6–7 + 8), §12 linkage (run `transaction_id`, Task 4), §14 tests (Tasks 1, 3, 4, 5, 6, 7). §9–§10 (frontend) and §13's README move belong to the frontend plan. No gaps. Test placement follows the restructured suite: domain-owned `Feature/App/Schedule/` for run/materialization/dashboard-prop tests (the `Feature/App/Template/` precedent), Settings-surface CRUD in `Feature/App/Settings/`, and `module:schedule` groups registered in `tests/Pest.php` (Task 5).
2. **Placeholders** — none; every step carries complete code or an exact edit. The one verified-at-execution note (flash macro arity, Task 7) states both fallback forms.
3. **Type consistency** — `advanceNextRun(CarbonInterface, ?int)` matches across model/service/processor; `postRun(run, RunStatus)` matches between Tasks 4 and 7; `schedulePayload()` keys match `TransactionScheduleService::create/update` `$data` shape; factory state names (`forUser`, `forTemplate`, `due`, `startsInPast`, `confirmation`, `forAccountOf`, `transferFor`) are each defined in the task that first uses them.
4. **Workflow mode** — `direct`: no run/verify/commit steps in any task; no final verification task. Wayfinder regen (Task 8) is codegen, not verification.
5. **Testing scope** — Tasks 1, 3, 4, 5, 6, 7 test meaningful behavior (engine math, lifecycle, HTTP contracts); Task 2 and Task 8 test no new standalone behavior beyond what Tasks 3–7 already exercise (deliberate, house convention).
6. **Complexity** — all tasks Medium except Tasks 5 and 8 (Low); overall: **Medium**.
