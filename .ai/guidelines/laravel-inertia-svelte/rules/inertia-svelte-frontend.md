# Inertia + Svelte Frontend Rules

## Svelte 5 Runes

Always use Svelte 5 rune syntax. Never use the legacy Options API (`export let`, `$:`, reactive statements).

```svelte
<script lang="ts">
    // ✅ runes
    let { account, providers } = $props();
    let showConfirm = $state(false);
    const isEdit = $derived(!!account);
    $effect(() => { document.title = account.name; });

    // ❌ legacy Options API
    export let account;
    $: isEdit = !!account;
</script>
```

## File & Directory Naming

All `.svelte` and `.ts` files and their containing directories must use `kebab-case`.

- `account-form.svelte` ✅ — `AccountForm.svelte` ❌
- `use-theme.svelte.ts` ✅ — `useTheme.ts` ❌
- `components/account-list/` ✅ — `components/AccountList/` ❌

PascalCase is reserved for component names *inside* files only (`<AccountForm />`).

Files that use Svelte runes outside a `.svelte` file must end in `.svelte.ts`.

## Directory Conventions

| Purpose | Location |
|---------|----------|
| Inertia page components | `resources/js/pages/` |
| Reusable UI primitives | `resources/js/components/ui/` |
| Feature module components | `resources/js/components/module/{module}/` |
| Layouts | `resources/js/components/layouts/` |
| Navigation | `resources/js/components/navigation/` |
| Data display | `resources/js/components/data/` |
| Svelte hooks | `resources/js/hooks/` |
| DataComposer schemas | `resources/js/schema/` |
| Wayfinder generated files | `resources/js/wayfinder/` |

## Module Components

Feature-specific components live in `resources/js/components/module/{module}/`. Each module directory groups all domain-specific UI: badge variants, forms, and any other domain UI.

Naming: `{module}-{purpose}.svelte`

```
components/module/
  account/
    account-type-badge.svelte       ← badge for AccountType enum
    account-access-type-badge.svelte
    account-form.svelte             ← create/edit form extracted from pages
  category/
    category-form.svelte
  household/
    household-member-role-badge.svelte
    household-form.svelte
    household-invite-form.svelte
  provider/
    provider-type-badge.svelte
```

Pages import module components instead of inlining logic:

```svelte
<!-- accounts/create.svelte -->
<AccountForm {providers} {household_id} />

<!-- accounts/edit.svelte -->
<AccountForm {providers} {account} onCancel={() => router.visit(...)} />
```

## Enum Badge Components

Every PHP-backed enum must have a badge component in `components/module/{module}/`. Badges wrap the existing `Badge` component (`@components/ui/badge.svelte`) with a config map keyed by Wayfinder enum constants.

```svelte
<!-- module/account/account-type-badge.svelte -->
<script lang="ts">
    import AccountType from '@wayfinder/App/Enums/AccountType';
    import type { App } from '@wayfinder/types';
    import type { ColorVariant } from '@/data/theme';
    import Badge from '@components/ui/badge.svelte';

    let { type }: { type: App.Enums.AccountType } = $props();

    const config: Record<App.Enums.AccountType, { label: string; color: ColorVariant }> = {
        [AccountType.DebitAccount]: { label: 'Debit',       color: 'primary'   },
        [AccountType.CreditCard]:   { label: 'Credit Card', color: 'warning'   },
        [AccountType.CashWallet]:   { label: 'Cash',        color: 'success'   },
        [AccountType.EWallet]:      { label: 'E-Wallet',    color: 'info'      },
        [AccountType.Investment]:   { label: 'Investment',  color: 'secondary' },
    };

    const badge = $derived(config[type]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
```

The config map uses Wayfinder constants as keys — TypeScript will error if an enum value changes without updating the map.

## Data Schemas (DataComposer)

One schema file per model in `resources/js/schema/`, named `{model}.schema.ts`. Schemas use the in-house `DataComposer` system (`@utilities/data-composer`). A single `DataSchema` drives form fields, display values, and table columns from one definition.

```typescript
// schema/account.schema.ts
import type { DataSchema } from '@utilities/data-composer';
import type { App } from '@wayfinder/types';
import AccountType from '@wayfinder/App/Enums/AccountType';

export const accountSchema: DataSchema<App.Models.Account> = {
    name: {
        label: 'Name',
        table: true,
        form: () => ({
            type: 'text',
            name: 'name',
            required: true,
            inputProps: { placeholder: 'e.g. BCA Savings', autocorrect: 'off' },
        }),
    },
    initial_balance: {
        label: 'Initial Balance',
        value: (data) => Number(data.initial_balance).toLocaleString('id-ID'), // display formatter
        form: () => ({
            type: 'number',
            name: 'initial_balance',
            inputProps: { inputmode: 'decimal', min: 0 },
        }),
    },
    credit_card_limit: {
        label: 'Credit Limit',
        show: (data) => data.type === AccountType.CreditCard, // conditional display
        form: () => ({
            type: 'number',
            name: 'credit_card_limit',
            show: (form: any) => form.type === AccountType.CreditCard, // conditional form field
            inputProps: { inputmode: 'decimal', min: 0 },
        }),
    },
};
```

Dynamic options (e.g. providers list from page props) are not in the static schema — extend in the component via `extendSchema()`.

### DataComposer usage patterns

```svelte
<script lang="ts">
    import { DataComposer } from '@utilities/data-composer';
    import { accountSchema } from '@schema/account.schema';

    // Form: extend with dynamic options, inject hidden values
    const { fields, data } = DataComposer.from(accountSchema)
        .extendSchema({
            provider_id: {
                label: 'Provider',
                form: () => ({ type: 'select', name: 'provider_id', options: providerOptions }),
            },
        })
        .toFormGenerator({ type: AccountType.DebitAccount, ... });

    const formSchema = { fields, data: { ...data, household_id: householdId } };

    // Display: feed DataList
    const displayData = DataComposer.from(accountSchema)
        .except(['type', 'access_type']) // shown as badges
        .toDataDisplay(account);
</script>
```

## Forms

### FormGenerator

Use `FormGenerator` (`@components/ui/forms/form-generator.svelte`) for all create/edit forms. It wraps `Form` + `FieldInput` + `FormAction` from the existing component library.

```svelte
<FormGenerator
    id="account-form"
    bind:form
    {formSchema}
    action={AccountsController.store.url()}
    withoutSubmit
/>
<FormAction {form} formId="account-form" labelSubmit="Create Account" withoutCancel />
```

Key patterns:
- `withoutSubmit` + external `FormAction` with `formId` lets the button live outside the `<form>` tag
- `data` keys not in `fields` are still submitted (use for hidden values like `household_id`)
- `show: (form) => bool` in a field definition makes it conditionally visible

### Form component

Use `Form` (`@components/ui/forms/form.svelte`) for simple single-action forms (e.g. accept/decline) that don't warrant `FormGenerator`.

```svelte
<Form form={acceptForm} action={HouseholdInvitationsController.accept.url({ token })}>
    <SubmitButton submitting={acceptForm.processing}>Accept</SubmitButton>
</Form>
```

### Typing forms with Wayfinder

Type `useForm` with the generated Form Request type:

```typescript
const form = useForm<App.Http.Controllers.AccountsController.Store.Request>({ ... });
```

### Confirmation dialogs

Replace all browser `confirm()` calls with `ConfirmationModal` (`@components/ui/modals/confirmation-modal.svelte`):

```svelte
let showDeleteConfirm = $state(false);

<ConfirmationModal
    bind:open={showDeleteConfirm}
    title="Delete Account"
    confirmText="Delete"
    cancelText="Cancel"
    onConfirm={destroy}
    confirmButtonProps={{ color: 'error' }}
>
    This cannot be undone.
</ConfirmationModal>

<Button color="error" variant="outline" onclick={() => (showDeleteConfirm = true)}>
    Delete
</Button>
```

## Wayfinder

All generated files live under `resources/js/wayfinder/` (alias `@wayfinder`). Run `php artisan wayfinder:generate` after any backend change. **Never hardcode URLs.**

### Imports

```typescript
// Controller actions (PHP namespace path)
import { AccountsController } from '@wayfinder/App/Http/Controllers/AccountsController';

// Named routes
import accounts from '@wayfinder/routes/accounts';

// Enum constants (runtime comparisons, badge config maps)
import AccountType from '@wayfinder/App/Enums/AccountType';

// All types
import type { App } from '@wayfinder/types';
```

### URL generation

```typescript
AccountsController.index.url()                            // '/accounts'
AccountsController.show.url({ account: 1 })               // '/accounts/1'
AccountsController.index.url({ query: { page: 2 } })      // '/accounts?page=2'
```

### With Inertia — always use `.url()`

```typescript
// useForm
form.post(AccountsController.store.url());
form.put(AccountsController.update.url({ account: id }));
form.delete(AccountsController.destroy.url({ account: id }));

// router for non-form actions
router.post(AccountsController.archive.url({ account: id }));
router.visit(AccountsController.show.url({ account: id }));
```

### `.form()` — native HTML forms only

```typescript
// Produces { action: '/accounts/1?_method=PUT', method: 'post' }
// Only use when spreading onto a native <form> element — NOT with Inertia useForm
AccountsController.update.form({ account: 1 })
```

### Enum constants — no magic strings

```typescript
// ✅ correct
if (account.type === AccountType.CreditCard) { ... }
const form = useForm({ type: AccountType.DebitAccount });

// ❌ wrong
if (account.type === 'credit_card') { ... }
```

## Hooks

Hooks live in `resources/js/hooks/` (alias `@hooks`). Files that use Svelte runes must end in `.svelte.ts`.

```typescript
// hooks/use-theme.svelte.ts
import { page } from '@inertiajs/svelte';
import type { App } from '@wayfinder/types';

export function useTheme() {
    const current = $derived(
        (page.props.auth?.user as App.Models.User | null)?.theme_preference ?? 'light'
    );

    $effect(() => {
        document.documentElement.dataset.theme = current;
    });

    return { get current() { return current; } };
}
```

## Components Over Raw HTML

Use existing components instead of raw HTML/CSS wherever possible:

| Instead of | Use |
|-----------|-----|
| `<button class="btn btn-primary">` | `<Button color="primary">` |
| `<a href>` / `<Link>` | `<Button href="...">` |
| `<div class="card">` | `<Card title="...">` |
| `<fieldset>` + raw `<input>` | `<FormField label errors>` + `<Input>` |
| `<span class="badge">` | `<Badge color="...">` or module badge component |
| Manual key-value rows | `<DataList data={DataComposer.toDataDisplay(record)}>` |
| `browser confirm()` | `<ConfirmationModal bind:open onConfirm>` |

## Layout Assignment

Layouts are assigned in `resources/js/app.ts` via the `layout()` function by page name prefix:

```typescript
layout: (name) => {
    switch (true) {
        case name.startsWith('accounts'):
        case name.startsWith('categories'):
        case name.startsWith('household'):
        case name.startsWith('settings/theme'):
            return AppLayout;     // mobile bottom-nav layout
        case name.startsWith('dashboard'):
            return DashboardLayout;
        default:
            return null;          // auth pages use no layout
    }
},
```
