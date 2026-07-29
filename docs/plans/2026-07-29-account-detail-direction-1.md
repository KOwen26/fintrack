# Implementation Plan: Account Detail Page — Direction 1 "Card Stack"

**Date:** 2026-07-29
**Target:** `resources/js/pages/accounts/show.svelte`
**Mockup reference:** `docs/mockups/account-detail-exploration.html` (Direction 1, lines 174–501)

---

## Overview

Rewrite the account detail page as a mobile-first vertical-scroll "card stack" layout. The current page is minimal (header + transaction list). Direction 1 adds a hero card, category spending with donut chart, and desktop stat cards — all in a single scrollable column.

**Layout flow (mobile → desktop):**
```
Mobile (360px):                     Desktop (768px+):
┌─────────────────────┐             ┌──────────────────────────────┐
│ Navigation          │             │ Navigation                   │
│                     │             │ Breadcrumb (desktop only)    │
│ ┌─────────────────┐ │             │ ┌──────────────────────────┐ │
│ │   Hero Card      │ │             │ │   Hero Card              │ │
│ │   (full bleed)   │ │             │ │   (decoration color)    │ │
│ │   Balance        │ │             │ │   Balance + actions      │ │
│ │   Quick actions  │ │             │ └──────────────────────────┘ │
│ │   (scroll X)     │ │             │ ┌──────────────────────────┐ │
│ └─────────────────┘ │             │ │ Spending by Category     │ │
│ ┌─────────────────┐ │             │ │ ┌─────┐ ┌──────────────┐ │ │
│ │ Spending by Cat  │ │             │ │ │donut│ │ category     │ │ │
│ │ ┌─────┐          │ │             │ │ │     │ │ list         │ │ │
│ │ │donut│ list     │ │             │ │ └─────┘ └──────────────┘ │ │
│ │ └─────┘          │ │             │ └──────────────────────────┘ │
│ └─────────────────┘ │             │ ┌──────────────────────────┐ │
│ ┌─────────────────┐ │             │ │ Recent Transactions      │ │
│ │ Recent Txns      │ │             │ │ (list)                  │ │
│ │ (list)           │ │             │ └──────────────────────────┘ │
│ └─────────────────┘ │             │ ┌──────┐ ┌──────┐ ┌──────┐ │ │
│ (no stat cards)     │             │ │Income│ │Expns │ │Savngs│ │
│                     │             │ └──────┘ └──────┘ └──────┘ │
└─────────────────────┘             └──────────────────────────────┘
```

---

## Current State

### `resources/js/pages/accounts/show.svelte` (45 lines)
- Receives `account: App.Models.Account` prop (with `provider`, `transactions` with `category` preloaded)
- Renders `DashboardPageHeader` with name, badges, Edit button
- Renders `PageSection` with `TransactionList` (or `EmptyItemPlaceholder`)

### `app/Http/Controllers/AccountController@show`
```php
$account->load([
    'provider',
    'transactions' => fn ($t) => $t->with(['category'])->take(10),
]);
return Inertia::render('accounts/show', ['account' => $account]);
```

### Available Account Model Fields (relevant to Direction 1)
| Field | Type | Use |
|-------|------|-----|
| `name` | string | Account display name |
| `type` | AccountType enum | Debit, Credit Card, etc. |
| `access_type` | AccountAccessType | Personal / Joint |
| `current_balance` | float | Hero balance |
| `initial_balance` | float | Fallback if no current |
| `currency` | string \| null | Defaults to 'IDR' |
| `credit_card_limit` | float \| null | For credit cards |
| `account_number` | string \| null | Masked display in badge row |
| `decorations` | DecorationData \| null | Icon + color for card |
| `created_at` | string \| null | "Since" date in badge row |
| `provider` | Provider \| null (relation) | Provider name |
| `transactions` | Transaction[] (relation) | Up to 10 with category |

---

## Implementation Steps

### Step 1: Rewrite `show.svelte` — Full Page Layout

Replace the current minimal page with the full Direction 1 layout. The page will be self-contained in `show.svelte` (no new components created) with clearly separated sections using Svelte 5 runes.

**Structure:**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';
    import AccountType from '@wayfinder/App/Enums/AccountType';
    
    import Formatter from '@utilities/formatter';
    import DateTimeHelper from '@utilities/date-time-helper';
    import { getDecorationColor } from '@data/decoration-colors';
    import { getDecorationIcon } from '@data/decoration-icons';
    import { cn } from '@utilities/shadcn';
    
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';
    import AccountAccessTypeBadge from '@components/module/account/account-access-type-badge.svelte';
    import AccountTypeBadge from '@components/module/account/account-type-badge.svelte';
    import TransactionList from '@components/module/transaction/transaction-list.svelte';
    import DashboardPageHeader from '@components/navigation/dashboard-page-header.svelte';
    
    let { account }: { account: App.Models.Account } = $props();
    
    // ── Derived state ──────────────────────────────────
    const colorSlug = $derived(account.decorations?.color);
    const iconSlug = $derived(account.decorations?.icon);
    const colorObj = $derived(colorSlug ? getDecorationColor(colorSlug) : undefined);
    const iconObj = $derived(iconSlug ? getDecorationIcon(iconSlug) : undefined);
    const bgColor = $derived(colorObj?.value ?? 'oklch(0.45 0.08 160)');
    const accentText = $derived(colorObj?.text_color ?? '#FFFFFF');
    
    let balanceHidden = $state(false);
    function toggleBalance(): void { balanceHidden = !balanceHidden; }
    
    // ── Category spending from transactions ────────────
    const categorySpending = $derived.by(() => { ... });
    // ── Income / expense totals ────────────────────────
    const incomeTotal = $derived(...);
    const expenseTotal = $derived(...);
</script>

<DashboardPageHeader title=""> ... </DashboardPageHeader>

<!-- Breadcrumb — desktop only -->
<nav class="hidden md:flex ...">Home / Accounts / {account.name}</nav>

<!-- Hero Card -->
<div class="..."> ... </div>

<!-- Spending by Category -->
<section class="..."> ... </section>

<!-- Recent Transactions -->
<section class="..."> ... </section>

<!-- Desktop-only Stat Cards -->
<section class="hidden md:grid ..."> ... </section>
```

### Step 2: Breadcrumb (Desktop Only)

```html
<nav class="hidden md:flex items-center gap-1.5 text-[11px] text-base-content/60 mb-4">
    <a href="/" class="hover:text-base-content transition-colors">Home</a>
    <span>/</span>
    <a href="{AccountController.index.url()}" class="hover:text-base-content transition-colors">Accounts</a>
    <span>/</span>
    <span class="font-medium text-base-content">{account.name}</span>
</nav>
```

- **Hidden on mobile** (`hidden md:flex`)
- Uses `AccountController.index.url()` for proper route generation via Wayfinder
- Links to Home (dashboard) and Accounts list

### Step 3: Hero Card (Decoration-Driven Color)

A full-width card with dynamic background color from `account.decorations.color`, white text. Uses the same pattern as the existing `account-detail.svelte` component.

**Derived state:**
```typescript
const colorSlug = $derived(account.decorations?.color);
const iconSlug = $derived(account.decorations?.icon);
const colorObj = $derived(colorSlug ? getDecorationColor(colorSlug) : undefined);
const bgColor = $derived(colorObj?.value ?? 'oklch(0.45 0.08 160)');  // fallback green
const accentText = $derived(colorObj?.text_color ?? '#FFFFFF');
const iconName = $derived(iconSlug ? getDecorationIcon(iconSlug)?.value : undefined);
```

**Background:** Uses `getDecorationColor()` to resolve the account's decoration color into an OKLCH value, applied as an inline `style:background`. The mockup uses `linear-gradient(135deg, ...)` — we apply the solid color as the base (can add gradient enhancement later).

**3a. Account header row:**
- Provider icon in a frosted glass container (uses `iconName` from decoration, falls back to bank icon per `account.type`)
- Account name + type badge (e.g., "Debit")
- Provider + access type secondary line (e.g., "Chime • Personal")
- Kebab menu button (`ph--dots-three-bold`)

**3b. Balance section:**
- "Current Balance" label with eye toggle button (toggles visibility)
- Balance amount in large bold text (uses `Formatter.currency()`)
- Monthly trend indicator: `+Rp 2.340.000 this month` (up arrow icon)
- Eye toggle uses `$state()` for balance visibility

**3c. Quick actions — horizontal scroll on mobile, static row on desktop:**
- Horizontal scroll container (`overflow-x-auto scrollbar-none`)
- Buttons: Transfer, Top Up, Withdraw, Report
- Context-aware actions based on `account.type` (reuse logic patterns from `account-detail.svelte`)
- Mobile: scroll-snap horizontal, Desktop: `md:flex-wrap`

**3d. Detail badges row (below hero background, white background):**
- Masked account number (`•••• •••• •••• 4829`)
- "Since {month Year}" from `account.created_at`
- Edit button (links to `AccountController.edit`)

### Step 4: Spending by Category Section

**Header:** "Spending by Category" | "June 2026"

**Layout:**
- Mobile: stacked vertically (donut centered, category list below)
- Desktop: side-by-side (`md:flex-row`)
- Uses existing `DonutChart` component from `@components/ui/charts/donut-chart.svelte`

**Data source:** Compute category breakdown from `account.transactions` client-side.

```typescript
// Derived: group transactions by category
type CategorySpending = { name: string; value: number; color: string; percentage: number; amount: number };
const categorySpending = $derived.by(() => {
    const groups = new Map<string, { name: string; amount: number; color: string }>();
    // Aggregate account.transactions by category name
    // Map to DonutSlice[] and list items
});
```

Since only 10 transactions are loaded, this gives a "recent spending" snapshot. For production accuracy, backend aggregation would be needed — noted as future enhancement.

**DonutChart props:**
```svelte
<DonutChart
    data={categorySpendingSlices}
    centerText={formatCenterText(totalSpent)}
    centerSubtext="Total spent"
    innerRadius={0.6}
/>
```

**Category list** — each row:
- Colored dot matching donut segment
- Category name
- Amount (formatted currency)
- Percentage (right-aligned)

**Design note from Direction 1:** The donut uses CSS conic-gradient in the mockup. We'll use the existing `layerchart` `DonutChart` component instead for consistency with the rest of the app.

### Step 5: Recent Transactions Section

**Header:** "Recent Transactions" | "View All" link -> links to transactions index filtered by account

**Content:** Use existing `TransactionList` component with `withoutAccount` prop:
```svelde
<TransactionList withoutAccount transactions={account.transactions} />
```

The component already handles:
- Transaction rows with colored icons
- Date/description/amount display
- Income (green) vs expense (red) coloring
- Load more (if applicable)

### Step 6: Desktop-only Stat Cards

**Section wrapper:** `hidden md:grid grid-cols-3 gap-4`

Three stat cards:

| Card | Icon | Label | Value Source |
|------|------|-------|-------------|
| Income | `ph--trend-up-bold` (success color) | Income | Computed from transactions |
| Expenses | `ph--trend-down-bold` (error color) | Expenses | Computed from transactions |
| Savings/Negative | `ph--piggy-bank-bold` (primary color) | Savings | Income - Expenses |

Each card contains:
- Icon in tinted background container
- Label (uppercase, small)
- Value (formatted currency, bold)
- Trend indicator (optional, from mockup: "12% vs last month")

**Data computation:**
```typescript
const incomeTotal = $derived(
    account.transactions
        .filter(t => t.type === 'income' || t.type === 'transfer_in')
        .reduce((sum, t) => sum + Number(t.amount), 0)
);
const expenseTotal = $derived(
    account.transactions
        .filter(t => t.type !== 'income' && t.type !== 'transfer_in')
        .reduce((sum, t) => sum + Number(t.amount), 0)
);
```

Trend percentages are placeholder for now (real data would need previous month aggregation server-side).

### Step 7: Apply Code Quality Standards

After implementation:
- Run linter/type check on the Svelte file
- Verify Svelte 5 runes syntax correctness
- Test that the page renders with real account data

---

## Architecture Decisions

### 1. Self-contained page vs. component extraction

**Decision:** Keep all Direction 1 layout in `show.svelte` directly.

**Rationale:** The sections are page-specific and unlikely to be reused elsewhere. If the hero card pattern is needed on other pages, extract to `AccountHeroCard.svelte` later.

### 2. Data computation: client-side vs. server-side

**Decision:** Compute category spending and monthly aggregates client-side from `account.transactions`.

**Trade-off:** Only 10 transactions are loaded. Category breakdown and stat card values will be a "recent snapshot" rather than full-month accurate. For production:
- Category spending: Works well for recent spending pattern
- Monthly stats (income/expenses): Will be partial since only 10 transactions

**Future enhancement:** Add a dedicated API endpoint or expand controller to pass `spendingByCategory` and `monthlyStats` computed server-side (see existing `AccountService` patterns).

### 3. Breadcrumb placement

**Decision:** Desktop-only, inline in the page (not in `DashboardPageHeader`).

**Rationale:** `DashboardPageHeader` title area handles the page title; breadcrumb is navigation context. On mobile it's hidden to maximize vertical space for the hero card.

### 4. Donut chart library

**Decision:** Use existing `DonutChart` (layerchart) instead of CSS conic-gradient.

**Rationale:** Already in the codebase with shadcn chart styling. Provides interactivity (click-to-select arcs) and consistent look with the rest of the app.

---

## Excluded Items (Not in Direction 1)

The following features from the existing `account-detail.svelte` are NOT part of Direction 1:
- Sparkline chart (Direction 1 hero doesn't include it)
- Monthly inflow/outflow ratio bar
- Credit card usage progress/due date/min payment
- Members section
- Detailed info fields (ID, timestamps)
- Balance/number reveal toggles (Direction 1 only has eye toggle for balance)

These can coexist below the card stack or in a separate "details" section if needed later.

---

## Dependencies

None beyond existing project dependencies:
- `layerchart` (already installed — used by DonutChart)
- `@inertiajs/svelte` (already installed)
- Iconify `ph` icon set (already configured in app.css)
- `@utilities/formatter`, `@utilities/date-time-helper` (already exist)

---

## File Changes Summary

| File | Action | Description |
|------|--------|-------------|
| `resources/js/pages/accounts/show.svelte` | **Rewrite** | Full Direction 1 layout |
| `docs/implementation-plan-account-detail.md` | **Create** | This plan |

No new files, no backend changes, no dependency changes.

---

## Verification

1. Run `php artisan wayfinder:generate` if routes change
2. Run `pnpm run build` to verify no TypeScript/build errors
3. Navigate to `/accounts/{id}` and visually verify:
   - Mobile viewport (360px): breadcrumb hidden, hero card full-width, quick actions scrollable, donut stacked above list, no stat cards
   - Desktop viewport (1024px): breadcrumb visible, hero card full-width, donut + list side-by-side, stat cards in 3-column grid
   - Balance eye toggle works
   - Category spending computes correctly from transactions
4. Run `php artisan test --compact --filter=Account` to ensure no backend regressions
