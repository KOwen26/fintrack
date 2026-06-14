# Insights — Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. Activate `inertia-svelte-development` skill when writing any Inertia/Svelte page or component.

**Goal:** Build the read-only Insights frontend — five module components (chart, gauge, badge), three Inertia report pages, and `app.ts` layout registration for the `reports/` prefix.

**Architecture:** Svelte 5 runes throughout. All report pages are read-only — no forms, no FormGenerator. Charts wrap the existing `ChartContainer` + `ChartTooltip` from `resources/js/components/ui/atoms/chart/` (which uses layerchart internally). Module components live in `resources/js/components/module/report/`. Date ranges persist in URL query params via Inertia's `router.visit`. Wayfinder (next branch) provides all typed route calls — no hardcoded URLs.

**Tech Stack:** Svelte 5, TypeScript, Inertia.js v3, Tailwind v4, DaisyUI, layerchart (via chart atoms), Wayfinder (next)

**Depends on:** Foundation, Ledger, and Automation specs fully implemented. Insights backend plan (`2026-06-14-insights-backend.md`) fully complete.

---

## Component Reference (existing — always prefer these)

| Component | Import | Key Props |
|-----------|--------|-----------|
| `Badge` | `@components/ui/badge.svelte` | `color`, `variant` (solid/outline/soft) |
| `Button` | `@components/ui/button.svelte` | `color`, `variant`, `href` |
| `Card` | `@components/ui/card.svelte` | `title`, `wrapperClass` |
| `DataList` | `@components/data/data-list.svelte` | `data: [{label, value}]` |
| `ChartContainer` | `@components/ui/atoms/chart` | `config: ChartConfig` — wraps layerchart chart |
| `ChartTooltip` | `@components/ui/atoms/chart` | `hideLabel`, `indicator`, `labelFormatter` |

### Chart atom API summary

`ChartContainer` wraps a `<div>` that sets a chart context via `setChartContext()`. The `config` prop is a `ChartConfig` object mapping data keys to `{ label, color }` entries. Actual chart primitives come from `layerchart` — import `Chart`, `Bars`, `Pie`, `BarStack`, `LinearGradientSvg`, `Tooltip`, `Axis` etc. directly from `layerchart`. `ChartTooltip` is used inside a `<Tooltip.Root>` context.

---

## File Map

```
resources/js/components/module/report/
  credit-alert-badge.svelte           ← AlertLevel → Badge
  trend-chart.svelte                  ← dual-bar month chart
  category-leak-chart.svelte          ← pie/donut chart
  contribution-gauge.svelte           ← per-member bar/gauge display
  credit-utilization-gauge.svelte     ← arc gauge with alert level color

resources/js/pages/reports/
  index.svelte                        ← dashboard: trend + category leak
  trend.svelte                        ← full-page trend chart
  category-leak.svelte                ← full-page category leak
  contribution-split.svelte           ← joint contribution split
  credit-utilization.svelte           ← credit card gauge page
  fixed-vs-variable.svelte            ← fixed vs variable breakdown

resources/js/app.ts                   ← modify: add reports layout case
```

---

## Task 1: Generate Wayfinder Types

- [ ] **Run Wayfinder generation after backend is complete**

```bash
php artisan wayfinder:generate --no-interaction
```

Expected: `resources/js/wayfinder/App/Http/Controllers/ReportsController.ts` exists with exported `index`, `trend`, `categoryLeak`, `contributionSplit`, `creditUtilization`, and `fixedVsVariable` action functions. `resources/js/wayfinder/App/Enums/AlertLevel.ts` exists.

---

## Task 2: Read Chart Components (REQUIRED before writing any chart)

- [ ] **Read `resources/js/components/ui/atoms/chart/chart-container.svelte`** — understand `ChartConfig` shape and how `setChartContext` wires the config.
- [ ] **Read `resources/js/components/ui/atoms/chart/chart-utils.ts`** — understand `ChartConfig` type, `THEMES`, `useChart()`, `setChartContext()`.
- [ ] **Read `resources/js/components/ui/atoms/chart/index.ts`** — confirm the public exports (`ChartContainer`, `ChartTooltip`, `getPayloadConfigFromPayload`, `ChartConfig`).

Key facts from reading:
- `ChartContainer` accepts a `config: ChartConfig` prop where keys match data series names.
- Chart content is rendered via `children` snippet — use `layerchart` primitives directly inside.
- `ChartTooltip` must be rendered inside a `layerchart` `<Tooltip.Root>` context (provided by layerchart's `<Chart>` component).
- Import chart atoms: `import { ChartContainer, ChartTooltip, type ChartConfig } from '@components/ui/atoms/chart';`
- Import layerchart primitives: `import { Chart, Bars, Axis, Tooltip, Pie, Arc } from 'layerchart';`

---

## Task 3: Module Report Components

### 3.1 Credit Alert Badge

- [ ] **Create `resources/js/components/module/report/credit-alert-badge.svelte`**

Maps the `AlertLevel` enum to a DaisyUI badge color. Uses Wayfinder-generated enum constants as keys so a rename propagates as a TypeScript error.

```svelte
<script lang="ts">
    import AlertLevel from '@wayfinder/App/Enums/AlertLevel';
    import type { App } from '@wayfinder/types';
    import type { ColorVariant } from '@/data/theme';
    import Badge from '@components/ui/badge.svelte';

    let { level }: { level: App.Enums.AlertLevel } = $props();

    const config: Record<App.Enums.AlertLevel, { label: string; color: ColorVariant }> = {
        [AlertLevel.Normal]:   { label: 'Normal',    color: 'success' },
        [AlertLevel.Warning]:  { label: 'Warning',   color: 'warning' },
        [AlertLevel.HighRisk]: { label: 'High Risk', color: 'error'   },
    };

    const badge = $derived(config[level]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
```

### 3.2 Trend Chart

- [ ] **Create `resources/js/components/module/report/trend-chart.svelte`**

Dual grouped-bar chart: income (success color) vs expense (error color) per month. Renders using `layerchart`'s `<Chart>` + `<Bars>` + `<Axis>` inside a `<ChartContainer>`.

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';
    import { ChartContainer, ChartTooltip, type ChartConfig } from '@components/ui/atoms/chart';
    import { Chart, Bars, Axis, Tooltip } from 'layerchart';

    interface TrendMonth {
        year: number;
        month: number;
        income: number;
        expense: number;
        net: number;
        surplus_rate: number;
    }

    let { months }: { months: TrendMonth[] } = $props();

    const chartConfig: ChartConfig = {
        income:  { label: 'Income',  color: 'hsl(var(--su))'  },
        expense: { label: 'Expense', color: 'hsl(var(--er))'  },
    };

    // Format month labels as "Jan", "Feb", etc.
    function monthLabel(month: number): string {
        return new Date(2000, month - 1, 1).toLocaleString('default', { month: 'short' });
    }

    const chartData = $derived(
        months.map((m) => ({
            label:   `${monthLabel(m.month)} ${m.year}`,
            income:  m.income,
            expense: m.expense,
        }))
    );
</script>

<ChartContainer config={chartConfig} class="min-h-[220px] w-full">
    <Chart
        data={chartData}
        x="label"
        xScale={{ padding: 0.2 }}
        yDomain={[0, null]}
        padding={{ left: 16, bottom: 24 }}
    >
        <Axis placement="bottom" format={(v) => String(v)} />
        <Bars key="income" color="var(--color-income)" radius={3} />
        <Bars key="expense" color="var(--color-expense)" radius={3} />
        <Tooltip.Root>
            <ChartTooltip indicator="line" />
        </Tooltip.Root>
    </Chart>
</ChartContainer>

{#if months.length === 0}
    <div class="flex flex-col items-center justify-center py-10 text-base-content/40">
        <i class="iconify mb-2 size-8 ph--chart-bar-bold"></i>
        <p class="text-sm">No transaction data yet</p>
    </div>
{/if}
```

### 3.3 Category Leak Chart

- [ ] **Create `resources/js/components/module/report/category-leak-chart.svelte`**

Pie/donut chart of category spend share. Each slice uses the category's own `color` field.

```svelte
<script lang="ts">
    import type { ChartConfig } from '@components/ui/atoms/chart';
    import { ChartContainer, ChartTooltip } from '@components/ui/atoms/chart';
    import { Chart, Pie, Arc, Tooltip } from 'layerchart';

    interface CategoryItem {
        name:       string;
        color:      string;
        icon:       string;
        total:      number;
        percentage: number;
    }

    let { categories, period_total }: { categories: CategoryItem[]; period_total: number } = $props();

    // Build ChartConfig keyed by category name — colors come from category data
    const chartConfig = $derived<ChartConfig>(
        Object.fromEntries(
            categories.map((c) => [c.name, { label: c.name, color: c.color }])
        )
    );

    function formatIDR(value: number): string {
        return value.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
    }
</script>

{#if categories.length === 0}
    <div class="flex flex-col items-center justify-center py-10 text-base-content/40">
        <i class="iconify mb-2 size-8 ph--chart-donut-bold"></i>
        <p class="text-sm">No expense data for this period</p>
    </div>
{:else}
    <ChartContainer config={chartConfig} class="mx-auto min-h-[200px] w-full max-w-xs">
        <Chart
            data={categories}
            key="name"
            value="total"
        >
            <Pie innerRadius={50}>
                {#each categories as cat}
                    <Arc color={cat.color} />
                {/each}
            </Pie>
            <Tooltip.Root>
                <ChartTooltip indicator="dot" />
            </Tooltip.Root>
        </Chart>
    </ChartContainer>

    <!-- Legend / ranked list -->
    <ul class="mt-4 space-y-2">
        {#each categories as cat (cat.name)}
            <li class="flex items-center justify-between text-sm">
                <div class="flex items-center gap-2 min-w-0">
                    <span
                        class="inline-block size-2.5 shrink-0 rounded-[2px]"
                        style="background-color: {cat.color}"
                    ></span>
                    <span class="truncate">{cat.name}</span>
                </div>
                <div class="ml-4 shrink-0 text-right">
                    <span class="font-mono font-medium">{formatIDR(cat.total)}</span>
                    <span class="ml-1 text-base-content/50">{cat.percentage}%</span>
                </div>
            </li>
        {/each}
    </ul>
{/if}
```

### 3.4 Contribution Gauge

- [ ] **Create `resources/js/components/module/report/contribution-gauge.svelte`**

Stacked horizontal progress bars — one segment per member, colored by their contribution share.

```svelte
<script lang="ts">
    interface Member {
        name:        string;
        contributed: number;
        percentage:  number;
    }

    let { members, total }: { members: Member[]; total: number } = $props();

    // Assign a DaisyUI color class per member position (predictable order)
    const memberColors = ['bg-primary', 'bg-secondary', 'bg-accent', 'bg-info', 'bg-success'];

    function formatIDR(value: number): string {
        return value.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
    }
</script>

{#if members.length === 0}
    <div class="flex flex-col items-center justify-center py-8 text-base-content/40">
        <i class="iconify mb-2 size-8 ph--users-bold"></i>
        <p class="text-sm">No income recorded this period</p>
    </div>
{:else}
    <!-- Stacked bar -->
    <div class="flex h-6 w-full overflow-hidden rounded-full">
        {#each members as member, i (member.name)}
            <div
                class="{memberColors[i % memberColors.length]} transition-all"
                style="width: {member.percentage}%"
                title="{member.name}: {member.percentage}%"
            ></div>
        {/each}
    </div>

    <!-- Legend -->
    <ul class="mt-4 space-y-2">
        {#each members as member, i (member.name)}
            <li class="flex items-center justify-between text-sm">
                <div class="flex items-center gap-2">
                    <span class="inline-block size-2.5 rounded-full {memberColors[i % memberColors.length]}"></span>
                    <span class="font-medium">{member.name}</span>
                </div>
                <div class="text-right">
                    <span class="font-mono">{formatIDR(member.contributed)}</span>
                    <span class="ml-1 text-base-content/50">{member.percentage}%</span>
                </div>
            </li>
        {/each}
    </ul>

    <p class="mt-3 text-right text-xs text-base-content/40">
        Total: {formatIDR(total)}
    </p>
{/if}
```

### 3.5 Credit Utilization Gauge

- [ ] **Create `resources/js/components/module/report/credit-utilization-gauge.svelte`**

Arc/radial progress gauge that colors itself based on alert level. Uses a CSS `conic-gradient` approach to avoid a full chart library import for a single gauge.

```svelte
<script lang="ts">
    import AlertLevel from '@wayfinder/App/Enums/AlertLevel';
    import type { App } from '@wayfinder/types';
    import CreditAlertBadge from './credit-alert-badge.svelte';

    let {
        limit,
        used,
        available,
        utilization_pct,
        alert_level,
    }: {
        limit:           number;
        used:            number;
        available:       number;
        utilization_pct: number;
        alert_level:     App.Enums.AlertLevel;
    } = $props();

    // DaisyUI semantic color tokens mapped by alert level
    const gaugeColorClass = $derived<string>({
        [AlertLevel.Normal]:   'text-success',
        [AlertLevel.Warning]:  'text-warning',
        [AlertLevel.HighRisk]: 'text-error',
    }[alert_level] ?? 'text-success');

    // Radial progress uses a CSS custom property --value (0–100)
    const pct = $derived(Math.min(100, Math.round(utilization_pct)));

    function formatIDR(value: number): string {
        return value.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
    }
</script>

<div class="flex flex-col items-center gap-4">
    <!-- DaisyUI radial-progress gauge -->
    <div
        class="radial-progress {gaugeColorClass} text-xl font-bold"
        style="--value:{pct}; --size:10rem; --thickness:1rem;"
        role="progressbar"
        aria-valuenow={pct}
        aria-valuemin={0}
        aria-valuemax={100}
    >
        {pct}%
    </div>

    <CreditAlertBadge level={alert_level} />

    <!-- Stats grid -->
    <div class="grid w-full grid-cols-3 gap-2 text-center text-sm">
        <div>
            <p class="text-xs text-base-content/50">Limit</p>
            <p class="font-mono font-semibold">{formatIDR(limit)}</p>
        </div>
        <div>
            <p class="text-xs text-base-content/50">Used</p>
            <p class="font-mono font-semibold text-error">{formatIDR(used)}</p>
        </div>
        <div>
            <p class="text-xs text-base-content/50">Available</p>
            <p class="font-mono font-semibold text-success">{formatIDR(available)}</p>
        </div>
    </div>
</div>
```

---

## Task 4: Update `app.ts` — Add Reports Layout

- [ ] **Update `resources/js/app.ts` — add `reports` layout case**

Add the `reports` case inside the existing `layout()` function alongside `accounts`, `categories`, etc.:

```typescript
case name.startsWith('reports'):
    return AppLayout;
```

The full updated `layout()` switch should be:

```typescript
layout: (name) => {
    switch (true) {
        case name.startsWith('dev'):
        case name.startsWith('dashboard'):
            return DashboardLayout;

        case name.startsWith('accounts'):
        case name.startsWith('categories'):
        case name.startsWith('household'):
        case name.startsWith('settings/theme'):
        case name.startsWith('reports'):
            return AppLayout;

        default:
            return null;
    }
},
```

`AppLayout` must already be imported (added in Foundation frontend plan):

```typescript
import AppLayout from '@components/layouts/app-layout.svelte';
```

---

## Task 5: Report Pages

### 5.1 Reports Index (Dashboard)

- [ ] **Create `resources/js/pages/reports/index.svelte`**

Mobile-first stacked dashboard: trend chart above the fold, category leak below. Date range from URL params (defaults to current month). The `account` prop determines which account's data is shown.

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';
    import { ReportsController } from '@wayfinder/App/Http/Controllers/ReportsController';
    import { router } from '@inertiajs/svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import TrendChart from '@components/module/report/trend-chart.svelte';
    import CategoryLeakChart from '@components/module/report/category-leak-chart.svelte';

    interface TrendMonth {
        year: number; month: number; income: number; expense: number; net: number; surplus_rate: number;
    }
    interface TrendReport { months: TrendMonth[]; months_count: number; }
    interface CategoryItem { name: string; color: string; icon: string; total: number; percentage: number; }
    interface CategoryLeakReport { categories: CategoryItem[]; period_total: number; from: string; to: string; }

    let {
        account,
        trend,
        category_leak,
        from,
        to,
    }: {
        account:       App.Models.Account;
        trend:         TrendReport;
        category_leak: CategoryLeakReport;
        from:          string;
        to:            string;
    } = $props();

    function formatIDR(value: number): string {
        return value.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
    }

    // Current month summary (last item in trend array)
    const latestMonth = $derived(trend.months.at(-1));

    function navigatePeriod(direction: 'prev' | 'next') {
        const current = new Date(from);
        const offset  = direction === 'prev' ? -1 : 1;
        current.setMonth(current.getMonth() + offset);
        const newFrom = new Date(current.getFullYear(), current.getMonth(), 1);
        const newTo   = new Date(current.getFullYear(), current.getMonth() + 1, 0);

        router.visit(
            ReportsController.index.url({
                account: account.id,
                query:   {
                    from: newFrom.toISOString().slice(0, 10),
                    to:   newTo.toISOString().slice(0, 10),
                },
            }),
            { preserveScroll: true }
        );
    }

    const periodLabel = $derived(
        new Date(from).toLocaleString('default', { month: 'long', year: 'numeric' })
    );
</script>

<div class="p-4">
    <!-- Header -->
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold">Reports</h1>
            <p class="text-sm text-base-content/50">{account.name}</p>
        </div>
        <Button
            color="light"
            variant="ghost"
            href={ReportsController.creditUtilization.url({ account: account.id })}
            class="btn-sm"
        >
            <i class="iconify size-4 ph--chart-pie-bold"></i>
        </Button>
    </div>

    <!-- Period nav -->
    <div class="mb-4 flex items-center justify-between rounded-xl bg-base-200 px-3 py-2">
        <Button color="light" variant="ghost" class="btn-xs btn-circle" onclick={() => navigatePeriod('prev')}>
            <i class="iconify size-4 ph--caret-left-bold"></i>
        </Button>
        <span class="text-sm font-medium">{periodLabel}</span>
        <Button color="light" variant="ghost" class="btn-xs btn-circle" onclick={() => navigatePeriod('next')}>
            <i class="iconify size-4 ph--caret-right-bold"></i>
        </Button>
    </div>

    <!-- Current month summary cards -->
    {#if latestMonth}
        <div class="mb-4 grid grid-cols-3 gap-3">
            <Card wrapperClass="text-center">
                <p class="text-xs text-base-content/50">Income</p>
                <p class="font-mono text-sm font-bold text-success">{formatIDR(latestMonth.income)}</p>
            </Card>
            <Card wrapperClass="text-center">
                <p class="text-xs text-base-content/50">Expense</p>
                <p class="font-mono text-sm font-bold text-error">{formatIDR(latestMonth.expense)}</p>
            </Card>
            <Card wrapperClass="text-center">
                <p class="text-xs text-base-content/50">Net</p>
                <p class="font-mono text-sm font-bold {latestMonth.net >= 0 ? 'text-success' : 'text-error'}">
                    {formatIDR(latestMonth.net)}
                </p>
            </Card>
        </div>
    {/if}

    <!-- Trend chart card -->
    <Card title="Income vs Expense" wrapperClass="mb-4" headerAction={null}>
        {#snippet headerAction()}
            <Button
                color="light"
                variant="ghost"
                class="btn-xs"
                href={ReportsController.trend.url({ account: account.id })}
            >
                Full view
            </Button>
        {/snippet}
        <TrendChart months={trend.months} />
    </Card>

    <!-- Category leak card -->
    <Card title="Top Spending Categories" wrapperClass="mb-4">
        {#snippet headerAction()}
            <Button
                color="light"
                variant="ghost"
                class="btn-xs"
                href={ReportsController.categoryLeak.url({
                    account: account.id,
                    query: { from, to },
                })}
            >
                Full view
            </Button>
        {/snippet}
        <CategoryLeakChart
            categories={category_leak.categories.slice(0, 5)}
            period_total={category_leak.period_total}
        />
    </Card>

    <!-- Report nav links -->
    <div class="space-y-2">
        <a
            href={ReportsController.fixedVsVariable.url({ account: account.id, query: { from, to } })}
            class="flex items-center justify-between rounded-xl bg-base-200 px-4 py-3 text-sm font-medium transition-opacity active:opacity-70"
        >
            <div class="flex items-center gap-2">
                <i class="iconify size-5 ph--sliders-horizontal-bold text-secondary"></i>
                Fixed vs Variable
            </div>
            <i class="iconify size-4 ph--caret-right-bold text-base-content/30"></i>
        </a>

        <a
            href={ReportsController.contributionSplit.url({ account: account.id, query: { from, to } })}
            class="flex items-center justify-between rounded-xl bg-base-200 px-4 py-3 text-sm font-medium transition-opacity active:opacity-70"
        >
            <div class="flex items-center gap-2">
                <i class="iconify size-5 ph--users-bold text-accent"></i>
                Contribution Split
            </div>
            <i class="iconify size-4 ph--caret-right-bold text-base-content/30"></i>
        </a>
    </div>
</div>
```

### 5.2 Trend Page (Full View)

- [ ] **Create `resources/js/pages/reports/trend.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';
    import { ReportsController } from '@wayfinder/App/Http/Controllers/ReportsController';
    import { router } from '@inertiajs/svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import Badge from '@components/ui/badge.svelte';
    import TrendChart from '@components/module/report/trend-chart.svelte';

    interface TrendMonth {
        year: number; month: number; income: number; expense: number; net: number; surplus_rate: number;
    }
    interface TrendReport { months: TrendMonth[]; months_count: number; }

    let {
        account,
        trend,
        months,
    }: {
        account: App.Models.Account;
        trend:   TrendReport;
        months:  number;
    } = $props();

    function setMonths(m: number) {
        router.visit(ReportsController.trend.url({ account: account.id, query: { months: m } }), {
            preserveScroll: true,
        });
    }

    function formatIDR(value: number): string {
        return value.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
    }

    const totalIncome  = $derived(trend.months.reduce((s, m) => s + m.income, 0));
    const totalExpense = $derived(trend.months.reduce((s, m) => s + m.expense, 0));
    const totalNet     = $derived(totalIncome - totalExpense);
</script>

<div class="p-4">
    <div class="mb-4 flex items-center gap-3">
        <Button
            color="light"
            variant="ghost"
            href={ReportsController.index.url({ account: account.id })}
            class="btn-circle btn-sm"
        >
            <i class="iconify size-5 ph--arrow-left-bold"></i>
        </Button>
        <div>
            <h1 class="text-xl font-bold">Income vs Expense</h1>
            <p class="text-sm text-base-content/50">{account.name}</p>
        </div>
    </div>

    <!-- Month selector -->
    <div class="mb-4 flex gap-2">
        {#each [3, 6, 12] as m (m)}
            <Button
                color={months === m ? 'primary' : 'light'}
                variant={months === m ? 'solid' : 'outline'}
                class="btn-sm flex-1"
                onclick={() => setMonths(m)}
            >
                {m}M
            </Button>
        {/each}
    </div>

    <!-- Trend chart -->
    <Card wrapperClass="mb-4">
        <TrendChart months={trend.months} />
    </Card>

    <!-- Totals summary -->
    <div class="grid grid-cols-3 gap-3 mb-4">
        <Card wrapperClass="text-center">
            <p class="text-xs text-base-content/50">Total Income</p>
            <p class="font-mono text-sm font-bold text-success">{formatIDR(totalIncome)}</p>
        </Card>
        <Card wrapperClass="text-center">
            <p class="text-xs text-base-content/50">Total Expense</p>
            <p class="font-mono text-sm font-bold text-error">{formatIDR(totalExpense)}</p>
        </Card>
        <Card wrapperClass="text-center">
            <p class="text-xs text-base-content/50">Net</p>
            <p class="font-mono text-sm font-bold {totalNet >= 0 ? 'text-success' : 'text-error'}">
                {formatIDR(totalNet)}
            </p>
        </Card>
    </div>

    <!-- Per-month breakdown table -->
    <Card title="Monthly Breakdown">
        <div class="overflow-x-auto">
            <table class="table table-sm w-full">
                <thead>
                    <tr class="text-xs text-base-content/50">
                        <th>Month</th>
                        <th class="text-right">Income</th>
                        <th class="text-right">Expense</th>
                        <th class="text-right">Net</th>
                        <th class="text-right">Rate</th>
                    </tr>
                </thead>
                <tbody>
                    {#each [...trend.months].reverse() as m (m.year + '-' + m.month)}
                        <tr class="text-sm">
                            <td class="font-medium">
                                {new Date(m.year, m.month - 1).toLocaleString('default', { month: 'short', year: '2-digit' })}
                            </td>
                            <td class="text-right font-mono text-success">{formatIDR(m.income)}</td>
                            <td class="text-right font-mono text-error">{formatIDR(m.expense)}</td>
                            <td class="text-right font-mono {m.net >= 0 ? 'text-success' : 'text-error'}">{formatIDR(m.net)}</td>
                            <td class="text-right">
                                <Badge
                                    color={m.surplus_rate >= 20 ? 'success' : m.surplus_rate >= 0 ? 'warning' : 'error'}
                                    variant="soft"
                                >
                                    {m.surplus_rate.toFixed(0)}%
                                </Badge>
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </Card>
</div>
```

### 5.3 Category Leak Page (Full View)

- [ ] **Create `resources/js/pages/reports/category-leak.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';
    import { ReportsController } from '@wayfinder/App/Http/Controllers/ReportsController';
    import { router } from '@inertiajs/svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import CategoryLeakChart from '@components/module/report/category-leak-chart.svelte';

    interface CategoryItem { name: string; color: string; icon: string; total: number; percentage: number; }
    interface CategoryLeakReport { categories: CategoryItem[]; period_total: number; from: string; to: string; }

    let {
        account,
        category_leak,
        from,
        to,
    }: {
        account:       App.Models.Account;
        category_leak: CategoryLeakReport;
        from:          string;
        to:            string;
    } = $props();

    function navigatePeriod(direction: 'prev' | 'next') {
        const current = new Date(from);
        current.setMonth(current.getMonth() + (direction === 'prev' ? -1 : 1));
        const newFrom = new Date(current.getFullYear(), current.getMonth(), 1);
        const newTo   = new Date(current.getFullYear(), current.getMonth() + 1, 0);

        router.visit(
            ReportsController.categoryLeak.url({
                account: account.id,
                query: {
                    from: newFrom.toISOString().slice(0, 10),
                    to:   newTo.toISOString().slice(0, 10),
                },
            }),
            { preserveScroll: true }
        );
    }

    const periodLabel = $derived(
        new Date(from).toLocaleString('default', { month: 'long', year: 'numeric' })
    );
</script>

<div class="p-4">
    <div class="mb-4 flex items-center gap-3">
        <Button
            color="light"
            variant="ghost"
            href={ReportsController.index.url({ account: account.id })}
            class="btn-circle btn-sm"
        >
            <i class="iconify size-5 ph--arrow-left-bold"></i>
        </Button>
        <div>
            <h1 class="text-xl font-bold">Category Breakdown</h1>
            <p class="text-sm text-base-content/50">{account.name}</p>
        </div>
    </div>

    <!-- Period nav -->
    <div class="mb-4 flex items-center justify-between rounded-xl bg-base-200 px-3 py-2">
        <Button color="light" variant="ghost" class="btn-xs btn-circle" onclick={() => navigatePeriod('prev')}>
            <i class="iconify size-4 ph--caret-left-bold"></i>
        </Button>
        <span class="text-sm font-medium">{periodLabel}</span>
        <Button color="light" variant="ghost" class="btn-xs btn-circle" onclick={() => navigatePeriod('next')}>
            <i class="iconify size-4 ph--caret-right-bold"></i>
        </Button>
    </div>

    <Card wrapperClass="mb-4">
        <CategoryLeakChart
            categories={category_leak.categories}
            period_total={category_leak.period_total}
        />
    </Card>
</div>
```

### 5.4 Contribution Split Page

- [ ] **Create `resources/js/pages/reports/contribution-split.svelte`**

Shows empty state for personal accounts (`is_joint: false`). Shows contribution gauges for joint accounts.

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';
    import { ReportsController } from '@wayfinder/App/Http/Controllers/ReportsController';
    import { router } from '@inertiajs/svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import ContributionGauge from '@components/module/report/contribution-gauge.svelte';

    interface Member { name: string; contributed: number; percentage: number; }
    interface ContributionSplit { is_joint: boolean; members: Member[]; total: number; from: string; to: string; }

    let {
        account,
        contribution_split,
        from,
        to,
    }: {
        account:             App.Models.Account;
        contribution_split:  ContributionSplit;
        from:                string;
        to:                  string;
    } = $props();

    function navigatePeriod(direction: 'prev' | 'next') {
        const current = new Date(from);
        current.setMonth(current.getMonth() + (direction === 'prev' ? -1 : 1));
        const newFrom = new Date(current.getFullYear(), current.getMonth(), 1);
        const newTo   = new Date(current.getFullYear(), current.getMonth() + 1, 0);

        router.visit(
            ReportsController.contributionSplit.url({
                account: account.id,
                query: {
                    from: newFrom.toISOString().slice(0, 10),
                    to:   newTo.toISOString().slice(0, 10),
                },
            }),
            { preserveScroll: true }
        );
    }

    const periodLabel = $derived(
        new Date(from).toLocaleString('default', { month: 'long', year: 'numeric' })
    );
</script>

<div class="p-4">
    <div class="mb-4 flex items-center gap-3">
        <Button
            color="light"
            variant="ghost"
            href={ReportsController.index.url({ account: account.id })}
            class="btn-circle btn-sm"
        >
            <i class="iconify size-5 ph--arrow-left-bold"></i>
        </Button>
        <div>
            <h1 class="text-xl font-bold">Contribution Split</h1>
            <p class="text-sm text-base-content/50">{account.name}</p>
        </div>
    </div>

    {#if !contribution_split.is_joint}
        <!-- Personal account empty state -->
        <div class="flex flex-col items-center justify-center py-16 text-center text-base-content/50">
            <i class="iconify mb-3 size-12 ph--users-slash-bold"></i>
            <p class="font-semibold">Joint accounts only</p>
            <p class="mt-1 max-w-xs text-sm">
                Contribution split is only available for joint accounts. This account is personal.
            </p>
        </div>
    {:else}
        <!-- Period nav -->
        <div class="mb-4 flex items-center justify-between rounded-xl bg-base-200 px-3 py-2">
            <Button color="light" variant="ghost" class="btn-xs btn-circle" onclick={() => navigatePeriod('prev')}>
                <i class="iconify size-4 ph--caret-left-bold"></i>
            </Button>
            <span class="text-sm font-medium">{periodLabel}</span>
            <Button color="light" variant="ghost" class="btn-xs btn-circle" onclick={() => navigatePeriod('next')}>
                <i class="iconify size-4 ph--caret-right-bold"></i>
            </Button>
        </div>

        <Card title="Income by Member" wrapperClass="mb-4">
            <ContributionGauge
                members={contribution_split.members}
                total={contribution_split.total}
            />
        </Card>
    {/if}
</div>
```

### 5.5 Credit Utilization Page

- [ ] **Create `resources/js/pages/reports/credit-utilization.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';
    import { ReportsController } from '@wayfinder/App/Http/Controllers/ReportsController';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import CreditUtilizationGauge from '@components/module/report/credit-utilization-gauge.svelte';

    interface CreditUtilization {
        limit:           number;
        used:            number;
        available:       number;
        utilization_pct: number;
        alert_level:     App.Enums.AlertLevel;
    }

    let {
        account,
        credit_utilization,
    }: {
        account:            App.Models.Account;
        credit_utilization: CreditUtilization;
    } = $props();
</script>

<div class="p-4">
    <div class="mb-4 flex items-center gap-3">
        <Button
            color="light"
            variant="ghost"
            href={ReportsController.index.url({ account: account.id })}
            class="btn-circle btn-sm"
        >
            <i class="iconify size-5 ph--arrow-left-bold"></i>
        </Button>
        <div>
            <h1 class="text-xl font-bold">Credit Utilization</h1>
            <p class="text-sm text-base-content/50">{account.name}</p>
        </div>
    </div>

    <Card wrapperClass="mb-4">
        <CreditUtilizationGauge
            limit={credit_utilization.limit}
            used={credit_utilization.used}
            available={credit_utilization.available}
            utilization_pct={credit_utilization.utilization_pct}
            alert_level={credit_utilization.alert_level}
        />
    </Card>

    <!-- Guidance text per alert level -->
    <Card>
        <div class="text-sm text-base-content/70">
            {#if credit_utilization.utilization_pct >= 70}
                <p class="font-semibold text-error">High risk — above 70%</p>
                <p class="mt-1">Your utilization is very high. This may affect your credit score. Pay down your balance as soon as possible.</p>
            {:else if credit_utilization.utilization_pct >= 30}
                <p class="font-semibold text-warning">Caution — 30% to 69%</p>
                <p class="mt-1">Your utilization is elevated. Aim to keep it below 30% to maintain a healthy credit profile.</p>
            {:else}
                <p class="font-semibold text-success">Healthy — below 30%</p>
                <p class="mt-1">Your utilization is within a healthy range. Keep it here to protect your credit score.</p>
            {/if}
        </div>
    </Card>
</div>
```

### 5.6 Fixed vs Variable Page

- [ ] **Create `resources/js/pages/reports/fixed-vs-variable.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';
    import { ReportsController } from '@wayfinder/App/Http/Controllers/ReportsController';
    import { router } from '@inertiajs/svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import Badge from '@components/ui/badge.svelte';

    interface FixedVariable {
        fixed_total:    number;
        variable_total: number;
        fixed_pct:      number;
        variable_pct:   number;
        safety_margin:  number;
        from:           string;
        to:             string;
    }

    let {
        account,
        fixed_vs_variable,
        from,
        to,
    }: {
        account:           App.Models.Account;
        fixed_vs_variable: FixedVariable;
        from:              string;
        to:                string;
    } = $props();

    function navigatePeriod(direction: 'prev' | 'next') {
        const current = new Date(from);
        current.setMonth(current.getMonth() + (direction === 'prev' ? -1 : 1));
        const newFrom = new Date(current.getFullYear(), current.getMonth(), 1);
        const newTo   = new Date(current.getFullYear(), current.getMonth() + 1, 0);

        router.visit(
            ReportsController.fixedVsVariable.url({
                account: account.id,
                query: {
                    from: newFrom.toISOString().slice(0, 10),
                    to:   newTo.toISOString().slice(0, 10),
                },
            }),
            { preserveScroll: true }
        );
    }

    const periodLabel = $derived(
        new Date(from).toLocaleString('default', { month: 'long', year: 'numeric' })
    );

    const grandTotal = $derived(fixed_vs_variable.fixed_total + fixed_vs_variable.variable_total);

    function formatIDR(value: number): string {
        return value.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
    }
</script>

<div class="p-4">
    <div class="mb-4 flex items-center gap-3">
        <Button
            color="light"
            variant="ghost"
            href={ReportsController.index.url({ account: account.id })}
            class="btn-circle btn-sm"
        >
            <i class="iconify size-5 ph--arrow-left-bold"></i>
        </Button>
        <div>
            <h1 class="text-xl font-bold">Fixed vs Variable</h1>
            <p class="text-sm text-base-content/50">{account.name}</p>
        </div>
    </div>

    <!-- Period nav -->
    <div class="mb-4 flex items-center justify-between rounded-xl bg-base-200 px-3 py-2">
        <Button color="light" variant="ghost" class="btn-xs btn-circle" onclick={() => navigatePeriod('prev')}>
            <i class="iconify size-4 ph--caret-left-bold"></i>
        </Button>
        <span class="text-sm font-medium">{periodLabel}</span>
        <Button color="light" variant="ghost" class="btn-xs btn-circle" onclick={() => navigatePeriod('next')}>
            <i class="iconify size-4 ph--caret-right-bold"></i>
        </Button>
    </div>

    {#if grandTotal === 0}
        <div class="flex flex-col items-center justify-center py-16 text-center text-base-content/50">
            <i class="iconify mb-3 size-12 ph--sliders-horizontal-bold"></i>
            <p class="font-semibold">No expense data</p>
            <p class="mt-1 text-sm">No expense or fee transactions found for this period.</p>
        </div>
    {:else}
        <!-- Stacked bar -->
        <Card wrapperClass="mb-4">
            <p class="mb-3 text-sm font-medium">Spend composition</p>
            <div class="flex h-8 w-full overflow-hidden rounded-full">
                <div
                    class="bg-error transition-all"
                    style="width: {fixed_vs_variable.fixed_pct}%"
                    title="Fixed: {fixed_vs_variable.fixed_pct}%"
                ></div>
                <div
                    class="bg-info transition-all"
                    style="width: {fixed_vs_variable.variable_pct}%"
                    title="Variable: {fixed_vs_variable.variable_pct}%"
                ></div>
            </div>
            <div class="mt-3 flex justify-between text-xs">
                <div class="flex items-center gap-1.5">
                    <span class="inline-block size-2.5 rounded-[2px] bg-error"></span>
                    Fixed ({fixed_vs_variable.fixed_pct}%)
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="inline-block size-2.5 rounded-[2px] bg-info"></span>
                    Variable ({fixed_vs_variable.variable_pct}%)
                </div>
            </div>
        </Card>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-3 mb-4">
            <Card>
                <p class="text-xs text-base-content/50">Fixed costs</p>
                <p class="font-mono font-bold text-error">{formatIDR(fixed_vs_variable.fixed_total)}</p>
                <p class="mt-1 text-xs text-base-content/40">Rent, utilities, subscriptions</p>
            </Card>
            <Card>
                <p class="text-xs text-base-content/50">Variable</p>
                <p class="font-mono font-bold text-info">{formatIDR(fixed_vs_variable.variable_total)}</p>
                <p class="mt-1 text-xs text-base-content/40">Dining, shopping, entertainment</p>
            </Card>
        </div>

        <!-- Safety margin -->
        <Card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium">Spending Flexibility</p>
                    <p class="mt-0.5 text-xs text-base-content/50">
                        How much of your spend is discretionary (can be reduced if needed)
                    </p>
                </div>
                <Badge
                    color={fixed_vs_variable.safety_margin >= 50 ? 'success' : fixed_vs_variable.safety_margin >= 25 ? 'warning' : 'error'}
                    variant="soft"
                >
                    {fixed_vs_variable.safety_margin.toFixed(0)}%
                </Badge>
            </div>
        </Card>
    {/if}
</div>
```

---

## Task 6: Frontend Formatting + Type Check

- [ ] **Run Prettier and ESLint fix**

```bash
pnpm run format:all
```

- [ ] **Run lint check**

```bash
pnpm run lint:all
```

Expected: No errors. Fix any ESLint issues before proceeding.

- [ ] **Run Svelte type check**

```bash
pnpm run sv:check
```

Expected: No type errors. If Wayfinder types are missing for `ReportsController` or `AlertLevel`, re-run `php artisan wayfinder:generate --no-interaction` and then `composer generate:ts`.

---

## Task 7: Commit

- [ ] **Stage all new and modified frontend files**

```bash
git add resources/js/components/module/report/ resources/js/pages/reports/ resources/js/app.ts resources/js/wayfinder/
```

- [ ] **Commit**

```bash
git commit -m "$(cat <<'EOF'
feat(insights): add report pages and module chart components

Five read-only report pages (index, trend, category leak, contribution
split, credit utilization, fixed vs variable). Module components use
layerchart via existing chart atoms for trend and category charts.
DaisyUI radial-progress for credit utilization gauge. All routes via
Wayfinder — no hardcoded URLs. No forms, no FormGenerator.

Co-Authored-By: Claude Sonnet 4.6 (1M context) <noreply@anthropic.com>
EOF
)"
```
