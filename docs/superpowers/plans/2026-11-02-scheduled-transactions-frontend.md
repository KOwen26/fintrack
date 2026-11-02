# Scheduled Transactions — Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use better-executing-plans to implement this plan task-by-task — or better-parallel-subagents-executions if the plan splits into independent slices, or superpowers:subagent-driven-development if workflow mode is `remote`. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the client half of scheduled transactions — the Settings → Scheduled Transactions management surfaces (index + shared form), the dashboard due card, the template detail linkage, and the nav entry.

**Goal architecture:** Thin client, per spec §"Frontend loop": every schedule fact arrives as a server-rendered prop; confirm/skip/pause/resume/cancel are plain POSTs to Wayfinder controller URLs; the only client-side "logic" is a label util that humanizes frequency params the server already computed. Zero date math in JS.

**Tech Stack:** Svelte 5 runes, Inertia v3 (`useForm`, `router`), Wayfinder-generated controllers/enums, DaisyUI-token classes.

**Workflow Mode:** `direct` — no run/verify/commit steps; the user runs `pnpm run build` on request and commits personally.

**Complexity:** Medium

## Global Constraints

- **Depends on the backend plan being complete** (`2026-11-02-scheduled-transactions-backend.md`) — Wayfinder must already mirror `App.Models.TransactionSchedule`, `App.Models.TransactionScheduleRun`, `App.Enums.{ScheduleFrequency,ScheduleStatus,RunStatus,SkipReason}`, `Settings/TransactionScheduleController`, and `TransactionScheduleRunController`. If a wayfinder import fails, stop and regenerate (`php artisan wayfinder:generate --fresh`) — never hand-edit `resources/js/wayfinder/`.
- All files kebab-case; Svelte 5 runes only (`$props`, `$state`, `$derived`, `$effect` — never `export let` / `$:`).
- No hardcoded URLs — Wayfinder controller imports only. No magic strings for enum values — import enum constants from `@wayfinder/App/Enums/*`.
- Every displayed PHP enum gets a badge component in `components/module/schedule/` (frequency renders as text, not a badge — spec §9).
- When form initials reference props, use the lazy pattern: `useForm(() => ({ ... }))`.
- **No frontend tests** — house convention (the Svelte layer ships zero tests; `pnpm run build` is the only gate, user-run). Every task below therefore omits the `Test:` line deliberately.
- Real import paths from siblings: `Card` is `@components/ui/cards/card.svelte`, `Badge` is `@components/ui/badge.svelte`, `Field`/`Select`/`Input`/`Switch`/`DateInput` are `@components/ui/forms/*`, `ConfirmationModal` is `@components/ui/modals/confirmation-modal.svelte`, `EmptyItemPlaceholder` is `@components/data/empty-item-placeholder.svelte`, `DecorationBadge` is `@components/module/decoration-badge.svelte`.
- `Select` (plain atom) takes `options={...}`; `DateInput` binds canonical `'YYYY-MM-DD'` strings; `Switch` binds `checked`.
- Preserve any on-disk edits in files you touch — do not restore plan text over drift.
- No formatters/linters/commits — user-owned.

---

### Task 1: Frequency labeler + enum badges

**Complexity:** Low

**Files:**
- Create: `resources/js/utilities/schedule-frequency.ts`
- Create: `resources/js/components/module/schedule/schedule-status-badge.svelte`
- Create: `resources/js/components/module/schedule/run-status-badge.svelte`

**Interfaces:**
- Consumes: Wayfinder enums `App.Enums.ScheduleFrequency`, `App.Enums.ScheduleStatus`, `App.Enums.RunStatus`.
- Produces: `export interface ScheduleParams { days_of_week?: number[]; interval?: number; day_of_month?: number; days_of_month?: number[]; month?: number; day?: number }` and `export default class ScheduleFrequencyHelper { static summary(frequency: App.Enums.ScheduleFrequency, params?: ScheduleParams | null): string }` — both from `schedule-frequency.ts`, imported by Tasks 2–4. Badge components take `{ status }`.

- [ ] **Step 1: Write the labeler**

`resources/js/utilities/schedule-frequency.ts`:

```ts
import type { App } from '@wayfinder/types';

import ScheduleFrequency from '@wayfinder/App/Enums/ScheduleFrequency';

/** Mirror of the `params` JSON column — never interpreted here, only labeled. */
export interface ScheduleParams {
    days_of_week?: number[];
    interval?: number;
    day_of_month?: number;
    days_of_month?: number[];
    month?: number;
    day?: number;
}

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as const;
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] as const;

export default class ScheduleFrequencyHelper {
    /** Human summary for list rows and the due card — "Every 2 weeks on Fri", "Monthly on 1, 15". */
    static summary(frequency: App.Enums.ScheduleFrequency, params?: ScheduleParams | null): string {
        switch (frequency) {
            case ScheduleFrequency.Once:
                return 'Once';
            case ScheduleFrequency.Daily:
                return 'Daily';
            case ScheduleFrequency.Weekly: {
                const days = (params?.days_of_week ?? []).map((day) => WEEKDAYS[day - 1]).join(', ');

                return `${(params?.interval ?? 1) === 2 ? 'Every 2 weeks' : 'Weekly'} on ${days}`;
            }
            case ScheduleFrequency.Monthly:
                return `Monthly on ${ScheduleFrequencyHelper.ordinal(params?.day_of_month ?? 1)}`;
            case ScheduleFrequency.MonthlyOnDates:
                return `Monthly on ${(params?.days_of_month ?? []).join(', ')}`;
            case ScheduleFrequency.Yearly:
                return `Yearly on ${MONTHS[(params?.month ?? 1) - 1]} ${params?.day ?? 1}`;
        }
    }

    /** 1 → 1st, 2 → 2nd, 15 → 15th, 21 → 21st, 22 → 22nd, 23 → 23rd, 31 → 31st. */
    static ordinal(day: number): string {
        const suffix = day % 100 >= 11 && day % 100 <= 13 ? 'th' : ['th', 'st', 'nd', 'rd'][day % 10] ?? 'th';

        return `${day}${suffix}`;
    }
}
```

- [ ] **Step 2: Write the badges**

`resources/js/components/module/schedule/schedule-status-badge.svelte`:

```svelte
<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { App } from '@wayfinder/types';

    import ScheduleStatus from '@wayfinder/App/Enums/ScheduleStatus';

    import Badge from '@components/ui/badge.svelte';

    let { status }: { status: App.Enums.ScheduleStatus } = $props();

    const config: Record<App.Enums.ScheduleStatus, { label: string; color: ColorVariant }> = {
        [ScheduleStatus.Active]: { label: 'Active', color: 'success' },
        [ScheduleStatus.Paused]: { label: 'Paused', color: 'warning' },
        [ScheduleStatus.Canceled]: { label: 'Canceled', color: 'light' },
        [ScheduleStatus.Completed]: { label: 'Completed', color: 'info' },
    };

    const badge = $derived(config[status]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
```

`resources/js/components/module/schedule/run-status-badge.svelte`:

```svelte
<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { App } from '@wayfinder/types';

    import RunStatus from '@wayfinder/App/Enums/RunStatus';

    import Badge from '@components/ui/badge.svelte';

    let { status }: { status: App.Enums.RunStatus } = $props();

    const config: Record<App.Enums.RunStatus, { label: string; color: ColorVariant }> = {
        [RunStatus.Pending]: { label: 'Pending', color: 'warning' },
        [RunStatus.Posted]: { label: 'Posted', color: 'success' },
        [RunStatus.Confirmed]: { label: 'Confirmed', color: 'info' },
        [RunStatus.Skipped]: { label: 'Skipped', color: 'light' },
    };

    const badge = $derived(config[status]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
```

---

### Task 2: Settings index page + `schedule-list`

**Complexity:** Medium

**Files:**
- Create: `resources/js/pages/app/settings/transaction-schedules/index.svelte`
- Create: `resources/js/components/module/schedule/schedule-list.svelte`

**Interfaces:**
- Consumes: `ScheduleFrequencyHelper`, `ScheduleStatusBadge`, `RunStatusBadge` (Task 1); `TransactionScheduleController` (Wayfinder); backend prop `schedules: ScheduleListItem[]`.
- Produces: `ScheduleListItem` interface (defined inline in both files — identical shape): `App.Models.TransactionSchedule & { template: App.Models.TransactionTemplate; last_run?: App.Models.TransactionScheduleRun | null; pending_runs_count?: number }`.

- [ ] **Step 1: Write the list component**

`resources/js/components/module/schedule/schedule-list.svelte`:

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import TransactionScheduleController from '@wayfinder/App/Http/Controllers/Settings/TransactionScheduleController';

    import ScheduleFrequencyHelper from '@utilities/schedule-frequency';

    import EmptyItemPlaceholder from '@components/data/empty-item-placeholder.svelte';
    import DecorationBadge from '@components/module/decoration-badge.svelte';
    import RunStatusBadge from '@components/module/schedule/run-status-badge.svelte';
    import ScheduleStatusBadge from '@components/module/schedule/schedule-status-badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/cards/card.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    interface ScheduleListItem extends App.Models.TransactionSchedule {
        template: App.Models.TransactionTemplate;
        last_run?: App.Models.TransactionScheduleRun | null;
        pending_runs_count?: number;
    }

    let { schedules }: { schedules: ScheduleListItem[] } = $props();

    let canceling = $state<ScheduleListItem | null>(null);
    let deleting = $state<ScheduleListItem | null>(null);

    const isPaused = (schedule: ScheduleListItem): boolean => schedule.status === 'paused';

    function lastRunLine(schedule: ScheduleListItem): string {
        const run = schedule.last_run;

        if (!run) {
            return 'last: —';
        }

        const when = new Date(run.occurrence_date).toLocaleDateString();

        if (run.status === 'skipped') {
            return `last: skipped — ${run.skip_reason === 'grace_expired' ? 'grace expired' : run.skip_reason === 'canceled' ? 'canceled' : 'account unavailable'}`;
        }

        return `last: ${run.status} ${when}`;
    }

    function cancel(): void {
        if (!canceling) return;

        router.post(TransactionScheduleController.cancel.url({ schedule: canceling.id }), {
            onFinish: () => (canceling = null),
        });
    }

    function destroy(): void {
        if (!deleting) return;

        router.delete(TransactionScheduleController.destroy.url({ schedule: deleting.id }), {
            onFinish: () => (deleting = null),
        });
    }
</script>

{#if schedules.length === 0}
    <EmptyItemPlaceholder
        ctaLabel="Schedule your first template"
        ctaUrl={TransactionScheduleController.create.url()}
        icon="solar--calendar-minimalistic-duotone"
        label="No schedules yet" />
{:else}
    <ul class="space-y-3">
        {#each schedules as schedule (schedule.id)}
            <li>
                <Card>
                    <div class="flex items-center gap-3">
                        <DecorationBadge decorations={schedule.template.decorations} size="sm" />

                        <span class="min-w-0 grow">
                            <span class="flex items-center gap-2">
                                <h6 class="truncate text-sm font-semibold">{schedule.template.name}</h6>
                                <ScheduleStatusBadge status={schedule.status} />
                            </span>

                            <p class="truncate text-xs text-muted-foreground">
                                {ScheduleFrequencyHelper.summary(schedule.frequency, schedule.params)}
                                · next {schedule.next_run_at
                                    ? new Date(schedule.next_run_at).toLocaleDateString()
                                    : '—'}
                            </p>

                            <p class="truncate text-xs text-muted-foreground/70">
                                {lastRunLine(schedule)}
                                {#if schedule.pending_runs_count}
                                    · {schedule.pending_runs_count} waiting
                                {/if}
                            </p>
                        </span>
                    </div>

                    {#if schedule.status === 'active' || schedule.status === 'paused'}
                        <div class="mt-3 flex justify-end gap-2">
                            <Button
                                class="btn-sm"
                                href={TransactionScheduleController.edit.url({ schedule: schedule.id })}
                                variant="ghost">
                                Edit
                            </Button>

                            <Button
                                class="btn-sm"
                                onclick={() =>
                                    router.post(
                                        isPaused(schedule)
                                            ? TransactionScheduleController.resume.url({ schedule: schedule.id })
                                            : TransactionScheduleController.pause.url({ schedule: schedule.id })
                                    )}
                                variant="ghost">
                                {isPaused(schedule) ? 'Resume' : 'Pause'}
                            </Button>

                            <Button class="btn-sm" onclick={() => (canceling = schedule)} variant="ghost">
                                Cancel
                            </Button>

                            <Button class="btn-sm" color="error" onclick={() => (deleting = schedule)} variant="ghost">
                                Delete
                            </Button>
                        </div>
                    {/if}
                </Card>
            </li>
        {/each}
    </ul>
{/if}

<ConfirmationModal
    cancelText="Keep Schedule"
    confirmButtonProps={{ color: 'warning' }}
    confirmText="Cancel Schedule"
    onConfirm={cancel}
    title="Cancel Schedule"
    bind:open={canceling}>
    Future occurrences stop and pending confirmations are skipped. The schedule stays in the list and can be
    reactivated by editing it.
</ConfirmationModal>

<ConfirmationModal
    cancelText="Keep Schedule"
    confirmButtonProps={{ color: 'error' }}
    confirmText="Delete"
    onConfirm={destroy}
    title="Delete Schedule"
    bind:open={deleting}>
    This removes the schedule and its run history. Transactions already created stay untouched. It cannot be
    undone.
</ConfirmationModal>
```

- [ ] **Step 2: Write the index page**

`resources/js/pages/app/settings/transaction-schedules/index.svelte`:

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { setLayoutProps } from '@inertiajs/svelte';
    import TransactionScheduleController from '@wayfinder/App/Http/Controllers/Settings/TransactionScheduleController';
    import settings from '@wayfinder/routes/settings';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import ScheduleList from '@components/module/schedule/schedule-list.svelte';
    import Button from '@components/ui/button.svelte';

    interface ScheduleListItem extends App.Models.TransactionSchedule {
        template: App.Models.TransactionTemplate;
        last_run?: App.Models.TransactionScheduleRun | null;
        pending_runs_count?: number;
    }

    let { schedules }: { schedules: ScheduleListItem[] } = $props();

    setLayoutProps({ title: 'Scheduled Transactions', backUrl: settings.index.url() });
</script>

<MobilePageLayout variant="full">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Schedules</h2>

        <Button color="primary" href={TransactionScheduleController.create.url()}>
            Add
            <i class="iconify size-5 solar--add-bold-duotone"></i>
        </Button>
    </div>

    <div class="mt-6">
        <ScheduleList {schedules} />
    </div>
</MobilePageLayout>
```

---

### Task 3: Shared form page + `schedule-form`

**Complexity:** Medium

**Files:**
- Create: `resources/js/pages/app/settings/transaction-schedules/form.svelte`
- Create: `resources/js/components/module/schedule/schedule-form.svelte`

**Interfaces:**
- Consumes: Wayfinder `Settings/TransactionScheduleController` (`store.url()`, `update.url({ schedule })`), `App.Enums.ScheduleFrequency`; backend props `schedule: App.Models.TransactionSchedule | null`, `templates: App.Models.TransactionTemplate[]`, `template_id: number | null`; the backend request's flat payload contract (`template_id, frequency, interval, days_of_week, day_of_month, days_of_month, month, day, start_date, ends_at, max_runs, requires_confirmation`).
- Produces: `schedule-form.svelte` exposing `form` via `bind:form` (the templates-form pattern); page hosts layout + `FormAction` in `BottomActionBar` with `formId="schedule-form"`.

- [ ] **Step 1: Write the form component**

`resources/js/components/module/schedule/schedule-form.svelte`:

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { useForm } from '@inertiajs/svelte';
    import ScheduleFrequency from '@wayfinder/App/Enums/ScheduleFrequency';
    import TransactionScheduleController from '@wayfinder/App/Http/Controllers/Settings/TransactionScheduleController';

    import type { ScheduleParams } from '@utilities/schedule-frequency';

    import DecorationBadge from '@components/module/decoration-badge.svelte';
    import Card from '@components/ui/cards/card.svelte';
    import DateInput from '@components/ui/forms/date-input.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import Form from '@components/ui/forms/form.svelte';
    import Input from '@components/ui/forms/input.svelte';
    import Select from '@components/ui/forms/select.svelte';
    import Switch from '@components/ui/forms/switch.svelte';

    /** The form's frequency select folds "Every 2 weeks" into Weekly + interval on submit. */
    type FrequencyChoice =
        | 'once'
        | 'daily'
        | 'weekly'
        | 'biweekly'
        | 'monthly'
        | 'monthly_on_dates'
        | 'yearly';

    const FREQUENCY_OPTIONS = [
        { value: 'once', label: 'Once' },
        { value: 'daily', label: 'Daily' },
        { value: 'weekly', label: 'Weekly' },
        { value: 'biweekly', label: 'Every 2 weeks' },
        { value: 'monthly', label: 'Monthly' },
        { value: 'monthly_on_dates', label: 'Monthly on dates' },
        { value: 'yearly', label: 'Yearly' },
    ];

    const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as const;
    const MONTH_OPTIONS = Array.from({ length: 12 }, (_, index) => ({
        value: String(index + 1),
        label: new Date(2026, index, 1).toLocaleDateString(undefined, { month: 'short' }),
    }));

    const END_CHOICES = [
        { value: 'never', label: 'Never' },
        { value: 'date', label: 'On date' },
        { value: 'count', label: 'After N runs' },
    ] as const;

    interface Props {
        id: string;
        schedule?: App.Models.TransactionSchedule | null;
        templates: App.Models.TransactionTemplate[];
        template_id?: number | null;
        /** Exposed to the page for its external FormAction — the `bind:form` pattern. */
        form?: ReturnType<typeof useForm>;
    }

    let { id, schedule = null, templates, template_id = null, form = $bindable() }: Props = $props();

    const isEdit = $derived(schedule !== null);
    const selectedTemplate = $derived(templates.find((item) => item.id === Number(form?.data?.template_id ?? template_id)));

    function toChoice(frequency?: App.Enums.ScheduleFrequency, params?: ScheduleParams | null): FrequencyChoice {
        if (frequency === ScheduleFrequency.Weekly && (params?.interval ?? 1) === 2) {
            return 'biweekly';
        }

        return ((frequency ?? ScheduleFrequency.Monthly) as FrequencyChoice);
    }

    /* Lazy initials — they reference props (house rule). */
    form = useForm(() => ({
        template_id: schedule?.template_id ? String(schedule.template_id) : template_id ? String(template_id) : '',
        frequencyChoice: toChoice(schedule?.frequency, schedule?.params),
        start_date: schedule?.start_date ?? '',
        days_of_week: schedule?.params?.days_of_week ?? [],
        day_of_month: schedule?.params?.day_of_month ? String(schedule.params.day_of_month) : '',
        days_of_month: schedule?.params?.days_of_month ?? [],
        month: schedule?.params?.month ? String(schedule.params.month) : '',
        day: schedule?.params?.day ? String(schedule.params.day) : '',
        requires_confirmation: schedule?.requires_confirmation ?? true,
        end_choice: schedule?.ends_at ? 'date' : schedule?.max_runs ? 'count' : 'never',
        ends_at: schedule?.ends_at ?? '',
        max_runs: schedule?.max_runs ? String(schedule.max_runs) : '',
    }));

    /* Fold UI state into the backend's flat payload contract. */
    form.transform((data: Record<string, unknown>) => {
        const frequency = data.frequencyChoice === 'biweekly' ? 'weekly' : data.frequencyChoice;
        const orNull = (value: unknown) => (value === '' || value === undefined ? null : value);

        return {
            ...(data.template_id ? { template_id: Number(data.template_id) } : {}),
            frequency,
            ...(frequency === 'weekly' ? { interval: data.frequencyChoice === 'biweekly' ? 2 : 1, days_of_week: data.days_of_week } : {}),
            ...(frequency === 'monthly' ? { day_of_month: orNull(data.day_of_month) } : {}),
            ...(frequency === 'monthly_on_dates' ? { days_of_month: data.days_of_month } : {}),
            ...(frequency === 'yearly' ? { month: orNull(data.month), day: orNull(data.day) } : {}),
            start_date: data.start_date,
            requires_confirmation: data.requires_confirmation,
            ends_at: data.end_choice === 'date' ? orNull(data.ends_at) : null,
            max_runs: data.end_choice === 'count' ? orNull(data.max_runs) : null,
        };
    });

    const isWeekly = $derived(form.data.frequencyChoice === 'weekly' || form.data.frequencyChoice === 'biweekly');
    const isMonthly = $derived(form.data.frequencyChoice === 'monthly');
    const isMonthlyOnDates = $derived(form.data.frequencyChoice === 'monthly_on_dates');
    const isYearly = $derived(form.data.frequencyChoice === 'yearly');

    function toggleFrom(list: number[], value: number): number[] {
        return list.includes(value) ? list.filter((item) => item !== value) : [...list, value];
    }
</script>

<Form {id} {form} action={isEdit && schedule
        ? TransactionScheduleController.update.url({ schedule: schedule.id })
        : TransactionScheduleController.store.url()}
    method={isEdit ? 'put' : 'post'}>
    <div class="space-y-6">
        <Card title="Template">
            {#if isEdit && selectedTemplate}
                <div class="flex items-center gap-3">
                    <DecorationBadge decorations={selectedTemplate.decorations} size="sm" />
                    <span class="grow text-sm font-medium">{selectedTemplate.name}</span>
                    <span class="text-xs text-muted-foreground">Template cannot be changed</span>
                </div>
            {:else}
                <Field error={form.errors.template_id} required title="Template">
                    <Select
                        options={templates.map((item) => ({
                            value: String(item.id),
                            label: `${item.name} · ${Number(item.amount).toLocaleString()}`,
                        }))}
                        placeholder="Pick a template"
                        bind:value={form.data.template_id} />
                </Field>
                <p class="mt-2 text-xs text-muted-foreground">
                    Only templates with a default account can be scheduled — there is nobody to pick one at 3am.
                </p>
            {/if}
        </Card>

        <Card title="Schedule">
            <div class="space-y-4">
                <Field error={form.errors.start_date} required title="Start Date">
                    <DateInput required bind:value={form.data.start_date} />
                </Field>

                <Field error={form.errors.frequency} required title="Frequency">
                    <Select options={FREQUENCY_OPTIONS} bind:value={form.data.frequencyChoice} />
                </Field>

                {#if isWeekly}
                    <Field error={form.errors.days_of_week} required title="Days of Week">
                        <div class="flex flex-wrap gap-2">
                            {#each WEEKDAYS as weekday, index (weekday)}
                                <button
                                    class:border-primary={form.data.days_of_week.includes(index + 1)}
                                    class:bg-primary={form.data.days_of_week.includes(index + 1)}
                                    class:text-primary-content={form.data.days_of_week.includes(index + 1)}
                                    class="rounded-full border border-base-content/15 bg-base-100 px-3 py-1.5 text-sm transition-colors"
                                    type="button"
                                    onclick={() => (form.data.days_of_week = toggleFrom(form.data.days_of_week, index + 1))}>
                                    {weekday}
                                </button>
                            {/each}
                        </div>
                    </Field>
                {:else if isMonthly}
                    <Field error={form.errors.day_of_month} required title="Day of Month">
                        <Input max="31" min="1" placeholder="e.g. 15" type="number" bind:value={form.data.day_of_month} />
                        <p class="mt-1.5 text-xs text-muted-foreground">Shorter months clamp to their last day.</p>
                    </Field>
                {:else if isMonthlyOnDates}
                    <Field error={form.errors.days_of_month} required title="Days of Month">
                        <div class="grid grid-cols-7 gap-1.5">
                            {#each Array.from({ length: 31 }, (_, index) => index + 1) as day (day)}
                                <button
                                    class:border-primary={form.data.days_of_month.includes(day)}
                                    class:bg-primary={form.data.days_of_month.includes(day)}
                                    class:text-primary-content={form.data.days_of_month.includes(day)}
                                    class="rounded-lg border border-base-content/15 bg-base-100 py-1.5 text-sm transition-colors"
                                    type="button"
                                    onclick={() => (form.data.days_of_month = toggleFrom(form.data.days_of_month, day))}>
                                    {day}
                                </button>
                            {/each}
                        </div>
                    </Field>
                {:else if isYearly}
                    <div class="grid grid-cols-2 gap-4">
                        <Field error={form.errors.month} required title="Month">
                            <Select options={MONTH_OPTIONS} placeholder="Month" bind:value={form.data.month} />
                        </Field>
                        <Field error={form.errors.day} required title="Day">
                            <Input max="31" min="1" placeholder="e.g. 3" type="number" bind:value={form.data.day} />
                        </Field>
                    </div>
                {/if}

                <Field title="Posting">
                    <label class="flex items-center gap-3 text-sm">
                        <Switch bind:checked={form.data.requires_confirmation} />
                        {form.data.requires_confirmation ? 'Ask before posting' : 'Post automatically'}
                    </label>
                </Field>

                <Field error={form.errors.ends_at ?? form.errors.max_runs} title="Ends">
                    <div class="flex gap-2">
                        {#each END_CHOICES as choice (choice.value)}
                            <button
                                class:border-primary={form.data.end_choice === choice.value}
                                class:bg-primary={form.data.end_choice === choice.value}
                                class:text-primary-content={form.data.end_choice === choice.value}
                                class="rounded-full border border-base-content/15 bg-base-100 px-3 py-1.5 text-sm transition-colors"
                                type="button"
                                onclick={() => (form.data.end_choice = choice.value)}>
                                {choice.label}
                            </button>
                        {/each}
                    </div>

                    {#if form.data.end_choice === 'date'}
                        <div class="mt-2">
                            <DateInput bind:value={form.data.ends_at} />
                        </div>
                    {:else if form.data.end_choice === 'count'}
                        <div class="mt-2">
                            <Input min="1" placeholder="e.g. 12" type="number" bind:value={form.data.max_runs} />
                        </div>
                    {/if}
                </Field>
            </div>
        </Card>
    </div>
</Form>
```

- [ ] **Step 2: Write the form page**

`resources/js/pages/app/settings/transaction-schedules/form.svelte`:

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router, setLayoutProps, useForm } from '@inertiajs/svelte';
    import TransactionScheduleController from '@wayfinder/App/Http/Controllers/Settings/TransactionScheduleController';
    import settings from '@wayfinder/routes/settings';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import ScheduleForm from '@components/module/schedule/schedule-form.svelte';
    import BottomActionBar from '@components/navigation/bottom-action-bar.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';

    let {
        schedule = null,
        templates,
        template_id = null,
    }: {
        schedule?: App.Models.TransactionSchedule | null;
        templates: App.Models.TransactionTemplate[];
        template_id?: number | null;
    } = $props();

    setLayoutProps({
        title: schedule ? 'Edit Schedule' : 'Add Schedule',
        backUrl: settings.index.url(),
    });

    const isEdit = $derived(schedule !== null);

    /* Created inside ScheduleForm, bound up — the `bind:form` pattern. */
    let form: ReturnType<typeof useForm> | undefined = $state();

    function cancel(): void {
        router.visit(TransactionScheduleController.index.url());
    }

    const submitLabel = $derived(isEdit ? 'Save Changes' : 'Add Schedule');

    const formActionProps = $derived(
        form && {
            cancelClass: 'flex-auto',
            submitClass: 'flex-3/5',
            form,
            formId: 'schedule-form',
            labelCancel: 'Cancel',
            labelSubmit: submitLabel,
            onCancel: cancel,
        }
    );
</script>

<MobilePageLayout variant="full">
    <ScheduleForm id="schedule-form" {schedule} {template_id} {templates} bind:form />
</MobilePageLayout>

{#if formActionProps}
    <BottomActionBar>
        <FormAction class="w-full" {...formActionProps} />
    </BottomActionBar>
{/if}
```

---

### Task 4: Dashboard due card

**Complexity:** Medium

**Files:**
- Create: `resources/js/components/module/schedule/schedule-due-card.svelte`
- Modify: `resources/js/pages/app/dashboard.svelte` (prop + mount)

**Interfaces:**
- Consumes: backend `scheduled` prop — `{ pending: PendingRun[]; upcoming: UpcomingItem[] }` where `PendingRun = App.Models.TransactionScheduleRun & { schedule: App.Models.TransactionSchedule & { template: App.Models.TransactionTemplate } }` and `UpcomingItem = { date: string; template: App.Models.TransactionTemplate; requires_confirmation: boolean }`; Wayfinder `TransactionScheduleRunController` (`confirm.url({ run })`, `skip.url({ run })`).
- Produces: `ScheduleDueCard` with props `{ pending, upcoming }`; hides itself when both are empty (the page still guards).

- [ ] **Step 1: Write the card**

`resources/js/components/module/schedule/schedule-due-card.svelte`:

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import TransactionScheduleRunController from '@wayfinder/App/Http/Controllers/TransactionScheduleRunController';

    import CurrencyAmount from '@components/data/currency-amount.svelte';
    import DecorationBadge from '@components/module/decoration-badge.svelte';
    import Button from '@components/ui/button.svelte';
    import ResponsiveCard from '@components/ui/cards/responsive-card.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    interface PendingRun extends App.Models.TransactionScheduleRun {
        schedule: App.Models.TransactionSchedule & { template: App.Models.TransactionTemplate };
    }

    interface UpcomingItem {
        date: string;
        template: App.Models.TransactionTemplate;
        requires_confirmation: boolean;
    }

    let { pending = [], upcoming = [] }: { pending?: PendingRun[]; upcoming?: UpcomingItem[] } = $props();

    let skipping = $state<PendingRun | null>(null);

    function confirm(run: PendingRun): void {
        router.post(TransactionScheduleRunController.confirm.url({ run: run.id }));
    }

    function skip(): void {
        if (!skipping) return;

        router.post(TransactionScheduleRunController.skip.url({ run: skipping.id }), {
            onFinish: () => (skipping = null),
        });
    }
</script>

<ResponsiveCard title="Scheduled Transactions">
    {#if pending.length}
        <div class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-warning">Needs confirmation</p>

            {#each pending as run (run.id)}
                <div class="flex items-center gap-3 rounded-xl border border-base-content/10 p-2.5">
                    <DecorationBadge decorations={run.schedule.template.decorations} size="sm" />

                    <span class="min-w-0 grow">
                        <span class="block truncate text-sm font-medium">{run.schedule.template.name}</span>
                        <span class="block text-xs text-muted-foreground">
                            <CurrencyAmount value={Number(run.schedule.template.amount)} />
                            · due {new Date(run.occurrence_date).toLocaleDateString()}
                        </span>
                    </span>

                    <Button class="btn-sm" color="primary" onclick={() => confirm(run)}>Confirm</Button>
                    <Button class="btn-sm" variant="ghost" onclick={() => (skipping = run)}>Skip</Button>
                </div>
            {/each}
        </div>
    {/if}

    {#if pending.length && upcoming.length}
        <div class="my-3 border-t border-base-content/10"></div>
    {/if}

    {#if upcoming.length}
        <div class="space-y-1.5">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Upcoming · next 7 days</p>

            {#each upcoming as item (item.date + item.template.id)}
                <div class="flex items-center gap-3 text-sm">
                    <span class="w-14 shrink-0 text-xs text-muted-foreground">
                        {new Date(item.date).toLocaleDateString(undefined, { day: 'numeric', month: 'short' })}
                    </span>

                    <DecorationBadge decorations={item.template.decorations} size="sm" />

                    <span class="min-w-0 grow truncate">{item.template.name}</span>

                    <span class="shrink-0 text-xs text-muted-foreground">
                        {item.requires_confirmation ? 'asks' : 'auto'}
                    </span>
                </div>
            {/each}
        </div>
    {/if}
</ResponsiveCard>

<ConfirmationModal
    cancelText="Keep It"
    confirmButtonProps={{ color: 'warning' }}
    confirmText="Skip"
    onConfirm={skip}
    title="Skip Occurrence"
    bind:open={skipping}>
    Skipping leaves this entry out of your books. The schedule itself keeps running.
</ConfirmationModal>
```

- [ ] **Step 2: Mount it on the dashboard**

In `resources/js/pages/app/dashboard.svelte`:

Script — add the import and widen props:

```ts
    import ScheduleDueCard from '@components/module/schedule/schedule-due-card.svelte';

    interface PendingRun extends App.Models.TransactionScheduleRun {
        schedule: App.Models.TransactionSchedule & { template: App.Models.TransactionTemplate };
    }

    interface UpcomingItem {
        date: string;
        template: App.Models.TransactionTemplate;
        requires_confirmation: boolean;
    }
```

and in the `$props()` destructure add `scheduled = { pending: [], upcoming: [] }` with type `{ scheduled?: { pending?: PendingRun[]; upcoming?: UpcomingItem[] } }`.

Markup — insert before the `<!-- Accounts -->` card (top of the grid = highest urgency):

```svelte
            {#if scheduled.pending.length || scheduled.upcoming.length}
                <ScheduleDueCard pending={scheduled.pending} upcoming={scheduled.upcoming} />
                <Separator />
            {/if}
```

---

### Task 5: Template detail linkage

**Complexity:** Low

**Files:**
- Modify: `resources/js/pages/app/settings/transaction-templates/show.svelte` (schedule link + delete warning)

**Interfaces:**
- Consumes: backend `schedule_id: number | null` prop (backend plan Task 8); Wayfinder `Settings/TransactionScheduleController.edit.url({ schedule })`.

- [ ] **Step 1: Add the prop, link chip, and warning copy**

In the script block, add the prop and import:

```ts
    import TransactionScheduleController from '@wayfinder/App/Http/Controllers/Settings/TransactionScheduleController';

    let { template, schedule_id = null }: { template: App.Models.TransactionTemplate; schedule_id?: number | null } = $props();
```

Between the `<Card title={template.name}>` block and the edit/delete button row, insert:

```svelte
        {#if schedule_id}
            <Button
                href={TransactionScheduleController.edit.url({ schedule: schedule_id })}
                variant="outline">
                <i class="iconify size-5 solar--calendar-minimalistic-duotone"></i>
                Scheduled — manage
            </Button>
        {/if}
```

And extend the `ConfirmationModal` body so the cascade is never a surprise:

```svelte
    This will permanently delete the template. It cannot be undone.
    {#if schedule_id}
        Its schedule and run history will be removed too — transactions already created stay.
    {/if}
```

---

### Task 6: Nav entry + README

**Complexity:** Low

**Files:**
- Modify: `resources/js/data/menu.ts` (settings menu entry)
- Modify: `README.md` (roadmap item moves to the implemented narrative)

**Interfaces:**
- Consumes: Wayfinder named route `@wayfinder/routes/transaction-schedules`.
- Produces: "Scheduled Transactions" in the Settings nav; README reflects shipped state.

- [ ] **Step 1: Add the nav entry**

In `resources/js/data/menu.ts` — add the import beside the templates import:

```ts
    import transactionSchedules from '@wayfinder/routes/transaction-schedules';
```

and inside `settingsMenu.menus`, directly after `transaction_templates`:

```ts
        transaction_schedules: {
            name: 'Scheduled Transactions',
            url: transactionSchedules.index().url,
            route: 'transaction-schedules.index',
            active: 'transaction-schedules.*',
            icon: 'iconify solar--calendar-minimalistic-duotone',
            type: 'menu',
        },
```

- [ ] **Step 2: Move the README roadmap item**

Find the roadmap "Scheduled Transaction" item and replace its wording with the shipped narrative:

```markdown
- **Scheduled transactions** — schedule any template daily, weekly, every 2 weeks, monthly, monthly on chosen dates, or yearly (plus one-offs). Occurrences auto-post or queue for a one-tap confirmation on the dashboard; missed dates catch up honestly when the app reopens.
```

Move it out of the roadmap/next list into the implemented features list (wherever the templates item already lives after that feature shipped).

---

## Self-Review

1. **Spec coverage** — §9 settings pages (Tasks 2–3, nav Task 6), §9 badges-for-enums (Task 1), §9 template-detail link + delete warning (Task 5), §10 due card + dashboard prop (Task 4), §"Frontend loop" thin-client rules (Global Constraints + POST-only actions in Tasks 2/4), §13 README (Task 6). Template-index badge was explicitly cut from the spec (Settings page owns schedules) — correctly absent. No gaps.
2. **Placeholders** — none; every step is complete code or an exact insertion point.
3. **Type consistency** — `ScheduleParams` + `ScheduleFrequencyHelper.summary(frequency, params)` defined in Task 1, consumed in Task 2; `ScheduleListItem` shape identical in Task 2's two files; `PendingRun`/`UpcomingItem` identical between Task 4's component and dashboard edit; `formId="schedule-form"` matches the page's `FormAction`; payload keys in Task 3's `transform` match the backend request's rule list exactly.
4. **Workflow mode** — `direct`: no run/verify/commit steps in any task and no final verification task; `pnpm run build` is user-run.
5. **Testing scope** — zero frontend test files, matching the house's established convention (no Svelte-layer tests exist); noted explicitly in Global Constraints so the omission reads as deliberate, not forgotten.
6. **Complexity** — Tasks 2–4 Medium, Tasks 1/5/6 Low; overall: **Medium**.
