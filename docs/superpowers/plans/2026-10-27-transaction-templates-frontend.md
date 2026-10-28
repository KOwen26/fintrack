# Transaction Templates — Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use better-executing-plans to implement this plan task-by-task — or better-parallel-subagents-executions if this plan runs as a slice. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The frontend for transaction templates: Settings management (list / detail / shared plain form), the settings menu entry, and the quick-access `template-select` chip row on the transaction create form.

**Architecture:** 4 module components in `components/module/template/`, 3 pages, 1 display schema. Chips are **pure navigation** to `create?template=id` — the server folds, the client implements no apply logic. **Dependency:** the backend plan (`2026-10-27-transaction-templates-backend.md`) must be complete first — its final step runs `php artisan wayfinder:generate`, and every task here imports that output. Spec: `docs/superpowers/specs/2026-10-27-transaction-templates-design.md`.

**Tech Stack:** Svelte 5 runes, Inertia v3, Tailwind/DaisyUI + shadcn-style atoms, Wayfinder imports.

**Workflow Mode:** `direct` — no JS test suite exists in this project, so no test steps; never run tooling or commit.

**Complexity:** Medium

## Global Constraints

- Spec is authoritative: `docs/superpowers/specs/2026-10-27-transaction-templates-design.md`. Frontend naming: `template-*` components in `resources/js/components/module/template/`, pages under `pages/app/settings/transaction-templates/`, schema at `resources/js/schema/template.schema.ts`.
- Svelte 5 runes only (`$props`, `$state`, `$derived`, `$effect`, `bind:`); kebab-case filenames; existing UI atoms over raw HTML.
- Inertia `useForm` initialization: a plain object is only for static defaults. When initial values reference other values (e.g. `template?.name`), use the lazy form `useForm(() => ({ … }))` — the `account-form.svelte` pattern.
- Never hardcode URLs — Wayfinder imports only (`@wayfinder/App/Http/Controllers/Settings/TransactionTemplateController`, `@wayfinder/routes/transaction-templates`, `@/wayfinder` types).
- The settings form is a **plain form**: no hero, no type tabs, standard `Select` inputs; `template-form.svelte` is the form part only and exposes `form` via `bind:form`; the page hosts the `Card` and the `FormAction` inside a `BottomActionBar`. Delete lives on the show page only.
- `template.schema.ts` is display-only — no `form()` factories.
- `template-select` chips never apply client-side: they visit `?template=` (+ current `account_id` as context).
- Post-execution: `template-select` was restructured into a **variant-based select** (the category-select pattern) — `inline` chip row / `combobox` (searchable; the create form's default, rendered as a "Templates" field gated to the active type) / `drawer` (trigger + bottom-sheet list). Offered templates filter by the `activeType` prop, account-matching first; picking navigates server-side and the picker holds no persistent selection.
- Serialized relation keys are **snake_case** (Laravel `$snakeAttributes`): read `destination_account` from props, never `destinationAccount` — this matches the generated Wayfinder types (compare `Transfer`'s `source_transaction?`).
- Post-execution corrections (verified against real siblings): the plain `Select` atom takes `options={…}` (not `items`), and category props were removed from the form chain — `CategorySelect` self-serves from the shared `static.categories` page prop (controller create/edit pass only `template` + `accounts`). Decoration icon/color default to random active values on create (`getRandomDecorationIcon().slug` / `getRandomDecorationColor().slug` from `@data/decorations`), preserved from the template on edit.

## File Structure

**Created:** `resources/js/schema/template.schema.ts`, `resources/js/components/module/template/{template-list,template-form,template-detail,template-select}.svelte`, `resources/js/pages/app/settings/transaction-templates/{index,show,form}.svelte`.

**Modified:** `resources/js/data/menu.ts`, `resources/js/pages/app/transaction/create.svelte`, `resources/js/components/module/transaction/transaction-form.svelte`, `README.md` (roadmap reword).

**Explicitly unchanged:** `app.ts` (layout switch defaults to `DashboardLayout` for every non-auth page — verified), `DashboardController`, dashboard page.

---
### Task 1: Settings management frontend — schema, menu, index page, template-list

**Complexity:** Medium

**Files:**
- Create: `resources/js/schema/template.schema.ts`
- Create: `resources/js/components/module/template/template-list.svelte`
- Create: `resources/js/pages/app/settings/transaction-templates/index.svelte`
- Modify: `resources/js/data/menu.ts`

**Interfaces:**
- Consumes: the backend plan's Wayfinder output (`2026-10-27-transaction-templates-backend.md` — its Task 4 regen must have run); existing `Formatter.currency`, `DecorationBadge`, `CurrencyAmount`, `Link`, `MobilePageLayout`.
- Produces: `templateSchema` + `templateSubtitle(template, categories)` + `labelForType(type)` exports (used by Task 3's detail view); settings menu entry "Transaction Templates".

No JS test suite exists in this project (quality tooling is Pest/Pint/Rector/ESLint/Prettier) — no test step for frontend tasks, matching house convention.

- [x] **Step 1: Write `resources/js/schema/template.schema.ts`**

```ts
import type { DataSchema } from '@lib/data-composer';
import type { App } from '@wayfinder/types';

import TransactionType from '@wayfinder/App/Enums/TransactionType';

import CurrencyAmount from '@components/data/currency-amount.svelte';
import Formatter from '@utilities/formatter';

export const templateTypeOptions = [
    { value: TransactionType.Income, label: 'Income' },
    { value: TransactionType.Expense, label: 'Expense' },
    { value: TransactionType.Transfer, label: 'Transfer' },
];

export const labelForType = (type: App.Enums.TransactionType): string =>
    templateTypeOptions.find((o) => o.value === type)?.label ?? type;

/** One-line summary for list rows: "Expense · Dining Out" or "Transfer → BCA Savings". */
export const templateSubtitle = (
    template: App.Models.TransactionTemplate,
    categories: App.Data.CategoryData[]
): string => {
    if (template.type === TransactionType.Transfer) {
        return `${labelForType(template.type)} → ${template.destination_account?.name ?? 'Pick destination'}`;
    }

    const category = categories.find((c) => c.id === template.category_id)?.name ?? template.category_id ?? '—';

    return `${labelForType(template.type)} · ${category}`;
};

export const templateSchema: DataSchema<App.Models.TransactionTemplate> = {
    name: { label: 'Name' },
    type: { label: 'Type', value: (data) => labelForType(data.type) },
    category_id: {
        label: 'Category',
        show: (data) => data.type !== TransactionType.Transfer,
        value: (data) => data.category_id ?? '—',
    },
    amount: { label: 'Amount', value: (data) => Formatter.currency(data.amount) },
    account_id: {
        label: 'Default Source Account',
        value: (data) => data.account?.name ?? '—',
    },
    destination_account_id: {
        label: 'Destination Account',
        show: (data) => data.type === TransactionType.Transfer,
        value: (data) => data.destination_account?.name ?? '—',
    },
    fee_amount: {
        label: 'Transfer Fee',
        show: (data) => data.type === TransactionType.Transfer,
        value: (data) => (data.fee_amount !== null ? Formatter.currency(data.fee_amount) : '—'),
    },
    description: { label: 'Note', value: (data) => data.description ?? '—' },
};
```

(If `CurrencyAmount` ends up unused in this file, drop that import — it is used by `template-list.svelte` below.)

- [x] **Step 2: Add the settings menu entry**

In `resources/js/data/menu.ts`, extend the `settingsMenu.menus` object (after `appearance`) and add the named-route import alongside the existing ones:

```ts
import transactionTemplates from '@wayfinder/routes/transaction-templates';
```

```ts
        transaction_templates: {
            name: 'Transaction Templates',
            url: transactionTemplates.index().url,
            route: 'transaction-templates.index',
            active: 'transaction-templates.*',
            icon: 'iconify solar--notes-line-duotone',
            type: 'menu',
        },
```

- [x] **Step 3: Write `resources/js/components/module/template/template-list.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import TransactionTemplateController from '@wayfinder/App/Http/Controllers/Settings/TransactionTemplateController';

    import CurrencyAmount from '@components/data/currency-amount.svelte';
    import EmptyItemPlaceholder from '@components/data/empty-item-placeholder.svelte';
    import DecorationBadge from '@components/module/decoration-badge.svelte';
    import { templateSubtitle } from '@schema/template.schema';

    import Link from '@components/ui/link.svelte';

    let {
        templates,
        categories,
    }: {
        templates: App.Models.TransactionTemplate[];
        categories: App.Data.CategoryData[];
    } = $props();
</script>

{#if templates.length === 0}
    <EmptyItemPlaceholder
        ctaLabel="Create your first template"
        ctaUrl={TransactionTemplateController.create.url()}
        icon="solar--notes-line-duotone"
        label="No templates yet" />
{:else}
<ul class="space-y-1">
    {#each templates as template (template.id)}
        <li>
            <Link
                class="flex items-center gap-3 rounded-xl px-3 py-3 transition-colors hover:bg-base-200 focus:bg-base-200"
                href={TransactionTemplateController.show.url({ template: template.id })}>
                <DecorationBadge decorations={template.decorations} size="sm" />

                <span class="min-w-0 grow">
                    <span class="block truncate text-sm font-semibold">{template.name}</span>
                    <span class="block truncate text-xs text-base-content/60">
                        {templateSubtitle(template, categories)}
                    </span>
                </span>

                <span class="shrink-0 text-sm font-medium">
                    <CurrencyAmount value={Number(template.amount)} />
                </span>

                <i class="iconify size-5 shrink-0 text-neutral solar--alt-arrow-right-linear" aria-hidden="true"
                ></i>
            </Link>
        </li>
    {/each}
</ul>
{/if}
```

- [x] **Step 4: Write `resources/js/pages/app/settings/transaction-templates/index.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { setLayoutProps } from '@inertiajs/svelte';
    import TransactionTemplateController from '@wayfinder/App/Http/Controllers/Settings/TransactionTemplateController';
    import settings from '@wayfinder/routes/settings';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import TemplateList from '@components/module/template/template-list.svelte';
    import Button from '@components/ui/button.svelte';

    let {
        templates,
        categories,
    }: {
        templates: App.Models.TransactionTemplate[];
        categories: App.Data.CategoryData[];
    } = $props();

    setLayoutProps({ title: 'Transaction Templates', backUrl: settings.index.url() });
</script>

<MobilePageLayout variant="4/5">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Templates</h2>

        <Button color="primary" href={TransactionTemplateController.create.url()} size="sm">
            <i class="iconify size-4 solar--add-bold-duotone"></i>
            Add
        </Button>
    </div>

    <div class="mt-4">
        <TemplateList {categories} {templates} />
    </div>
</MobilePageLayout>
```

---

### Task 2: Shared form page + template-form component

**Complexity:** Medium

**Files:**
- Create: `resources/js/components/module/template/template-form.svelte`
- Create: `resources/js/pages/app/settings/transaction-templates/form.svelte`

**Interfaces:**
- Consumes: component — `DecorationIconSelector` / `DecorationColorSelector` (`value` bindable slug string), the plain `Select` atom (verify its exact path and prop names against how `FormGenerator` renders `type: 'select'`), `CurrencyInput`, `Field`, `Form`, `Input`, `Textarea`, `Card` (`title`, `description`); page — `FormAction` (`form`, `formId`, `labelCancel`, `labelSubmit`, `onCancel`, `cancelClass`, `submitClass`), `BottomActionBar`.

    This is deliberately a **plain settings form**: no hero, no type tabs — standard inputs, conditional transfer fields via `{#if}`. The domain split is explicit in the layout: two titled Cards — **Template** (name, icon, color — the template's own identity) and **Transaction Defaults** (type, category or destination + fee, amount, default source account, note — the prefilled values). The component renders the `<Form>` including both section Cards and exposes its `useForm` instance via `bind:form`; the page hosts the layout and the `FormAction` inside a `BottomActionBar` (the account-form pattern). Delete lives on the show page only, per spec — the form has no delete. `template.schema.ts` stays display-only; no `form()` factories.

- Produces: the create/edit form used by both routes; `form.svelte` page props `{ template?: App.Models.TransactionTemplate | null; accounts: App.Models.Account[]; categories: App.Data.CategoryData[] }`.

- [x] **Step 1: Write `resources/js/components/module/template/template-form.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { useForm } from '@inertiajs/svelte';
    import TransactionType from '@wayfinder/App/Enums/TransactionType';
    import TransactionTemplateController from '@wayfinder/App/Http/Controllers/Settings/TransactionTemplateController';

    import DecorationColorSelector from '@components/module/decoration-color-selector.svelte';
    import DecorationIconSelector from '@components/module/decoration-icon-selector.svelte';
    import CurrencyInput from '@components/ui/forms/currency-input.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import Form from '@components/ui/forms/form.svelte';
    import Input from '@components/ui/forms/input.svelte';
    import Select from '@components/ui/forms/select.svelte';
    import Textarea from '@components/ui/forms/textarea.svelte';
    import Card from '@components/ui/card.svelte';

    interface Props {
        template?: App.Models.TransactionTemplate | null;
        accounts: App.Models.Account[];
        categories: App.Data.CategoryData[];
        /** Exposed to the page for its external FormAction — the `bind:form` pattern. */
        form?: ReturnType<typeof useForm>;
    }

    let { template = null, accounts, categories, form = $bindable() }: Props = $props();

    const isEdit = $derived(template !== null);

    const typeOptions = [
        { value: TransactionType.Income, label: 'Income' },
        { value: TransactionType.Expense, label: 'Expense' },
        { value: TransactionType.Transfer, label: 'Transfer' },
    ];

    form = useForm(() => ({
        name: template?.name ?? '',
        type: template?.type ?? TransactionType.Expense,
        category_id: template?.category_id ?? '',
        amount: template?.amount !== null && template?.amount !== undefined ? String(template.amount) : '',
        description: template?.description ?? '',
        account_id: template?.account_id !== null && template?.account_id !== undefined ? String(template.account_id) : '',
        destination_account_id:
            template?.destination_account_id !== null && template?.destination_account_id !== undefined
                ? String(template.destination_account_id)
                : '',
        fee_amount:
            template?.fee_amount !== null && template?.fee_amount !== undefined
                ? String(template.fee_amount)
                : '',
        decorations: {
            icon: template?.decorations?.icon ?? '',
            color: template?.decorations?.color ?? '',
        },
    }));

    const isTransfer = $derived(form.type === TransactionType.Transfer);
    const isIncome = $derived(form.type === TransactionType.Income);

    const action = $derived(
        isEdit && template
            ? TransactionTemplateController.update.form({ template: template.id })
            : TransactionTemplateController.store.form()
    );

    /* Plain selects with string values; the request's integer rules accept numeric strings. */
    const categoryOptions = $derived(
        categories
            .filter((category) => category.cashflow === (isIncome ? 'inflow' : 'outflow'))
            .map((category) => ({ value: category.id, label: category.name }))
    );

    const accountOptions = $derived(
        accounts.map((account) => ({ value: String(account.id), label: account.name }))
    );

    /* A type switch drops the type-specific values so nothing stale reaches the payload. */
    let lastType = form.type;

    $effect(() => {
        if (form.type !== lastType) {
            lastType = form.type;
            form.category_id = '';
            form.destination_account_id = '';
            form.fee_amount = '';
            form.clearErrors();
        }
    });

    /* Strip fields the request prohibits for the active type, and empty decorations. */
    form.transform((data) => {
        const payload: Record<string, unknown> = { ...data };

        if (data.type === TransactionType.Transfer) {
            delete payload.category_id;
        } else {
            delete payload.destination_account_id;
            delete payload.fee_amount;
        }

        if (!data.decorations?.icon && !data.decorations?.color) {
            delete payload.decorations;
        }

        return payload;
    });

</script>

<Form class="space-y-6" id="transaction-template-form" {...action} {form}>
        <Card description="How this template appears in your list." title="Template">
            <div class="space-y-4">
                <Field error={form.errors.name} required title="Name">
                    <Input placeholder="e.g. Netflix" bind:value={form.name} />
                </Field>

                <div class="grid grid-cols-2 gap-4">
                    <Field title="Icon">
                        <DecorationIconSelector rows={1} bind:value={form.decorations.icon} />
                    </Field>

                    <Field title="Color">
                        <DecorationColorSelector rows={1} bind:value={form.decorations.color} />
                    </Field>
                </div>
            </div>
        </Card>

        <Card
            description="Values prefilled when this template is applied."
            title="Transaction Defaults">
            <div class="space-y-4">
                <Field error={form.errors.type} required title="Type">
                    <Select items={typeOptions} bind:value={form.type} />
                </Field>

                {#if !isTransfer}
                    <Field error={form.errors.category_id} required title="Category">
                        <Select items={categoryOptions} bind:value={form.category_id} />
                    </Field>
                {:else}
                    <Field error={form.errors.destination_account_id} required title="Destination Account">
                        <Select items={accountOptions} bind:value={form.destination_account_id} />
                    </Field>

                    <Field error={form.errors.fee_amount} title="Transfer Fee (optional)">
                        <CurrencyInput inputmode="numeric" placeholder="0" bind:value={form.fee_amount} />
                    </Field>
                {/if}

                <Field error={form.errors.amount} required title="Amount">
                    <CurrencyInput inputmode="numeric" placeholder="0" bind:value={form.amount} />
                </Field>

                <Field error={form.errors.account_id} title="Default Source Account (optional)">
                    <Select
                        items={[{ value: '', label: 'No default account' }, ...accountOptions]}
                        bind:value={form.account_id} />
                </Field>

                <Field error={form.errors.description} title="Note">
                    <Textarea class="min-h-20" placeholder="Optional note or memo" bind:value={form.description} />
                </Field>
            </div>
        </Card>
    </Form>
```

If the `Select` atom's API differs (e.g. `items` vs `options`, or `onValueChange` instead of `bind:value`), follow exactly how `form-generator.svelte` renders its `type: 'select'` fields — do not invent props.

- [x] **Step 2: Write `resources/js/pages/app/settings/transaction-templates/form.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { setLayoutProps, useForm, router } from '@inertiajs/svelte';
    import TransactionTemplateController from '@wayfinder/App/Http/Controllers/Settings/TransactionTemplateController';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import TemplateForm from '@components/module/template/template-form.svelte';
    import BottomActionBar from '@components/navigation/bottom-action-bar.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';

    let {
        template = null,
        accounts,
        categories,
    }: {
        template?: App.Models.TransactionTemplate | null;
        accounts: App.Models.Account[];
        categories: App.Data.CategoryData[];
    } = $props();

    setLayoutProps({
        title: template ? 'Edit Template' : 'Add Template',
        backUrl: template
            ? TransactionTemplateController.show.url({ template: template.id })
            : TransactionTemplateController.index.url(),
    });

    const isEdit = $derived(template !== null);

    /* Created inside TemplateForm, bound up — the `bind:form` pattern. */
    let form: ReturnType<typeof useForm> | undefined = $state();

    function cancel(): void {
        router.visit(
            isEdit && template
                ? TransactionTemplateController.show.url({ template: template.id })
                : TransactionTemplateController.index.url()
        );
    }

    const submitLabel = $derived(isEdit ? 'Save Changes' : 'Create Template');

    const formActionProps = $derived(
        form && {
            cancelClass: 'flex-auto',
            submitClass: 'flex-3/5',
            form,
            formId: 'transaction-template-form',
            labelCancel: 'Cancel',
            labelSubmit: submitLabel,
            onCancel: cancel,
        }
    );
</script>

<!-- variant="full" is the plain settings chrome (profile/security pattern): no hero. -->
<MobilePageLayout variant="full">
    <TemplateForm {accounts} bind:form {categories} {template} />
</MobilePageLayout>

{#if formActionProps}
    <BottomActionBar>
        <FormAction class="w-full" {...formActionProps} />
    </BottomActionBar>
{/if}
```

---
### Task 3: Detail page + template-detail component

**Complexity:** Medium

**Files:**
- Create: `resources/js/components/module/template/template-detail.svelte`
- Create: `resources/js/pages/app/settings/transaction-templates/show.svelte`

**Interfaces:**
- Consumes: `templateSchema` (Task 1), `DataComposer.from(schema).toDataDisplay(data)`, `DataList` (`@components/data/data-list.svelte` - verify the exact filename against siblings; `account-detail.svelte` imports it), `ConfirmationModal`, `Button`.
- Produces: the show page used by `transaction-templates.show`.

- [x] **Step 1: Write `resources/js/components/module/template/template-detail.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { DataComposer } from '@lib/data-composer';
    import { templateSchema } from '@schema/template.schema';

    import DataList from '@components/data/data-list.svelte';

    let { template }: { template: App.Models.TransactionTemplate } = $props();

    const details = $derived(DataComposer.from(templateSchema).toDataDisplay(template));
</script>

<DataList data={details} />
```

(If `account-detail.svelte` resolves account names differently — e.g. it consumes an `accounts` prop — follow its exact pattern for the DataList composition; the schema already reads `data.account?.name` from the eager-loaded relation, so no `accounts` prop should be needed here.)

- [x] **Step 2: Write `resources/js/pages/app/settings/transaction-templates/show.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router, setLayoutProps } from '@inertiajs/svelte';
    import TransactionTemplateController from '@wayfinder/App/Http/Controllers/Settings/TransactionTemplateController';

    import Card from '@components/ui/card.svelte';
    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import TemplateDetail from '@components/module/template/template-detail.svelte';
    import Button from '@components/ui/button.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    let { template }: { template: App.Models.TransactionTemplate } = $props();

    setLayoutProps({ title: template.name, backUrl: TransactionTemplateController.index.url() });

    let showDeleteConfirm = $state(false);

    function destroy(): void {
        router.delete(TransactionTemplateController.destroy.url({ template: template.id }));
    }
</script>

<MobilePageLayout variant="full">
    <div class="space-y-6">
        <Card title={template.name}>
            <TemplateDetail {template} />
        </Card>

        <div class="flex gap-3">
            <Button
                class="grow"
                color="primary"
                href={TransactionTemplateController.edit.url({ template: template.id })}
                variant="outline">
                Edit
            </Button>

            <Button color="error" onclick={() => (showDeleteConfirm = true)} variant="outline">
                <i class="iconify size-5 solar--trash-bin-minimalistic-bold-duotone"></i>
                Delete
            </Button>
        </div>
    </div>
</MobilePageLayout>

<ConfirmationModal
    title="Delete Template"
    confirmText="Delete"
    cancelText="Cancel"
    confirmButtonProps={{ color: 'error' }}
    onConfirm={destroy}
    bind:open={showDeleteConfirm}>
    This will permanently delete the template. It cannot be undone.
</ConfirmationModal>
```

---

### Task 4: template-select + create-form wiring + README

**Complexity:** Medium

**Files:**
- Create: `resources/js/components/module/template/template-select.svelte`
- Modify: `resources/js/pages/app/transaction/create.svelte`
- Modify: `resources/js/components/module/transaction/transaction-form.svelte`
- Modify: `README.md`

**Interfaces:**
- Consumes: `templates` prop (backend Task 4's `getQuickAccessTemplates`), `TransactionController.create.url({ query })`, `DecorationBadge`, `CurrencyAmount`.
- Produces: the complete quick-access surface; nothing downstream.

- [x] **Step 1: Write `resources/js/components/module/template/template-select.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import CurrencyAmount from '@components/data/currency-amount.svelte';
    import DecorationBadge from '@components/module/decoration-badge.svelte';

    let {
        templates,
        selectedAccountId = null,
    }: {
        templates: App.Models.TransactionTemplate[];
        selectedAccountId?: string | number | null;
    } = $props();

    /* Presets bound to the selected account surface first; the rest follow. */
    const ordered = $derived.by(() => {
        const key = String(selectedAccountId ?? '');

        return [...templates].sort((a, b) => {
            const aMatch = a.account_id !== null && String(a.account_id) === key ? 0 : 1;
            const bMatch = b.account_id !== null && String(b.account_id) === key ? 0 : 1;

            return aMatch - bMatch;
        });
    });

    /* Chips are pure navigation: the server folds the template (§8 of the spec). */
    function apply(template: App.Models.TransactionTemplate): void {
        router.visit(
            TransactionController.create.url({
                query: {
                    template: template.id,
                    ...(selectedAccountId ? { account_id: selectedAccountId } : {}),
                },
            }),
            { preserveScroll: true }
        );
    }
</script>

<div class="-mx-5 flex gap-2 overflow-x-auto px-5 pb-1">
    {#each ordered as template (template.id)}
        <button
            class="flex shrink-0 items-center gap-2 rounded-full border border-base-content/15 bg-base-100 px-3 py-1.5 transition-colors hover:border-base-content/35"
            type="button"
            onclick={() => apply(template)}>
            <DecorationBadge decorations={template.decorations} size="sm" />
            <span class="whitespace-nowrap text-sm font-medium">{template.name}</span>
            <span class="whitespace-nowrap text-xs text-base-content/50">
                <CurrencyAmount value={Number(template.amount)} />
            </span>
        </button>
    {/each}
</div>
```

- [x] **Step 2: Forward the prop in `resources/js/pages/app/transaction/create.svelte`**

```svelte
<script lang="ts">
    import type { Data, Models } from '@type/type';
    import type { App } from '@wayfinder/types';

    import { setLayoutProps } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import TransactionForm from '@components/module/transaction/transaction-form.svelte';

    let {
        transaction,
        accounts,
        templates = [],
    }: {
        transaction: Data.TransactionFormData;
        accounts: Models.Account[];
        templates?: App.Models.TransactionTemplate[];
    } = $props();

    setLayoutProps({ title: 'Add New Transaction', backUrl: TransactionController.index.url() });
</script>

<TransactionForm {accounts} {templates} {transaction} />
```

- [x] **Step 3: Render the select in `resources/js/components/module/transaction/transaction-form.svelte`**

Add to the imports: `import TemplateSelect from '@components/module/template/template-select.svelte';`

Extend the `Props` interface and destructuring (after `amountPresets`):

```ts
        /** Quick-access templates — create surface only. */
        templates?: App.Models.TransactionTemplate[];
```

```ts
    let { transaction, accounts, templates = [], amountPresets = DEFAULT_AMOUNT_PRESETS }: Props = $props();
```

Render directly under the type `Tabs` block, still inside the `<Form>`, gated to create mode:

```svelte
        {#if !isEdit && templates.length}
            <div class="mt-4">
                <TemplateSelect {templates} selectedAccountId={form.account_id} />
            </div>
        {/if}
```

- [x] **Step 4: Reword the README roadmap**

In `README.md` → `## Roadmap` → `### Current focus`: delete the "Transaction Presets Feature" block (its three bullets) and leave "Transaction Tagging Feature" as the remaining current-focus item. In `### Later`, reword the scheduled-transaction entry to template vocabulary:

```markdown
- Scheduled Transaction (Template improvement)
  - from a transaction template the user can set it as a scheduled transaction, either requiring confirmation or not
```

Also add a sentence to the README domain narrative (near the Transaction section) once shipped:

```markdown
- **Templates**: user-private transaction form presets (name, decoration, type, category or transfer destination, amount, optional default source account), managed under Settings and applied server-side to prefill the create form via `?template=`.
```

---
## Self-Review (completed)

Spec §9–§10, §13 (frontend half) all map to Tasks 1–4; no placeholders (the `Select` atom's props are pinned to `form-generator.svelte`'s rendering); component names consistent; `direct` mode — no test/run/commit steps (no JS suite exists). Complexity: Medium.
