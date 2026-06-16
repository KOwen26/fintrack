# Foundation — Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. Activate `inertia-svelte-development` skill when writing any Inertia/Svelte page or component.

**Goal:** Build the mobile-first app shell and all Foundation Svelte pages — app layout with bottom nav, theme hook, enum badge components, and pages for accounts, categories, household, invitation, and theme settings.

**Architecture:** Svelte 5 runes. Inertia v3 layout via `app.ts` layout function. Theme stored in `users.theme_preference`, applied client-side via `$effect`. Wayfinder (`next`) for all route calls. Enum badge components wrap the existing `Badge` component using Wayfinder-generated enum constants. Existing components are always preferred over raw HTML.

**Tech Stack:** Svelte 5, TypeScript, Inertia.js v3, Tailwind v4, DaisyUI, Wayfinder (next), bits-ui

**Prerequisite:** Backend plan (`2026-06-14-foundation-backend.md`) fully complete.

---

## Component Reference (existing — always prefer these)

| Component           | Import                                            | Key Props                                                                                                |
| ------------------- | ------------------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| `Badge`             | `@components/ui/badge.svelte`                     | `color` (primary/secondary/accent/success/info/warning/error/light/dark), `variant` (solid/outline/soft) |
| `Button`            | `@components/ui/button.svelte`                    | `color`, `variant` (solid/outline/ghost/soft/link), `href` (renders as `<a>`), `disabled`                |
| `Card`              | `@components/ui/card.svelte`                      | `title`, `header`, `headerAction` snippet, `footer` snippet, `wrapperClass`                              |
| `FormGenerator`     | `@components/ui/forms/form-generator.svelte`      | `formSchema: {fields, data}`, `bind:form`, `action`, `method`, `withoutSubmit`                           |
| `Form`              | `@components/ui/forms/form.svelte`                | `form`, `action`, `method`, `submitOptions` — wraps `<form>` with Inertia + method spoofing              |
| `FormAction`        | `@components/ui/forms/form-action.svelte`         | `form`, `formId`, `labelSubmit`, `labelCancel`, `withoutCancel`, `onCancel`                              |
| `ConfirmationModal` | `@components/ui/modals/confirmation-modal.svelte` | `bind:open`, `title`, `onConfirm`, `loading`, `confirmText`, `cancelText`, `confirmButtonProps`          |
| `DataList`          | `@components/data/data-list.svelte`               | `data: [{label, value}]` — feed from `DataComposer.toDataDisplay()`                                      |
| `DataComposer`      | `@utilities/data-composer`                        | `.from(schema)`, `.extendSchema()`, `.except()`, `.toFormGenerator()`, `.toDataDisplay()`                |
| `Toaster`           | `@components/ui/toaster.svelte`                   | —                                                                                                        |
| Module components   | `@components/module/{module}/`                    | Feature-scoped: badge variants + form components per domain                                              |

### FormGenerator field types

| `type`                      | Extra props                                   | Maps to                         |
| --------------------------- | --------------------------------------------- | ------------------------------- |
| `text` / `email` / `number` | `inputProps`                                  | `Input`                         |
| `select`                    | `options: [{value, label}]`                   | `Select` (svelecte)             |
| `radio`                     | `options: [{value, label}]`, `inputItemProps` | `RadioGroup` + `RadioGroupItem` |
| `checkbox`                  | `options: [{value, label}]`                   | `CheckboxGroup`                 |
| `switch`                    | `inputProps`                                  | `Switch`                        |
| `textarea`                  | `inputProps`                                  | `Textarea`                      |
| `date`                      | `inputProps`                                  | `DateInput`                     |

All field types share: `name` (form key), `fieldProps.title` (label), `required`, `show` (bool or `(form) => bool`), `disabledFn: (form) => bool`.

### FormGenerator pattern

```svelte
<FormGenerator
    id="my-form"
    bind:form
    formSchema={{ fields: { ... }, data: { ... } }}
    action={SomeController.store.url()}
    method="post"
    withoutSubmit
/>
<FormAction {form} formId="my-form" labelSubmit="Save" withoutCancel />
```

- `data` keys not in `fields` are still submitted (use for hidden values like `household_id`)
- `show: (form) => form.type === 'credit_card'` makes a field conditionally visible
- `withoutSubmit` + `FormAction` with `formId` lets the button live outside the `<form>` tag

---

## File Map

```
resources/js/schema/
  account.schema.ts
  category.schema.ts
  household.schema.ts

resources/js/hooks/
  use-theme.svelte.ts

resources/js/components/module/
  account/
    account-type-badge.svelte
    account-access-type-badge.svelte
    account-form.svelte
  category/
    category-form.svelte
  household/
    household-member-role-badge.svelte
    household-form.svelte
    household-invite-form.svelte
  provider/
    provider-type-badge.svelte

resources/js/components/layouts/
  app-layout.svelte

resources/js/components/navigation/
  bottom-nav.svelte

resources/js/app.ts                   (modify)

resources/js/pages/
  accounts/index.svelte
  accounts/create.svelte
  accounts/edit.svelte
  accounts/show.svelte
  categories/index.svelte
  household/settings.svelte
  household/invitation.svelte
  settings/theme.svelte
```

---

## Task 1: Generate Wayfinder Types

- [ ] **Run Wayfinder generation**

```bash
php artisan wayfinder:generate --no-interaction
```

Expected: `resources/js/wayfinder/` populated with controller files, enum constant files, and `types.d.ts`. Verify `App/Enums/AccountType.ts` exists and exports constants.

---

## Task 2: Data Schemas (DataComposer)

One schema file per model in `resources/js/schema/`. Schemas use the in-house `DataComposer` system (`@utilities/data-composer`) — a single `DataSchema` object drives form fields (`FormGenerator`), display values (`DataList`), and table columns in one place. No external library needed.

Each `DataSchemaItem` declares:

- `label` — display label and form field title
- `value` — optional formatter for display (e.g. number → localised string)
- `form` — factory returning `FormGeneratorProps` for `FormGenerator`
- `table` — whether this field appears as a table column
- `show` — conditional visibility (bool or predicate)

Dynamic options (e.g. provider list from page props) are not in the static schema — extend in the page via `DataComposer.extendSchema()`.

- [ ] **Create `resources/js/schema/account.schema.ts`**

```typescript
import type { DataSchema } from '@utilities/data-composer';
import type { App } from '@wayfinder/types';

import AccountAccessType from '@wayfinder/App/Enums/AccountAccessType';
import AccountType from '@wayfinder/App/Enums/AccountType';

export const accountSchema: DataSchema<App.Models.Account> = {
    name: {
        label: 'Name',
        table: true,
        form: () => ({
            type: 'text',
            name: 'name',
            required: true,
            inputProps: {
                placeholder: 'e.g. BCA Savings',
                autocorrect: 'off',
                autocomplete: 'off',
            },
        }),
    },
    type: {
        label: 'Account Type',
        table: true,
        form: () => ({
            type: 'select',
            name: 'type',
            required: true,
            options: [
                { value: AccountType.DebitAccount, label: 'Debit / Savings' },
                { value: AccountType.CreditCard, label: 'Credit Card' },
                { value: AccountType.CashWallet, label: 'Cash Wallet' },
                { value: AccountType.EWallet, label: 'E-Wallet' },
                { value: AccountType.Investment, label: 'Investment' },
            ],
        }),
    },
    access_type: {
        label: 'Access',
        form: () => ({
            type: 'radio',
            name: 'access_type',
            required: true,
            options: [
                { value: AccountAccessType.Personal, label: 'Personal' },
                { value: AccountAccessType.Joint, label: 'Joint' },
            ],
        }),
    },
    // provider_id is NOT here — options are dynamic (page prop). Extended per-page.
    initial_balance: {
        label: 'Initial Balance',
        value: (data) => Number(data.initial_balance).toLocaleString('id-ID'),
        form: () => ({
            type: 'number',
            name: 'initial_balance',
            required: true,
            inputProps: { inputmode: 'decimal', min: 0, step: 0.01 },
        }),
    },
    credit_card_limit: {
        label: 'Credit Limit',
        show: (data) => data.type === AccountType.CreditCard,
        value: (data) =>
            data.credit_card_limit ? Number(data.credit_card_limit).toLocaleString('id-ID') : '-',
        form: () => ({
            type: 'number',
            name: 'credit_card_limit',
            show: (form: any) => form.type === AccountType.CreditCard,
            inputProps: { inputmode: 'decimal', min: 0, step: 0.01 },
        }),
    },
    currency: {
        label: 'Currency',
        table: true,
        form: () => ({
            type: 'text',
            name: 'currency',
            required: true,
            inputProps: {
                maxlength: 3,
                placeholder: 'IDR',
                autocorrect: 'off',
                autocapitalize: 'characters',
            },
        }),
    },
};
```

- [ ] **Create `resources/js/schema/category.schema.ts`**

```typescript
import type { DataSchema } from '@utilities/data-composer';
import type { App } from '@wayfinder/types';

export const categorySchema: DataSchema<App.Models.Category> = {
    name: {
        label: 'Name',
        table: true,
        form: () => ({
            type: 'text',
            name: 'name',
            required: true,
            inputProps: { placeholder: 'Category name', autocorrect: 'off' },
        }),
    },
    color: {
        label: 'Color',
        form: () => ({
            type: 'text',
            name: 'color',
            inputProps: { placeholder: '#6366f1' },
        }),
    },
    is_fixed_cost: {
        label: 'Fixed cost',
        form: () => ({
            type: 'switch',
            name: 'is_fixed_cost',
        }),
    },
    // parent_id options are dynamic — extended in page
};
```

- [ ] **Create `resources/js/schema/household.schema.ts`**

```typescript
import type { DataSchema } from '@utilities/data-composer';
import type { App } from '@wayfinder/types';

export const householdSchema: DataSchema<App.Models.Household> = {
    name: {
        label: 'Household name',
        form: () => ({
            type: 'text',
            name: 'name',
            required: true,
            inputProps: { placeholder: 'e.g. Kevin & Partner', autocorrect: 'off' },
        }),
    },
};

// Separate schema for the invite form (different endpoint, different data shape)
export const householdInviteSchema: DataSchema<{ email: string }> = {
    email: {
        label: 'Partner email',
        form: () => ({
            type: 'email',
            name: 'email',
            required: true,
            inputProps: {
                placeholder: 'partner@example.com',
                autocorrect: 'off',
                autocapitalize: 'none',
            },
        }),
    },
};
```

### DataComposer usage pattern in pages

```typescript
import { DataComposer } from '@utilities/data-composer';
import { accountSchema } from '@schema/account.schema';

// Build formSchema for FormGenerator (create page)
const { fields, data } = DataComposer.from(accountSchema)
    .extendSchema({
        provider_id: {
            label: 'Provider (optional)',
            form: () => ({
                type: 'select',
                name: 'provider_id',
                options: [{ value: '', label: '— None —' }, ...providers.map(p => ({ value: p.id, label: p.name }))],
            }),
        },
    })
    .toFormGenerator({ type: AccountType.DebitAccount, ... });

// Inject non-field data (household_id is submitted but not displayed)
const formSchema = { fields, data: { ...data, household_id: household_id ?? '' } };

// Build display data for DataList (show page)
const displayData = DataComposer.from(accountSchema)
    .except(['access_type'])   // shown via badge, not in list
    .toDataDisplay(account);
```

---

## Task 3: Enum Badge Components

Each badge wraps the existing `Badge` component. The config map uses Wayfinder enum constants as keys — when an enum value changes, TypeScript will error at the map, not silently break.

- [ ] **Create `resources/js/components/module/account/account-type-badge.svelte`**

```svelte
<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { App } from '@wayfinder/types';

    import AccountType from '@wayfinder/App/Enums/AccountType';

    import Badge from '@components/ui/badge.svelte';

    let { type }: { type: App.Enums.AccountType } = $props();

    const config: Record<App.Enums.AccountType, { label: string; color: ColorVariant }> = {
        [AccountType.DebitAccount]: { label: 'Debit', color: 'primary' },
        [AccountType.CreditCard]: { label: 'Credit Card', color: 'warning' },
        [AccountType.CashWallet]: { label: 'Cash', color: 'success' },
        [AccountType.EWallet]: { label: 'E-Wallet', color: 'info' },
        [AccountType.Investment]: { label: 'Investment', color: 'secondary' },
    };

    const badge = $derived(config[type]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
```

- [ ] **Create `resources/js/components/module/account/account-access-type-badge.svelte`**

```svelte
<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { App } from '@wayfinder/types';

    import AccountAccessType from '@wayfinder/App/Enums/AccountAccessType';

    import Badge from '@components/ui/badge.svelte';

    let { type }: { type: App.Enums.AccountAccessType } = $props();

    const config: Record<App.Enums.AccountAccessType, { label: string; color: ColorVariant }> = {
        [AccountAccessType.Personal]: { label: 'Personal', color: 'light' },
        [AccountAccessType.Joint]: { label: 'Joint', color: 'accent' },
    };

    const badge = $derived(config[type]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
```

- [ ] **Create `resources/js/components/module/provider/provider-type-badge.svelte`**

```svelte
<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { App } from '@wayfinder/types';

    import ProviderType from '@wayfinder/App/Enums/ProviderType';

    import Badge from '@components/ui/badge.svelte';

    let { type }: { type: App.Enums.ProviderType } = $props();

    const config: Record<App.Enums.ProviderType, { label: string; color: ColorVariant }> = {
        [ProviderType.Bank]: { label: 'Bank', color: 'primary' },
        [ProviderType.DigitalBank]: { label: 'Digital Bank', color: 'info' },
        [ProviderType.EWallet]: { label: 'E-Wallet', color: 'success' },
        [ProviderType.CreditLoan]: { label: 'Credit / Loan', color: 'warning' },
        [ProviderType.Investment]: { label: 'Investment', color: 'secondary' },
    };

    const badge = $derived(config[type]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
```

- [ ] **Create `resources/js/components/module/household/household-member-role-badge.svelte`**

```svelte
<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { App } from '@wayfinder/types';

    import HouseholdMemberRole from '@wayfinder/App/Enums/HouseholdMemberRole';

    import Badge from '@components/ui/badge.svelte';

    let { role }: { role: App.Enums.HouseholdMemberRole } = $props();

    const config: Record<App.Enums.HouseholdMemberRole, { label: string; color: ColorVariant }> = {
        [HouseholdMemberRole.Owner]: { label: 'Owner', color: 'primary' },
        [HouseholdMemberRole.Member]: { label: 'Member', color: 'light' },
    };

    const badge = $derived(config[role]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
```

---

## Task 4: Module Form Components

Extract all form logic into module components under `resources/js/components/module/{module}/`. Pages become thin — they import the module component and pass props.

- [ ] **Create `resources/js/components/module/account/account-form.svelte`**

Single component for both create (no `account` prop) and edit (`account` prop provided) modes.

```svelte
<script lang="ts">
    import type { InertiaForm } from '@inertiajs/svelte';
    import type { App } from '@wayfinder/types';

    import AccountAccessType from '@wayfinder/App/Enums/AccountAccessType';
    import AccountType from '@wayfinder/App/Enums/AccountType';
    import { AccountsController } from '@wayfinder/App/Http/Controllers/AccountsController';

    import { accountSchema } from '@schema/account.schema';

    import { DataComposer } from '@utilities/data-composer';

    import Card from '@components/ui/card.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import FormGenerator from '@components/ui/forms/form-generator.svelte';

    interface Props {
        providers: App.Models.Provider[];
        household_id?: number | null;
        account?: App.Models.Account;
        onCancel?: () => void;
    }

    let { providers, household_id, account, onCancel }: Props = $props();

    let form: InertiaForm<any> = $state(null!);

    const isEdit = $derived(!!account);

    const providerOptions = $derived([
        { value: '', label: '— None —' },
        ...providers.map((p) => ({ value: p.id, label: p.name })),
    ]);

    const formSchema = $derived(() => {
        const composer = DataComposer.from(accountSchema).extendSchema({
            provider_id: {
                label: 'Provider (optional)',
                form: () => ({ type: 'select', name: 'provider_id', options: providerOptions }),
            },
        });

        if (isEdit && account) {
            return composer.except(['access_type', 'initial_balance']).toFormGenerator({
                name: account.name,
                type: account.type,
                provider_id: account.provider_id ?? '',
                credit_card_limit: account.credit_card_limit
                    ? Number(account.credit_card_limit)
                    : null,
                currency: account.currency,
            });
        }

        const { fields, data } = composer.toFormGenerator({
            type: AccountType.DebitAccount,
            access_type: AccountAccessType.Personal,
            provider_id: '',
            initial_balance: 0,
            credit_card_limit: null,
            currency: 'IDR',
        });

        return { fields, data: { ...data, household_id: household_id ?? '' } };
    });

    const action = $derived(
        isEdit && account
            ? AccountsController.update.url({ account: account.id })
            : AccountsController.store.url()
    );

    const method = $derived(isEdit ? 'put' : undefined);
    const submitLabel = $derived(isEdit ? 'Save Changes' : 'Create Account');
</script>

<Card>
    <FormGenerator
        id="account-form"
        bind:form
        formSchema={formSchema()}
        {action}
        {method}
        withoutSubmit />
</Card>

<div class="mt-4">
    <FormAction
        {form}
        formId="account-form"
        labelSubmit={submitLabel}
        labelCancel="Cancel"
        onCancel={onCancel ?? (() => window.history.back())}
        withoutCancel={!onCancel && !isEdit} />
</div>
```

- [ ] **Create `resources/js/components/module/category/category-form.svelte`**

Inline add-category form. Accepts `categories` for parent options; calls `onSuccess`/`onCancel` callbacks.

```svelte
<script lang="ts">
    import type { InertiaForm } from '@inertiajs/svelte';
    import type { App } from '@wayfinder/types';

    import { CategoriesController } from '@wayfinder/App/Http/Controllers/CategoriesController';

    import { categorySchema } from '@schema/category.schema';

    import { DataComposer } from '@utilities/data-composer';

    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import FormGenerator from '@components/ui/forms/form-generator.svelte';

    interface Props {
        categories: App.Models.Category[];
        onSuccess?: () => void;
        onCancel?: () => void;
    }

    let { categories, onSuccess, onCancel }: Props = $props();

    let form: InertiaForm<any> = $state(null!);

    const parentOptions = $derived([
        { value: '', label: 'Top-level group' },
        ...categories.map((c) => ({ value: c.id, label: c.name })),
    ]);

    const formSchema = $derived(() =>
        DataComposer.from(categorySchema)
            .extendSchema({
                parent_id: {
                    label: 'Parent group',
                    form: () => ({ type: 'select', name: 'parent_id', options: parentOptions }),
                },
            })
            .toFormGenerator({
                name: '',
                color: '#6366f1',
                icon: 'ph:tag',
                parent_id: '',
                is_fixed_cost: false,
            })
    );
</script>

<Card wrapperClass="mb-4">
    <FormGenerator
        id="add-category"
        bind:form
        formSchema={formSchema()}
        action={CategoriesController.store.url()}
        withoutSubmit
        submitOptions={{
            onSuccess: () => {
                form?.reset?.();
                onSuccess?.();
            },
        }} />
    <div class="mt-4 flex gap-2">
        <FormAction {form} formId="add-category" labelSubmit="Save" withoutCancel class="flex-1" />
        {#if onCancel}
            <Button color="light" variant="outline" onclick={onCancel}>Cancel</Button>
        {/if}
    </div>
</Card>
```

- [ ] **Create `resources/js/components/module/household/household-form.svelte`**

```svelte
<script lang="ts">
    import type { InertiaForm } from '@inertiajs/svelte';

    import { HouseholdsController } from '@wayfinder/App/Http/Controllers/HouseholdsController';

    import { householdSchema } from '@schema/household.schema';

    import { DataComposer } from '@utilities/data-composer';

    import Card from '@components/ui/card.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import FormGenerator from '@components/ui/forms/form-generator.svelte';

    let form: InertiaForm<any> = $state(null!);

    const formSchema = DataComposer.from(householdSchema).toFormGenerator({ name: '' });
</script>

<Card title="Create Household">
    <FormGenerator
        id="create-household"
        bind:form
        {formSchema}
        action={HouseholdsController.store.url()}
        withoutSubmit />
    <div class="mt-4">
        <FormAction {form} formId="create-household" labelSubmit="Create Household" withoutCancel />
    </div>
</Card>
```

- [ ] **Create `resources/js/components/module/household/household-invite-form.svelte`**

```svelte
<script lang="ts">
    import type { InertiaForm } from '@inertiajs/svelte';

    import { HouseholdsController } from '@wayfinder/App/Http/Controllers/HouseholdsController';

    import { householdInviteSchema } from '@schema/household.schema';

    import { DataComposer } from '@utilities/data-composer';

    import Card from '@components/ui/card.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import FormGenerator from '@components/ui/forms/form-generator.svelte';

    let form: InertiaForm<any> = $state(null!);

    const formSchema = DataComposer.from(householdInviteSchema).toFormGenerator({ email: '' });
</script>

<Card title="Invite Partner">
    <FormGenerator
        id="invite-member"
        bind:form
        {formSchema}
        action={HouseholdsController.invite.url()}
        withoutSubmit
        submitOptions={{ onSuccess: () => form?.reset?.() }} />
    <div class="mt-4">
        <FormAction {form} formId="invite-member" labelSubmit="Send Invitation" withoutCancel />
    </div>
</Card>
```

---

## Task 5: Theme Hook

- [ ] **Create `resources/js/hooks/use-theme.svelte.ts`**

```typescript
import type { App } from '@wayfinder/types';

import { page } from '@inertiajs/svelte';

export function useTheme() {
    const current = $derived(
        (page.props.auth?.user as App.Models.User | null)?.theme_preference ?? 'light'
    );

    $effect(() => {
        document.documentElement.dataset.theme = current;
    });

    return {
        get current() {
            return current;
        },
    };
}
```

---

## Task 6: App Layout + Bottom Nav + `app.ts`

- [ ] **Create `resources/js/components/navigation/bottom-nav.svelte`**

```svelte
<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import { AccountsController } from '@wayfinder/App/Http/Controllers/AccountsController';
    import { CategoriesController } from '@wayfinder/App/Http/Controllers/CategoriesController';
    import { HouseholdsController } from '@wayfinder/App/Http/Controllers/HouseholdsController';

    import Button from '@components/ui/button.svelte';

    const currentRoute = $derived(
        (page.props.meta as { current_route_name?: string } | null)?.current_route_name ?? ''
    );
    const isActive = (prefix: string) => currentRoute.startsWith(prefix);
</script>

<nav
    class="btm-nav btm-nav-sm fixed bottom-0 left-0 right-0 z-50 border-t border-base-300 bg-base-100">
    <a
        href={AccountsController.index.url()}
        class:active={isActive('accounts')}
        aria-label="Accounts">
        <i class="iconify size-5 ph--wallet-bold"></i>
        <span class="btm-nav-label text-xs">Accounts</span>
    </a>

    <a
        href={CategoriesController.index.url()}
        class:active={isActive('categories')}
        aria-label="Categories">
        <i class="iconify size-5 ph--tag-bold"></i>
        <span class="btm-nav-label text-xs">Categories</span>
    </a>

    <button class="rounded-full bg-primary text-primary-content" disabled aria-label="Quick add">
        <i class="iconify size-6 ph--plus-bold"></i>
    </button>

    <a
        href={HouseholdsController.show.url()}
        class:active={isActive('household')}
        aria-label="Household">
        <i class="iconify size-5 ph--users-bold"></i>
        <span class="btm-nav-label text-xs">Household</span>
    </a>

    <a href="/dashboard" class:active={isActive('dashboard')} aria-label="Reports">
        <i class="iconify size-5 ph--chart-bar-bold"></i>
        <span class="btm-nav-label text-xs">Reports</span>
    </a>
</nav>
```

- [ ] **Create `resources/js/components/layouts/app-layout.svelte`**

```svelte
<script lang="ts">
    import { useFlashToast } from '@hooks/flash-handler.svelte';
    import { useTheme } from '@hooks/use-theme.svelte';

    import BottomNav from '@components/navigation/bottom-nav.svelte';
    import Toaster from '@components/ui/toaster.svelte';

    let { children } = $props();

    useTheme();
    useFlashToast();
</script>

<svelte:head>
    <title>{import.meta.env.VITE_APP_NAME || 'FinTrack'}</title>
</svelte:head>

<Toaster />
<div class="min-h-screen bg-base-100 pb-20">
    {@render children()}
</div>
<BottomNav />
```

- [ ] **Update `resources/js/app.ts` — add `AppLayout` import and cases**

Add alongside existing `DashboardLayout` import:

```typescript
import AppLayout from '@components/layouts/app-layout.svelte';
```

Add cases inside the existing `layout()` function:

```typescript
case name.startsWith('accounts'):
case name.startsWith('categories'):
case name.startsWith('household'):
case name.startsWith('settings/theme'):
    return AppLayout;
```

---

## Task 7: Account Pages

- [ ] **Create `resources/js/pages/accounts/index.svelte`**

No form — no FormGenerator needed.

```svelte
<script context="module" lang="ts">
    import { inertia } from '@inertiajs/svelte';
</script>

<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { inertia } from '@inertiajs/svelte';
    import { AccountsController } from '@wayfinder/App/Http/Controllers/AccountsController';

    import AccountAccessTypeBadge from '@components/module/account/account-access-type-badge.svelte';
    import AccountTypeBadge from '@components/module/account/account-type-badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';

    let { accounts }: { accounts: App.Models.Account[] } = $props();
</script>

<div class="p-4">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-bold">Accounts</h1>
        <Button color="primary" href={AccountsController.create.url()} size="sm">
            <i class="iconify size-4 ph--plus-bold"></i>
            Add
        </Button>
    </div>

    {#if accounts.length === 0}
        <div class="flex flex-col items-center justify-center py-16 text-base-content/50">
            <i class="iconify mb-3 size-12 ph--wallet-bold"></i>
            <p class="text-sm">No accounts yet</p>
            <Button color="primary" href={AccountsController.create.url()} class="mt-4" size="sm">
                Create your first account
            </Button>
        </div>
    {:else}
        <div class="space-y-3">
            {#each accounts as account (account.id)}
                <a href={AccountsController.show.url({ account: account.id })} use:inertia>
                    <Card wrapperClass="transition-transform active:scale-95">
                        <div class="flex items-center justify-between">
                            <div class="space-y-1">
                                <p class="font-semibold">{account.name}</p>
                                <div class="flex items-center gap-1">
                                    <AccountTypeBadge type={account.type} />
                                    <AccountAccessTypeBadge type={account.access_type} />
                                    <span class="text-xs text-base-content/50"
                                        >{account.currency}</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-base-content/50">Initial Balance</p>
                                <p class="font-mono font-semibold">
                                    {Number(account.initial_balance).toLocaleString('id-ID')}
                                </p>
                            </div>
                        </div>
                    </Card>
                </a>
            {/each}
        </div>
    {/if}
</div>
```

- [ ] **Create `resources/js/pages/accounts/create.svelte`**

Delegates entirely to `AccountForm` module component.

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { AccountsController } from '@wayfinder/App/Http/Controllers/AccountsController';

    import AccountForm from '@components/module/account/account-form.svelte';
    import Button from '@components/ui/button.svelte';

    let {
        providers,
        household_id,
    }: { providers: App.Models.Provider[]; household_id: number | null } = $props();
</script>

<div class="p-4">
    <div class="mb-4 flex items-center gap-3">
        <Button
            color="light"
            variant="ghost"
            href={AccountsController.index.url()}
            class="btn-circle btn-sm">
            <i class="iconify size-5 ph--arrow-left-bold"></i>
        </Button>
        <h1 class="text-xl font-bold">New Account</h1>
    </div>

    <AccountForm {providers} {household_id} />
</div>
```

- [ ] **Create `resources/js/pages/accounts/edit.svelte`**

Delegates form to `AccountForm`. Keeps archive/delete actions and their confirmation modals in the page.

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import { AccountsController } from '@wayfinder/App/Http/Controllers/AccountsController';

    import AccountForm from '@components/module/account/account-form.svelte';
    import Button from '@components/ui/button.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    let { account, providers }: { account: App.Models.Account; providers: App.Models.Provider[] } =
        $props();

    let showArchiveConfirm = $state(false);
    let showDeleteConfirm = $state(false);

    function archive() {
        router.post(AccountsController.archive.url({ account: account.id }));
    }
    function destroy() {
        router.delete(AccountsController.destroy.url({ account: account.id }));
    }
</script>

<div class="p-4">
    <div class="mb-4 flex items-center gap-3">
        <Button
            color="light"
            variant="ghost"
            href={AccountsController.show.url({ account: account.id })}
            class="btn-circle btn-sm">
            <i class="iconify size-5 ph--arrow-left-bold"></i>
        </Button>
        <h1 class="text-xl font-bold">Edit Account</h1>
    </div>

    <AccountForm
        {providers}
        {account}
        onCancel={() => router.visit(AccountsController.show.url({ account: account.id }))} />

    <div class="mt-4 space-y-3">
        <Button
            color="warning"
            variant="outline"
            class="w-full"
            onclick={() => (showArchiveConfirm = true)}>
            <i class="iconify size-4 ph--archive-bold"></i>
            Archive Account
        </Button>
        <Button
            color="error"
            variant="outline"
            class="w-full"
            onclick={() => (showDeleteConfirm = true)}>
            <i class="iconify size-4 ph--trash-bold"></i>
            Delete Account
        </Button>
    </div>
</div>

<ConfirmationModal
    bind:open={showArchiveConfirm}
    title="Archive Account"
    confirmText="Archive"
    cancelText="Cancel"
    onConfirm={archive}
    confirmButtonProps={{ color: 'warning' }}>
    This account will be hidden from active views. You can restore it later.
</ConfirmationModal>

<ConfirmationModal
    bind:open={showDeleteConfirm}
    title="Delete Account"
    confirmText="Delete"
    cancelText="Cancel"
    onConfirm={destroy}
    confirmButtonProps={{ color: 'error' }}>
    This will permanently delete the account and cannot be undone.
</ConfirmationModal>
```

- [ ] **Create `resources/js/pages/accounts/show.svelte`**

`DataComposer.toDataDisplay()` drives the detail list — `value` formatters from the schema handle localisation. `type` and `access_type` are excluded because they're shown as badges above.

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { AccountsController } from '@wayfinder/App/Http/Controllers/AccountsController';

    import { accountSchema } from '@schema/account.schema';

    import { DataComposer } from '@utilities/data-composer';

    import DataList from '@components/data/data-list.svelte';
    import AccountAccessTypeBadge from '@components/module/account/account-access-type-badge.svelte';
    import AccountTypeBadge from '@components/module/account/account-type-badge.svelte';
    import ProviderTypeBadge from '@components/module/provider/provider-type-badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';

    let { account }: { account: App.Models.Account } = $props();

    const details = $derived(
        DataComposer.from(accountSchema)
            .except(['type', 'access_type', 'name']) // shown as heading/badges, not in list
            .toDataDisplay(account)
    );
</script>

<div class="p-4">
    <div class="mb-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <Button
                color="light"
                variant="ghost"
                href={AccountsController.index.url()}
                class="btn-circle btn-sm">
                <i class="iconify size-5 ph--arrow-left-bold"></i>
            </Button>
            <div>
                <h1 class="text-xl font-bold">{account.name}</h1>
                <div class="mt-1 flex items-center gap-1">
                    <AccountTypeBadge type={account.type} />
                    <AccountAccessTypeBadge type={account.access_type} />
                </div>
            </div>
        </div>
        <Button
            color="light"
            variant="ghost"
            href={AccountsController.edit.url({ account: account.id })}
            class="btn-circle btn-sm">
            <i class="iconify size-5 ph--pencil-simple-bold"></i>
        </Button>
    </div>

    <Card wrapperClass="mb-4">
        <DataList data={details} />
    </Card>

    {#if account.provider}
        <Card wrapperClass="mb-4">
            <div class="flex items-center justify-between">
                <p class="font-semibold">{account.provider.name}</p>
                <ProviderTypeBadge type={account.provider.type} />
            </div>
        </Card>
    {/if}

    <div class="flex flex-col items-center py-10 text-base-content/40">
        <i class="iconify mb-2 size-10 ph--receipt-bold"></i>
        <p class="text-sm">Transactions will appear here</p>
    </div>
</div>
```

---

## Task 8: Categories Page

The add-category panel uses `FormGenerator` with `withoutSubmit`. `icon` is in `data` but not `fields` (submitted silently with default value).

- [ ] **Create `resources/js/pages/categories/index.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import { CategoriesController } from '@wayfinder/App/Http/Controllers/CategoriesController';

    import CategoryForm from '@components/module/category/category-form.svelte';
    import Badge from '@components/ui/badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    let { categories }: { categories: App.Models.Category[] } = $props();

    let showForm = $state(false);
    let deletingId = $state<number | null>(null);

    function destroy() {
        if (!deletingId) return;
        router.delete(CategoriesController.destroy.url({ category: deletingId }), {
            onFinish: () => (deletingId = null),
        });
    }
</script>

<div class="p-4">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-bold">Categories</h1>
        <Button color="primary" size="sm" onclick={() => (showForm = !showForm)}>
            <i class="iconify size-4 ph--plus-bold"></i>
            Add
        </Button>
    </div>

    {#if showForm}
        <CategoryForm
            {categories}
            onSuccess={() => (showForm = false)}
            onCancel={() => (showForm = false)} />
    {/if}

    <div class="space-y-3">
        {#each categories as group (group.id)}
            <Card>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-block h-3 w-3 rounded-full"
                            style="background-color: {group.color}"></span>
                        <span class="font-semibold text-sm">{group.name}</span>
                        {#if group.is_fixed_cost}
                            <Badge color="light" variant="outline">Fixed</Badge>
                        {/if}
                    </div>
                    <Button
                        color="error"
                        variant="ghost"
                        class="btn-xs"
                        onclick={() => (deletingId = group.id)}>
                        <i class="iconify size-4 ph--trash-bold"></i>
                    </Button>
                </div>

                {#if group.children?.length}
                    <ul class="ml-5 mt-2 space-y-1 border-t border-base-200 pt-2">
                        {#each group.children as child (child.id)}
                            <li class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-block h-2 w-2 rounded-full"
                                        style="background-color: {child.color}"></span>
                                    <span class="text-sm">{child.name}</span>
                                    {#if child.is_fixed_cost}
                                        <Badge color="light" variant="outline">Fixed</Badge>
                                    {/if}
                                </div>
                                <Button
                                    color="error"
                                    variant="ghost"
                                    class="btn-xs"
                                    onclick={() => (deletingId = child.id)}>
                                    <i class="iconify size-4 ph--trash-bold"></i>
                                </Button>
                            </li>
                        {/each}
                    </ul>
                {/if}
            </Card>
        {/each}
    </div>
</div>

<ConfirmationModal
    bind:open={deletingId !== null}
    title="Delete Category"
    confirmText="Delete"
    onConfirm={destroy}
    onCancel={() => (deletingId = null)}
    confirmButtonProps={{ color: 'error' }}>
    This category will be soft-deleted. Transactions using it are unaffected.
</ConfirmationModal>
```

---

## Task 9: Household Pages

- [ ] **Create `resources/js/pages/household/settings.svelte`**

Two `FormGenerator` instances on the same page: one for creating a household, one for inviting a partner.

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import { HouseholdsController } from '@wayfinder/App/Http/Controllers/HouseholdsController';

    import HouseholdForm from '@components/module/household/household-form.svelte';
    import HouseholdInviteForm from '@components/module/household/household-invite-form.svelte';
    import HouseholdMemberRoleBadge from '@components/module/household/household-member-role-badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    let { household }: { household: App.Models.Household | null } = $props();
    let removingMemberId = $state<number | null>(null);

    function removeMember() {
        if (!removingMemberId) return;
        router.delete(HouseholdsController.removeMember.url({ member: removingMemberId }), {
            onFinish: () => (removingMemberId = null),
        });
    }
</script>

<div class="p-4">
    <h1 class="mb-4 text-xl font-bold">Household</h1>

    {#if !household}
        <HouseholdForm />
    {:else}
        <Card title={household.name} wrapperClass="mb-4">
            <div class="divide-y divide-base-200">
                {#each household.members as member (member.id)}
                    <div class="flex items-center justify-between py-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium">{member.name}</span>
                            <HouseholdMemberRoleBadge role={member.role} />
                        </div>
                        {#if member.role !== 'owner'}
                            <Button
                                color="error"
                                variant="ghost"
                                class="btn-xs"
                                onclick={() => (removingMemberId = member.id)}>
                                Remove
                            </Button>
                        {/if}
                    </div>
                {/each}
            </div>
        </Card>

        <HouseholdInviteForm />
    {/if}
</div>

<ConfirmationModal
    bind:open={removingMemberId !== null}
    title="Remove Member"
    confirmText="Remove"
    cancelText="Cancel"
    onConfirm={removeMember}
    onCancel={() => (removingMemberId = null)}
    confirmButtonProps={{ color: 'error' }}>
    This member will lose access to all joint accounts in this household.
</ConfirmationModal>
```

- [ ] **Create `resources/js/pages/household/invitation.svelte`**

Accept/decline are single-action forms — use `Form` component (no `FormGenerator` needed).

```svelte
<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import { HouseholdInvitationsController } from '@wayfinder/App/Http/Controllers/HouseholdInvitationsController';

    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import Form from '@components/ui/forms/form.svelte';
    import SubmitButton from '@components/ui/forms/submit-button.svelte';

    let {
        invitation,
    }: {
        invitation: {
            token: string;
            household_name: string;
            invited_by: string;
            expires_at: string;
        };
    } = $props();

    const acceptForm = useForm({});
    const declineForm = useForm({});
    const expiresAt = $derived(new Date(invitation.expires_at).toLocaleDateString('id-ID'));
</script>

<div class="flex min-h-screen items-center justify-center p-6">
    <Card wrapperClass="w-full max-w-sm text-center shadow-lg">
        <i class="iconify mx-auto mb-4 size-12 ph--envelope-open-bold text-primary"></i>
        <h1 class="mb-1 text-lg font-bold">You're invited!</h1>
        <p class="mb-2 text-sm text-base-content/60">
            <strong>{invitation.invited_by}</strong> invited you to join
        </p>
        <p class="mb-1 text-xl font-bold">{invitation.household_name}</p>
        <p class="mb-6 text-xs text-base-content/40">Expires {expiresAt}</p>

        <div class="space-y-3">
            <Form
                form={acceptForm}
                action={HouseholdInvitationsController.accept.url({ token: invitation.token })}>
                <SubmitButton submitting={acceptForm.processing} class="w-full" color="primary">
                    Accept Invitation
                </SubmitButton>
            </Form>
            <Form
                form={declineForm}
                action={HouseholdInvitationsController.decline.url({ token: invitation.token })}>
                <Button
                    color="light"
                    variant="ghost"
                    class="w-full btn-sm"
                    disabled={declineForm.processing}>
                    Decline
                </Button>
            </Form>
        </div>
    </Card>
</div>
```

---

## Task 10: Theme Settings Page

- [ ] **Create `resources/js/pages/settings/theme.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { page, router } from '@inertiajs/svelte';
    import { UserThemeController } from '@wayfinder/App/Http/Controllers/UserThemeController';

    import Badge from '@components/ui/badge.svelte';
    import Card from '@components/ui/card.svelte';

    const daisyThemes = [
        'light',
        'dark',
        'cupcake',
        'bumblebee',
        'emerald',
        'corporate',
        'synthwave',
        'retro',
        'cyberpunk',
        'valentine',
        'halloween',
        'forest',
        'aqua',
        'lofi',
        'pastel',
        'fantasy',
        'black',
        'luxury',
        'dracula',
        'business',
        'night',
        'coffee',
        'winter',
        'dim',
        'nord',
        'sunset',
    ];

    const currentTheme = $derived(
        (page.props.auth?.user as App.Models.User | null)?.theme_preference ?? 'light'
    );

    function selectTheme(theme: string) {
        document.documentElement.dataset.theme = theme;
        router.put(
            UserThemeController.update.url(),
            { theme },
            {
                preserveScroll: true,
                preserveState: true,
            }
        );
    }
</script>

<div class="p-4">
    <h1 class="mb-4 text-xl font-bold">Theme</h1>

    <div class="grid grid-cols-2 gap-3">
        {#each daisyThemes as theme (theme)}
            <button
                data-theme={theme}
                class="text-left transition-all"
                onclick={() => selectTheme(theme)}>
                <Card
                    wrapperClass="border-2 {currentTheme === theme
                        ? 'border-primary'
                        : 'border-base-300'}">
                    <div class="mb-2 flex gap-1">
                        <span class="h-3 w-3 rounded-full bg-primary"></span>
                        <span class="h-3 w-3 rounded-full bg-secondary"></span>
                        <span class="h-3 w-3 rounded-full bg-accent"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium capitalize">{theme}</p>
                        {#if currentTheme === theme}
                            <Badge color="primary" variant="soft">Active</Badge>
                        {/if}
                    </div>
                </Card>
            </button>
        {/each}
    </div>
</div>
```

---

## Task 11: Frontend Formatting

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

Expected: No type errors. If Wayfinder types are missing, re-run `php artisan wayfinder:generate`.

---

## Task 12: Commit

- [ ] **Stage all new and modified frontend files**

```bash
git add resources/js/schema/ resources/js/hooks/ resources/js/components/module/ resources/js/components/layouts/app-layout.svelte resources/js/components/navigation/bottom-nav.svelte resources/js/app.ts resources/js/pages/accounts/ resources/js/pages/categories/ resources/js/pages/household/ resources/js/pages/settings/ resources/js/wayfinder/
```

- [ ] **Commit**

```bash
git commit -m "$(cat <<'EOF'
feat(foundation): add app layout, enum badges, and all Foundation pages

Mobile-first shell with bottom nav and theme hook. Enum badge components
use existing Badge with Wayfinder constants. Forms use FormGenerator +
FormAction (no raw FormField/Input). Confirmations use ConfirmationModal.
DataList for detail views. No magic strings or browser confirm().

Co-Authored-By: Claude Sonnet 4.6 (1M context) <noreply@anthropic.com>
EOF
)"
```
