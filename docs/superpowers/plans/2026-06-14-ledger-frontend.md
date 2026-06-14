# Ledger — Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. Activate `inertia-svelte-development` skill when writing any Inertia/Svelte page or component.

**Goal:** Build all Ledger Svelte pages and module components — transaction ledger feed, create/edit transaction forms, budget list with status bars, and all supporting enum badge and form components.

**Architecture:** Svelte 5 runes throughout. Wayfinder (`next`) for all route calls and enum constants — no magic strings. DataComposer schemas drive form fields and display. Module components in `components/module/transaction/` and `components/module/budget/` keep pages thin. FormGenerator + DataComposer for all forms. `app.ts` layout function updated to serve `AppLayout` for `transactions` and `budgets` pages (reusing the existing layout — no new layout needed).

**Tech Stack:** Svelte 5, TypeScript, Inertia.js v3, Tailwind v4, DaisyUI, Wayfinder (next), DataComposer

**Depends on:** Foundation spec fully implemented. Ledger backend plan (`2026-06-14-ledger-backend.md`) fully implemented.

---

## Component Reference (existing — always prefer these)

| Component | Import | Key Props |
|-----------|--------|-----------|
| `Badge` | `@components/ui/badge.svelte` | `color` (primary/secondary/accent/success/info/warning/error/light/dark), `variant` (solid/outline/soft) |
| `Button` | `@components/ui/button.svelte` | `color`, `variant`, `href`, `disabled`, `onclick` |
| `Card` | `@components/ui/card.svelte` | `title`, `wrapperClass` |
| `FormGenerator` | `@components/ui/forms/form-generator.svelte` | `formSchema: {fields, data}`, `bind:form`, `action`, `method`, `withoutSubmit` |
| `FormAction` | `@components/ui/forms/form-action.svelte` | `form`, `formId`, `labelSubmit`, `labelCancel`, `withoutCancel`, `onCancel` |
| `ConfirmationModal` | `@components/ui/modals/confirmation-modal.svelte` | `bind:open`, `title`, `onConfirm`, `confirmText`, `cancelText`, `confirmButtonProps` |
| `DataList` | `@components/data/data-list.svelte` | `data: [{label, value}]` |
| `DataComposer` | `@utilities/data-composer` | `.from(schema)`, `.extendSchema()`, `.except()`, `.toFormGenerator()`, `.toDataDisplay()` |

---

## File Map

```
resources/js/schema/
  transaction.schema.ts
  budget.schema.ts

resources/js/components/module/
  transaction/
    transaction-type-badge.svelte
    transaction-form.svelte
  budget/
    budget-status-badge.svelte
    budget-form.svelte

resources/js/app.ts                         (modify: add transactions + budgets cases)

resources/js/pages/
  transactions/
    index.svelte
    create.svelte
    edit.svelte
  budgets/
    index.svelte
```

---

## Task 1: Generate Wayfinder Types

- [ ] **Run Wayfinder generation**

```bash
php artisan wayfinder:generate --no-interaction
```

Expected: `resources/js/wayfinder/` updated with `TransactionsController.ts`, `BudgetsController.ts`, and `App/Enums/TransactionType.ts`. Verify `App/Enums/TransactionType.ts` exports `Income`, `Expense`, `TransferOut`, `TransferIn`, `Fee` constants.

---

## Task 2: DataComposer Schemas

One schema file per model in `resources/js/schema/`. Dynamic options (categories list, accounts list) are not in the static schema — extended per-component via `DataComposer.extendSchema()`.

- [ ] **Create `resources/js/schema/transaction.schema.ts`**

`type` is excluded from the static schema because:
1. The form shows a "Transfer" option (a UI alias) but the DB stores `transfer_out`/`transfer_in`.
2. Transfer edit is blocked — `type` is read-only after creation.
3. Type options are injected in the form component where the alias logic lives.

```typescript
import type { DataSchema } from '@utilities/data-composer';
import type { App } from '@wayfinder/types';

export const transactionSchema: DataSchema<App.Models.Transaction> = {
    amount: {
        label: 'Amount',
        value: (data) =>
            Number(data.amount).toLocaleString('id-ID', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }),
        form: () => ({
            type: 'number',
            name: 'amount',
            required: true,
            inputProps: { inputmode: 'decimal', min: 0.01, step: 0.01, placeholder: '0.00' },
        }),
    },
    transaction_date: {
        label: 'Date',
        value: (data) =>
            data.transaction_date
                ? new Date(data.transaction_date as string).toLocaleDateString('id-ID', {
                      day: '2-digit',
                      month: 'short',
                      year: 'numeric',
                  })
                : '-',
        form: () => ({
            type: 'date',
            name: 'transaction_date',
            required: true,
            inputProps: { max: new Date().toISOString().split('T')[0] },
        }),
    },
    description: {
        label: 'Note',
        value: (data) => (data.description as string | null) ?? '-',
        form: () => ({
            type: 'text',
            name: 'description',
            inputProps: { placeholder: 'Optional note or memo', autocorrect: 'off' },
        }),
    },
    // category_id: dynamic options — extended in component
    // type: excluded — form component handles type selection + UI alias logic
};
```

- [ ] **Create `resources/js/schema/budget.schema.ts`**

`year` and `month` are period selectors — extended in the component with a generated range of options.

```typescript
import type { DataSchema } from '@utilities/data-composer';
import type { App } from '@wayfinder/types';

export const budgetSchema: DataSchema<App.Models.Budget> = {
    limit_amount: {
        label: 'Monthly Limit',
        value: (data) =>
            Number(data.limit_amount).toLocaleString('id-ID', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0,
            }),
        form: () => ({
            type: 'number',
            name: 'limit_amount',
            required: true,
            inputProps: { inputmode: 'decimal', min: 0.01, step: 1000, placeholder: '0' },
        }),
    },
    // category_id, year, month: dynamic options — extended in component
};
```

---

## Task 3: Enum + Status Badge Components

- [ ] **Create `resources/js/components/module/transaction/transaction-type-badge.svelte`**

Maps all five `TransactionType` values plus the display alias `transfer` (used when rendering transfer rows generically). The badge config uses Wayfinder constants as keys.

```svelte
<script lang="ts">
    import TransactionType from '@wayfinder/App/Enums/TransactionType';
    import type { App } from '@wayfinder/types';
    import type { ColorVariant } from '@/data/theme';
    import Badge from '@components/ui/badge.svelte';

    let { type }: { type: App.Enums.TransactionType } = $props();

    const config: Record<App.Enums.TransactionType, { label: string; color: ColorVariant }> = {
        [TransactionType.Income]:      { label: 'Income',      color: 'success'   },
        [TransactionType.Expense]:     { label: 'Expense',     color: 'error'     },
        [TransactionType.TransferOut]: { label: 'Transfer Out', color: 'warning'  },
        [TransactionType.TransferIn]:  { label: 'Transfer In',  color: 'info'     },
        [TransactionType.Fee]:         { label: 'Fee',          color: 'secondary' },
    };

    const badge = $derived(config[type]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
```

- [ ] **Create `resources/js/components/module/budget/budget-status-badge.svelte`**

`BudgetStatus` is computed — not a PHP enum. The badge maps the three string literals from `BudgetStatusData.status`. No Wayfinder import needed (it's a computed string, not a backend enum).

```svelte
<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import Badge from '@components/ui/badge.svelte';

    type BudgetStatus = 'on_track' | 'at_risk' | 'over_budget';

    let { status }: { status: BudgetStatus } = $props();

    const config: Record<BudgetStatus, { label: string; color: ColorVariant }> = {
        on_track:    { label: 'On Track',    color: 'success' },
        at_risk:     { label: 'At Risk',     color: 'warning' },
        over_budget: { label: 'Over Budget', color: 'error'   },
    };

    const badge = $derived(config[status]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
```

---

## Task 4: Module Form Components

Extract all form logic into module components. Pages become thin — they import these and pass props.

- [ ] **Create `resources/js/components/module/transaction/transaction-form.svelte`**

Handles both create and edit modes. Key logic:
- `type` options include `transfer` (UI alias) + `income`, `expense`, `fee`
- `destination_account_id` field is conditionally shown when `type === 'transfer'`
- `fee_amount` field is conditionally shown when `type === 'transfer'`
- Edit mode hides `type`, `destination_account_id`, and `fee_amount` (type is immutable after creation)
- Category field uses dynamic options from page props

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';
    import type { InertiaForm } from '@inertiajs/svelte';
    import { TransactionsController } from '@wayfinder/App/Http/Controllers/TransactionsController';
    import TransactionType from '@wayfinder/App/Enums/TransactionType';
    import { DataComposer } from '@utilities/data-composer';
    import { transactionSchema } from '@schema/transaction.schema';
    import Card from '@components/ui/card.svelte';
    import FormGenerator from '@components/ui/forms/form-generator.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';

    interface Props {
        account: App.Models.Account;
        categories: App.Models.Category[];
        accounts?: App.Models.Account[];  // other accounts for transfer destination
        transaction?: App.Models.Transaction;
        onCancel?: () => void;
    }

    let { account, categories, accounts = [], transaction, onCancel }: Props = $props();

    let form: InertiaForm<any> = $state(null!);

    const isEdit = $derived(!!transaction);

    // Flatten categories (parent + children) for select options
    const categoryOptions = $derived(() => {
        const opts: { value: string | number; label: string }[] = [{ value: '', label: '— Uncategorized —' }];

        for (const parent of categories) {
            if (parent.children && parent.children.length > 0) {
                for (const child of parent.children) {
                    opts.push({ value: child.id, label: `${parent.name} › ${child.name}` });
                }
            } else {
                opts.push({ value: parent.id, label: parent.name });
            }
        }

        return opts;
    });

    const accountOptions = $derived(() =>
        accounts.map(a => ({ value: a.id, label: a.name }))
    );

    // Type options for create form — 'transfer' is a UI alias, never sent as-is to DB
    const typeOptions = [
        { value: 'income',   label: 'Income'   },
        { value: 'expense',  label: 'Expense'  },
        { value: 'transfer', label: 'Transfer' },
        { value: TransactionType.Fee, label: 'Fee' },
    ];

    const formSchema = $derived(() => {
        if (isEdit && transaction) {
            // Edit: amount, transaction_date, category_id, description only
            const { fields, data } = DataComposer.from(transactionSchema)
                .extendSchema({
                    category_id: {
                        label: 'Category',
                        form: () => ({
                            type: 'select',
                            name: 'category_id',
                            options: categoryOptions(),
                        }),
                    },
                })
                .toFormGenerator({
                    amount: Number(transaction.amount),
                    transaction_date: transaction.transaction_date as string,
                    category_id: transaction.category_id ?? '',
                    description: transaction.description ?? '',
                });

            return { fields, data };
        }

        // Create: full form with type + conditional transfer fields
        const { fields, data } = DataComposer.from(transactionSchema)
            .extendSchema({
                type: {
                    label: 'Type',
                    form: () => ({
                        type: 'select',
                        name: 'type',
                        required: true,
                        options: typeOptions,
                    }),
                },
                category_id: {
                    label: 'Category',
                    form: () => ({
                        type: 'select',
                        name: 'category_id',
                        options: categoryOptions(),
                        show: (f: any) => f.type !== 'transfer',
                    }),
                },
                destination_account_id: {
                    label: 'Destination Account',
                    form: () => ({
                        type: 'select',
                        name: 'destination_account_id',
                        options: accountOptions(),
                        show: (f: any) => f.type === 'transfer',
                        required: false, // required rule in StoreTransactionRequest handles it server-side
                    }),
                },
                fee_amount: {
                    label: 'Transfer Fee (optional)',
                    form: () => ({
                        type: 'number',
                        name: 'fee_amount',
                        show: (f: any) => f.type === 'transfer',
                        inputProps: { inputmode: 'decimal', min: 0.01, step: 0.01, placeholder: '0.00' },
                    }),
                },
            })
            .toFormGenerator({
                type: 'expense',
                amount: 0,
                transaction_date: new Date().toISOString().split('T')[0],
                category_id: '',
                destination_account_id: '',
                fee_amount: null,
                description: '',
            });

        return { fields, data };
    });

    const action = $derived(
        isEdit && transaction
            ? TransactionsController.update.url({ account: account.id, transaction: transaction.id })
            : TransactionsController.store.url({ account: account.id })
    );

    const method = $derived<'put' | undefined>(isEdit ? 'put' : undefined);
    const submitLabel = $derived(isEdit ? 'Save Changes' : 'Add Transaction');
</script>

<Card>
    <FormGenerator
        id="transaction-form"
        bind:form
        formSchema={formSchema()}
        {action}
        {method}
        withoutSubmit
    />
</Card>

<div class="mt-4">
    <FormAction
        {form}
        formId="transaction-form"
        labelSubmit={submitLabel}
        labelCancel="Cancel"
        onCancel={onCancel ?? (() => window.history.back())}
    />
</div>
```

- [ ] **Create `resources/js/components/module/budget/budget-form.svelte`**

Used in `budgets/index.svelte` as an inline add/edit form. `year` and `month` options are generated dynamically from a rolling 12-month window.

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';
    import type { InertiaForm } from '@inertiajs/svelte';
    import { BudgetsController } from '@wayfinder/App/Http/Controllers/BudgetsController';
    import { DataComposer } from '@utilities/data-composer';
    import { budgetSchema } from '@schema/budget.schema';
    import Card from '@components/ui/card.svelte';
    import FormGenerator from '@components/ui/forms/form-generator.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import Button from '@components/ui/button.svelte';

    interface Props {
        account: App.Models.Account;
        categories: App.Models.Category[];
        budget?: App.Models.Budget;
        defaultYear?: number;
        defaultMonth?: number;
        onSuccess?: () => void;
        onCancel?: () => void;
    }

    let {
        account,
        categories,
        budget,
        defaultYear = new Date().getFullYear(),
        defaultMonth = new Date().getMonth() + 1,
        onSuccess,
        onCancel,
    }: Props = $props();

    let form: InertiaForm<any> = $state(null!);

    const isEdit = $derived(!!budget);

    // Flatten category hierarchy for select options
    const categoryOptions = $derived(() => {
        const opts: { value: string | number; label: string }[] = [];

        for (const parent of categories) {
            if (parent.children && parent.children.length > 0) {
                for (const child of parent.children) {
                    opts.push({ value: child.id, label: `${parent.name} › ${child.name}` });
                }
            } else {
                opts.push({ value: parent.id, label: parent.name });
            }
        }

        return opts;
    });

    // Generate 12 month options centred around today
    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    function generateYearOptions(): { value: number; label: string }[] {
        const currentYear = new Date().getFullYear();

        return [currentYear - 1, currentYear, currentYear + 1].map(y => ({
            value: y,
            label: String(y),
        }));
    }

    function generateMonthOptions(): { value: number; label: string }[] {
        return monthNames.map((name, idx) => ({ value: idx + 1, label: name }));
    }

    const formSchema = $derived(() => {
        if (isEdit && budget) {
            // Edit: limit_amount only
            return DataComposer.from(budgetSchema).toFormGenerator({
                limit_amount: Number(budget.limit_amount),
            });
        }

        // Create: full form
        return DataComposer.from(budgetSchema)
            .extendSchema({
                category_id: {
                    label: 'Category',
                    form: () => ({
                        type: 'select',
                        name: 'category_id',
                        required: true,
                        options: categoryOptions(),
                    }),
                },
                year: {
                    label: 'Year',
                    form: () => ({
                        type: 'select',
                        name: 'year',
                        required: true,
                        options: generateYearOptions(),
                    }),
                },
                month: {
                    label: 'Month',
                    form: () => ({
                        type: 'select',
                        name: 'month',
                        required: true,
                        options: generateMonthOptions(),
                    }),
                },
            })
            .toFormGenerator({
                category_id: '',
                limit_amount: 0,
                year: defaultYear,
                month: defaultMonth,
            });
    });

    const action = $derived(
        isEdit && budget
            ? BudgetsController.update.url({ account: account.id, budget: budget.id })
            : BudgetsController.store.url({ account: account.id })
    );

    const method = $derived<'put' | undefined>(isEdit ? 'put' : undefined);
    const submitLabel = $derived(isEdit ? 'Update Limit' : 'Set Budget');
</script>

<Card wrapperClass="mb-4">
    <FormGenerator
        id="budget-form"
        bind:form
        formSchema={formSchema()}
        {action}
        {method}
        withoutSubmit
        submitOptions={{ onSuccess: () => { form?.reset?.(); onSuccess?.(); } }}
    />
    <div class="mt-4 flex gap-2">
        <FormAction
            {form}
            formId="budget-form"
            labelSubmit={submitLabel}
            withoutCancel={!onCancel}
            class="flex-1"
        />
        {#if onCancel}
            <Button color="light" variant="outline" onclick={onCancel}>Cancel</Button>
        {/if}
    </div>
</Card>
```

---

## Task 5: Update `app.ts` Layout

- [ ] **Update `resources/js/app.ts` — add `transactions` and `budgets` cases**

The ledger pages reuse the existing `AppLayout` (mobile bottom-nav shell). Only the `layout()` function needs two new `case` entries — no layout file changes.

Inside the existing `layout()` function, add alongside the `accounts` and `categories` cases:

```typescript
case name.startsWith('transactions'):
case name.startsWith('budgets'):
    return AppLayout;
```

The full switch after the change:

```typescript
case name.startsWith('accounts'):
case name.startsWith('transactions'):
case name.startsWith('budgets'):
case name.startsWith('categories'):
case name.startsWith('household'):
case name.startsWith('settings/theme'):
    return AppLayout;
```

---

## Task 6: Transaction Pages

- [ ] **Create `resources/js/pages/transactions/index.svelte`**

Ledger feed for a single account. Shows paginated transactions with `TransactionTypeBadge`, current balance, and links to create/edit. Uses Inertia's `<Link>` for pagination.

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';
    import { TransactionsController } from '@wayfinder/App/Http/Controllers/TransactionsController';
    import { AccountsController } from '@wayfinder/App/Http/Controllers/AccountsController';
    import { Link } from '@inertiajs/svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import TransactionTypeBadge from '@components/module/transaction/transaction-type-badge.svelte';

    interface PaginatedTransactions {
        data: App.Models.Transaction[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
    }

    let {
        account,
        transactions,
        balance,
    }: {
        account: App.Models.Account;
        transactions: PaginatedTransactions;
        balance: string;
    } = $props();

    const formattedBalance = $derived(
        Number(balance).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    );
</script>

<div class="p-4">
    <!-- Header -->
    <div class="mb-4 flex items-center gap-3">
        <Button
            color="light"
            variant="ghost"
            href={AccountsController.show.url({ account: account.id })}
            class="btn-circle btn-sm"
        >
            <i class="iconify size-5 ph--arrow-left-bold"></i>
        </Button>
        <div class="flex-1">
            <h1 class="text-xl font-bold">{account.name}</h1>
            <p class="text-xs text-base-content/50">Transactions</p>
        </div>
        <Button
            color="primary"
            size="sm"
            href={TransactionsController.create.url({ account: account.id })}
        >
            <i class="iconify size-4 ph--plus-bold"></i>
            Add
        </Button>
    </div>

    <!-- Balance card -->
    <Card wrapperClass="mb-4 bg-primary text-primary-content">
        <p class="text-xs opacity-70">Current Balance</p>
        <p class="font-mono text-2xl font-bold">{account.currency} {formattedBalance}</p>
    </Card>

    <!-- Transaction list -->
    {#if transactions.data.length === 0}
        <div class="flex flex-col items-center justify-center py-16 text-base-content/40">
            <i class="iconify mb-3 size-12 ph--receipt-bold"></i>
            <p class="text-sm">No transactions yet</p>
            <Button
                color="primary"
                size="sm"
                href={TransactionsController.create.url({ account: account.id })}
                class="mt-4"
            >
                Add your first transaction
            </Button>
        </div>
    {:else}
        <div class="space-y-2">
            {#each transactions.data as transaction (transaction.id)}
                <a
                    href={TransactionsController.edit.url({ account: account.id, transaction: transaction.id })}
                    class="block"
                >
                    <Card wrapperClass="transition-transform active:scale-95">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="truncate text-sm font-medium">
                                        {transaction.description ?? (transaction.category?.name ?? 'Transaction')}
                                    </p>
                                    <div class="mt-1 flex items-center gap-1">
                                        <TransactionTypeBadge type={transaction.type} />
                                        {#if transaction.category}
                                            <span class="text-xs text-base-content/50">{transaction.category.name}</span>
                                        {/if}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-mono text-sm font-semibold {['income', 'transfer_in'].includes(transaction.type) ? 'text-success' : 'text-error'}">
                                    {['income', 'transfer_in'].includes(transaction.type) ? '+' : '-'}{Number(transaction.amount).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
                                </p>
                                <p class="text-xs text-base-content/40">
                                    {new Date(transaction.transaction_date as string).toLocaleDateString('id-ID', { day: '2-digit', month: 'short' })}
                                </p>
                            </div>
                        </div>
                    </Card>
                </a>
            {/each}
        </div>

        <!-- Pagination -->
        {#if transactions.last_page > 1}
            <div class="mt-6 flex items-center justify-center gap-1 flex-wrap">
                {#each transactions.links as link (link.label)}
                    {#if link.url}
                        <Link
                            href={link.url}
                            class="btn btn-xs {link.active ? 'btn-primary' : 'btn-ghost'}"
                            preserveScroll
                        >
                            {@html link.label}
                        </Link>
                    {:else}
                        <span class="btn btn-xs btn-disabled">{@html link.label}</span>
                    {/if}
                {/each}
            </div>
        {/if}
    {/if}
</div>
```

- [ ] **Create `resources/js/pages/transactions/create.svelte`**

Delegates entirely to `TransactionForm`. Thin page component.

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';
    import { TransactionsController } from '@wayfinder/App/Http/Controllers/TransactionsController';
    import { router } from '@inertiajs/svelte';
    import Button from '@components/ui/button.svelte';
    import TransactionForm from '@components/module/transaction/transaction-form.svelte';

    let {
        account,
        categories,
        accounts,
    }: {
        account: App.Models.Account;
        categories: App.Models.Category[];
        accounts: App.Models.Account[];
    } = $props();
</script>

<div class="p-4">
    <div class="mb-4 flex items-center gap-3">
        <Button
            color="light"
            variant="ghost"
            href={TransactionsController.index.url({ account: account.id })}
            class="btn-circle btn-sm"
        >
            <i class="iconify size-5 ph--arrow-left-bold"></i>
        </Button>
        <h1 class="text-xl font-bold">New Transaction</h1>
    </div>

    <TransactionForm
        {account}
        {categories}
        {accounts}
        onCancel={() => router.visit(TransactionsController.index.url({ account: account.id }))}
    />
</div>
```

- [ ] **Create `resources/js/pages/transactions/edit.svelte`**

Delegates form to `TransactionForm`. Keeps delete action + confirmation modal in the page.

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';
    import { TransactionsController } from '@wayfinder/App/Http/Controllers/TransactionsController';
    import { router } from '@inertiajs/svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import TransactionForm from '@components/module/transaction/transaction-form.svelte';
    import TransactionTypeBadge from '@components/module/transaction/transaction-type-badge.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    let {
        account,
        transaction,
        categories,
    }: {
        account: App.Models.Account;
        transaction: App.Models.Transaction;
        categories: App.Models.Category[];
    } = $props();

    let showDeleteConfirm = $state(false);

    function destroy(): void {
        router.delete(
            TransactionsController.destroy.url({ account: account.id, transaction: transaction.id })
        );
    }

    // Transfer rows cannot have their type changed — show a read-only badge instead of the type select
    const isTransferRow = $derived(
        transaction.type === 'transfer_out' ||
        transaction.type === 'transfer_in' ||
        transaction.type === 'fee'
    );
</script>

<div class="p-4">
    <div class="mb-4 flex items-center gap-3">
        <Button
            color="light"
            variant="ghost"
            href={TransactionsController.index.url({ account: account.id })}
            class="btn-circle btn-sm"
        >
            <i class="iconify size-5 ph--arrow-left-bold"></i>
        </Button>
        <div class="flex-1">
            <h1 class="text-xl font-bold">Edit Transaction</h1>
            <div class="mt-1">
                <TransactionTypeBadge type={transaction.type} />
                {#if isTransferRow}
                    <span class="ml-1 text-xs text-base-content/50">Transfer — type cannot be changed</span>
                {/if}
            </div>
        </div>
    </div>

    <TransactionForm
        {account}
        {categories}
        {transaction}
        onCancel={() => router.visit(TransactionsController.index.url({ account: account.id }))}
    />

    <div class="mt-4">
        <Button
            color="error"
            variant="outline"
            class="w-full"
            onclick={() => (showDeleteConfirm = true)}
        >
            <i class="iconify size-4 ph--trash-bold"></i>
            {isTransferRow ? 'Delete Transfer (all linked rows)' : 'Delete Transaction'}
        </Button>
    </div>
</div>

<ConfirmationModal
    bind:open={showDeleteConfirm}
    title="Delete Transaction"
    confirmText="Delete"
    cancelText="Cancel"
    onConfirm={destroy}
    confirmButtonProps={{ color: 'error' }}
>
    {#if isTransferRow}
        This is part of a transfer. Deleting it will soft-delete all linked transfer rows.
    {:else}
        This transaction will be soft-deleted and cannot be recovered from the UI.
    {/if}
</ConfirmationModal>
```

---

## Task 7: Budget Page

- [ ] **Create `resources/js/pages/budgets/index.svelte`**

Shows monthly budgets for the account. Each budget card has:
- Category name + `BudgetStatusBadge`
- A progress bar (percentage from `BudgetStatusData`)
- Spend vs limit figures
- Inline edit via `BudgetForm` toggled per card
- Delete with `ConfirmationModal`

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';
    import type { BudgetStatusData } from '@/types/generated';
    import { BudgetsController } from '@wayfinder/App/Http/Controllers/BudgetsController';
    import { AccountsController } from '@wayfinder/App/Http/Controllers/AccountsController';
    import { router } from '@inertiajs/svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import BudgetStatusBadge from '@components/module/budget/budget-status-badge.svelte';
    import BudgetForm from '@components/module/budget/budget-form.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    interface BudgetWithStatus {
        budget: App.Models.Budget;
        status: BudgetStatusData;
    }

    let {
        account,
        budgets_with_status,
        year,
        month,
        categories,
    }: {
        account: App.Models.Account;
        budgets_with_status: BudgetWithStatus[];
        year: number;
        month: number;
        categories: App.Models.Category[];
    } = $props();

    let showAddForm = $state(false);
    let editingBudgetId = $state<number | null>(null);
    let deletingBudgetId = $state<number | null>(null);

    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    const currentMonthLabel = $derived(`${monthNames[month - 1]} ${year}`);

    function navigateMonth(delta: number): void {
        let newMonth = month + delta;
        let newYear = year;

        if (newMonth > 12) {
            newMonth = 1;
            newYear++;
        } else if (newMonth < 1) {
            newMonth = 12;
            newYear--;
        }

        router.visit(
            BudgetsController.index.url({ account: account.id, query: { year: newYear, month: newMonth } }),
            { preserveState: false }
        );
    }

    function destroyBudget(): void {
        if (!deletingBudgetId) { return; }

        router.delete(
            BudgetsController.destroy.url({ account: account.id, budget: deletingBudgetId }),
            { onFinish: () => (deletingBudgetId = null) }
        );
    }

    function progressColor(status: string): string {
        if (status === 'over_budget') { return 'progress-error'; }
        if (status === 'at_risk') { return 'progress-warning'; }

        return 'progress-success';
    }
</script>

<div class="p-4">
    <!-- Header -->
    <div class="mb-4 flex items-center gap-3">
        <Button
            color="light"
            variant="ghost"
            href={AccountsController.show.url({ account: account.id })}
            class="btn-circle btn-sm"
        >
            <i class="iconify size-5 ph--arrow-left-bold"></i>
        </Button>
        <div class="flex-1">
            <h1 class="text-xl font-bold">{account.name}</h1>
            <p class="text-xs text-base-content/50">Budgets</p>
        </div>
        <Button color="primary" size="sm" onclick={() => (showAddForm = !showAddForm)}>
            <i class="iconify size-4 ph--plus-bold"></i>
            Add
        </Button>
    </div>

    <!-- Month navigation -->
    <div class="mb-4 flex items-center justify-between rounded-xl bg-base-200 px-4 py-2">
        <Button color="light" variant="ghost" class="btn-circle btn-sm" onclick={() => navigateMonth(-1)}>
            <i class="iconify size-5 ph--caret-left-bold"></i>
        </Button>
        <p class="font-semibold">{currentMonthLabel}</p>
        <Button color="light" variant="ghost" class="btn-circle btn-sm" onclick={() => navigateMonth(1)}>
            <i class="iconify size-5 ph--caret-right-bold"></i>
        </Button>
    </div>

    <!-- Add budget form -->
    {#if showAddForm}
        <BudgetForm
            {account}
            {categories}
            defaultYear={year}
            defaultMonth={month}
            onSuccess={() => (showAddForm = false)}
            onCancel={() => (showAddForm = false)}
        />
    {/if}

    <!-- Budget list -->
    {#if budgets_with_status.length === 0}
        <div class="flex flex-col items-center justify-center py-16 text-base-content/40">
            <i class="iconify mb-3 size-12 ph--piggy-bank-bold"></i>
            <p class="text-sm">No budgets for this month</p>
            <Button color="primary" size="sm" class="mt-4" onclick={() => (showAddForm = true)}>
                Set your first budget
            </Button>
        </div>
    {:else}
        <div class="space-y-3">
            {#each budgets_with_status as { budget, status } (budget.id)}
                {#if editingBudgetId === budget.id}
                    <BudgetForm
                        {account}
                        {categories}
                        {budget}
                        defaultYear={year}
                        defaultMonth={month}
                        onSuccess={() => (editingBudgetId = null)}
                        onCancel={() => (editingBudgetId = null)}
                    />
                {:else}
                    <Card>
                        <!-- Top row: category name + status badge + actions -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="truncate text-sm font-semibold">
                                        {budget.category?.name ?? 'Category'}
                                    </p>
                                    <BudgetStatusBadge status={status.status} />
                                </div>

                                <!-- Progress bar -->
                                <div class="mt-2">
                                    <progress
                                        class="progress h-2 w-full {progressColor(status.status)}"
                                        value={Math.min(status.percentage, 100)}
                                        max="100"
                                    ></progress>
                                </div>

                                <!-- Spend / limit figures -->
                                <div class="mt-1 flex items-center justify-between">
                                    <p class="text-xs text-base-content/60">
                                        Spent: <span class="font-mono font-medium">
                                            {Number(status.spend).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
                                        </span>
                                    </p>
                                    <p class="text-xs text-base-content/60">
                                        Limit: <span class="font-mono font-medium">
                                            {Number(status.limit_amount).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
                                        </span>
                                    </p>
                                    <p class="text-xs font-semibold {status.percentage >= 100 ? 'text-error' : 'text-base-content/60'}">
                                        {status.percentage.toFixed(0)}%
                                    </p>
                                </div>
                            </div>

                            <!-- Edit / delete actions -->
                            <div class="flex shrink-0 flex-col gap-1">
                                <Button
                                    color="light"
                                    variant="ghost"
                                    class="btn-xs"
                                    onclick={() => (editingBudgetId = budget.id)}
                                >
                                    <i class="iconify size-4 ph--pencil-simple-bold"></i>
                                </Button>
                                <Button
                                    color="error"
                                    variant="ghost"
                                    class="btn-xs"
                                    onclick={() => (deletingBudgetId = budget.id)}
                                >
                                    <i class="iconify size-4 ph--trash-bold"></i>
                                </Button>
                            </div>
                        </div>
                    </Card>
                {/if}
            {/each}
        </div>
    {/if}
</div>

<ConfirmationModal
    bind:open={deletingBudgetId !== null}
    title="Delete Budget"
    confirmText="Delete"
    cancelText="Cancel"
    onConfirm={destroyBudget}
    onCancel={() => (deletingBudgetId = null)}
    confirmButtonProps={{ color: 'error' }}
>
    This budget will be soft-deleted. Existing transactions are unaffected.
</ConfirmationModal>
```

---

## Task 8: Frontend Formatting + Type Check

- [ ] **Run Prettier and ESLint fix**

```bash
pnpm run format:all
```

- [ ] **Run lint check**

```bash
pnpm run lint:all
```

Expected: No errors.

- [ ] **Run Svelte type check**

```bash
pnpm run sv:check
```

Expected: No type errors. If Wayfinder types are missing for `BudgetStatusData`, run `composer generate:ts` and then re-run `php artisan wayfinder:generate`.

---

## Task 9: Commit

- [ ] **Stage all new and modified frontend files**

```bash
git add resources/js/schema/transaction.schema.ts resources/js/schema/budget.schema.ts resources/js/components/module/transaction/ resources/js/components/module/budget/ resources/js/app.ts resources/js/pages/transactions/ resources/js/pages/budgets/ resources/js/wayfinder/
```

- [ ] **Commit**

```bash
git commit -m "$(cat <<'EOF'
feat(ledger): add transaction and budget pages, badges, and forms

Transaction ledger feed with paginated list, balance display, and type
badges. Create/edit transaction forms with conditional transfer fields
(destination account + fee). Budget list with month navigation, progress
bars, inline edit/delete, and BudgetStatusBadge. DataComposer schemas
drive all form fields. No magic strings — Wayfinder constants throughout.

Co-Authored-By: Claude Sonnet 4.6 (1M context) <noreply@anthropic.com>
EOF
)"
```
