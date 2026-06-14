# Automation — Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. Activate `inertia-svelte-development` skill when writing any Inertia/Svelte page or component.

**Goal:** Build the Svelte 5 UI for Transaction Presets ("templates") and Recurring Presets — DataComposer schemas, enum badge components, module form components, and two Inertia pages with full create/edit/delete flows.

**Architecture:** Svelte 5 runes throughout. All form state uses `useForm` typed against Wayfinder Form Request types. Enum badge components wrap `Badge` from `@components/ui/badge.svelte` using Wayfinder-generated constants as config map keys. `DataComposer` drives every form field definition and conditional visibility (`show: (form) => ...`). `FormGenerator` + `FormAction` for all forms; `ConfirmationModal` for all destructive actions. No raw HTML, no hardcoded URLs, no magic strings.

**Tech Stack:** Svelte 5, TypeScript, Inertia.js v3, Tailwind v4, DaisyUI, Wayfinder (next), bits-ui

**Depends on:** Automation backend plan (`2026-06-14-automation-backend.md`) fully complete. Foundation and Ledger specs fully implemented.

---

## Component Reference (existing — always prefer these)

| Component | Import | Key Props |
|-----------|--------|-----------|
| `Badge` | `@components/ui/badge.svelte` | `color` (primary/secondary/accent/success/info/warning/error/light/dark), `variant` (solid/outline/soft) |
| `Button` | `@components/ui/button.svelte` | `color`, `variant` (solid/outline/ghost/soft/link), `href` (renders as `<a>`), `disabled` |
| `Card` | `@components/ui/card.svelte` | `title`, `header`, `headerAction` snippet, `footer` snippet, `wrapperClass` |
| `FormGenerator` | `@components/ui/forms/form-generator.svelte` | `formSchema: {fields, data}`, `bind:form`, `action`, `method`, `withoutSubmit` |
| `FormAction` | `@components/ui/forms/form-action.svelte` | `form`, `formId`, `labelSubmit`, `labelCancel`, `withoutCancel`, `onCancel` |
| `ConfirmationModal` | `@components/ui/modals/confirmation-modal.svelte` | `bind:open`, `title`, `onConfirm`, `loading`, `confirmText`, `cancelText`, `confirmButtonProps` |
| `DataList` | `@components/data/data-list.svelte` | `data: [{label, value}]` — feed from `DataComposer.toDataDisplay()` |
| `DataComposer` | `@utilities/data-composer` | `.from(schema)`, `.extendSchema()`, `.except()`, `.toFormGenerator()`, `.toDataDisplay()` |

### FormGenerator `show` pattern for conditional fields

```typescript
// In schema definition — field is shown/hidden based on another field's value
default_destination_account_id: {
    label: 'Destination Account',
    form: () => ({
        type: 'select',
        name: 'default_destination_account_id',
        show: (form: any) => form.type === TransactionPresetType.Transfer,
        options: accountOptions,
    }),
},
```

---

## File Map

```
resources/js/schema/
  transaction-preset.schema.ts
  recurring-preset.schema.ts

resources/js/components/module/
  transaction-preset/
    preset-type-badge.svelte
    preset-form.svelte
  recurring-preset/
    recurring-frequency-badge.svelte
    recurring-preset-form.svelte

resources/js/pages/
  transaction-presets/index.svelte
  recurring-presets/index.svelte

resources/js/app.ts              (modify: add layout cases for new page prefixes)
```

---

## Task 1: Generate Wayfinder Types

- [ ] **Run Wayfinder generation** (after automation backend is complete)

```bash
php artisan wayfinder:generate --no-interaction
```

Expected: the following files now exist in `resources/js/wayfinder/`:
- `App/Http/Controllers/TransactionPresetsController.ts`
- `App/Http/Controllers/RecurringPresetsController.ts`
- `App/Enums/TransactionPresetType.ts`
- `App/Enums/RecurringFrequency.ts`

Verify the enum files export the correct constants before writing any component.

---

## Task 2: DataComposer Schemas

One schema file per model in `resources/js/schema/`. Dynamic options (accounts list, categories list from page props) are NOT in the static schema — they are added via `DataComposer.extendSchema()` in the module form component.

### Conditional field visibility

`transaction_presets` has transfer-specific fields (`default_source_account_id`, `default_destination_account_id`, `default_transfer_fee`) that only appear when `type === 'transfer'`. These use `show: (form) => form.type === TransactionPresetType.Transfer` in the form field definition.

- [ ] **Create `resources/js/schema/transaction-preset.schema.ts`**

```typescript
import type { DataSchema } from '@utilities/data-composer';
import type { App } from '@wayfinder/types';
import TransactionPresetType from '@wayfinder/App/Enums/TransactionPresetType';

export const transactionPresetSchema: DataSchema<App.Models.TransactionPreset> = {
    name: {
        label: 'Template Name',
        table: true,
        form: () => ({
            type: 'text',
            name: 'name',
            required: true,
            inputProps: { placeholder: 'e.g. Morning Coffee', autocorrect: 'off', autocomplete: 'off' },
        }),
    },
    type: {
        label: 'Type',
        table: true,
        form: () => ({
            type: 'radio',
            name: 'type',
            required: true,
            options: [
                { value: TransactionPresetType.Income,   label: 'Income'   },
                { value: TransactionPresetType.Expense,  label: 'Expense'  },
                { value: TransactionPresetType.Transfer, label: 'Transfer' },
            ],
        }),
    },
    default_amount: {
        label: 'Default Amount',
        value: (data) =>
            data.default_amount != null
                ? Number(data.default_amount).toLocaleString('id-ID')
                : '—',
        form: () => ({
            type: 'number',
            name: 'default_amount',
            inputProps: { inputmode: 'decimal', min: 0, step: 0.01, placeholder: '0' },
        }),
    },
    default_description: {
        label: 'Default Description',
        form: () => ({
            type: 'text',
            name: 'default_description',
            inputProps: { placeholder: 'Optional note', autocorrect: 'off' },
        }),
    },
    // default_category_id, default_source_account_id, default_destination_account_id,
    // and default_transfer_fee are NOT here — options are dynamic (page props).
    // They are added via extendSchema() in preset-form.svelte.
};
```

- [ ] **Create `resources/js/schema/recurring-preset.schema.ts`**

```typescript
import type { DataSchema } from '@utilities/data-composer';
import type { App } from '@wayfinder/types';
import TransactionPresetType from '@wayfinder/App/Enums/TransactionPresetType';
import RecurringFrequency from '@wayfinder/App/Enums/RecurringFrequency';

export const recurringPresetSchema: DataSchema<App.Models.TransactionRecurringPreset> = {
    name: {
        label: 'Rule Name',
        table: true,
        form: () => ({
            type: 'text',
            name: 'name',
            required: true,
            inputProps: { placeholder: 'e.g. Monthly Rent', autocorrect: 'off', autocomplete: 'off' },
        }),
    },
    type: {
        label: 'Type',
        table: true,
        form: () => ({
            type: 'radio',
            name: 'type',
            required: true,
            options: [
                { value: TransactionPresetType.Income,  label: 'Income'  },
                { value: TransactionPresetType.Expense, label: 'Expense' },
            ],
        }),
    },
    frequency: {
        label: 'Frequency',
        table: true,
        form: () => ({
            type: 'select',
            name: 'frequency',
            required: true,
            options: [
                { value: RecurringFrequency.Daily,       label: 'Daily'       },
                { value: RecurringFrequency.Weekly,      label: 'Weekly'      },
                { value: RecurringFrequency.Fortnightly, label: 'Fortnightly' },
                { value: RecurringFrequency.Monthly,     label: 'Monthly'     },
                { value: RecurringFrequency.Yearly,      label: 'Yearly'      },
            ],
        }),
    },
    amount: {
        label: 'Amount',
        value: (data) => Number(data.amount).toLocaleString('id-ID'),
        form: () => ({
            type: 'number',
            name: 'amount',
            required: true,
            inputProps: { inputmode: 'decimal', min: 0.01, step: 0.01, placeholder: '0' },
        }),
    },
    description: {
        label: 'Description',
        form: () => ({
            type: 'text',
            name: 'description',
            inputProps: { placeholder: 'Optional note', autocorrect: 'off' },
        }),
    },
    next_run_date: {
        label: 'First Run Date',
        value: (data) => new Date(data.next_run_date).toLocaleDateString('id-ID'),
        form: () => ({
            type: 'date',
            name: 'next_run_date',
            required: true,
        }),
    },
    recurrence_end_date: {
        label: 'End Date',
        value: (data) =>
            data.recurrence_end_date
                ? new Date(data.recurrence_end_date).toLocaleDateString('id-ID')
                : 'No end date',
        form: () => ({
            type: 'date',
            name: 'recurrence_end_date',
            inputProps: { placeholder: 'Leave blank for no end' },
        }),
    },
    // account_id and category_id options are dynamic — added via extendSchema() in recurring-preset-form.svelte.
};
```

---

## Task 3: Enum Badge Components

Each badge wraps the existing `Badge` component. Config map keys use Wayfinder enum constants — TypeScript will error if an enum value is renamed without updating the map.

- [ ] **Create `resources/js/components/module/transaction-preset/preset-type-badge.svelte`**

```svelte
<script lang="ts">
    import TransactionPresetType from '@wayfinder/App/Enums/TransactionPresetType';
    import type { App } from '@wayfinder/types';
    import type { ColorVariant } from '@/data/theme';
    import Badge from '@components/ui/badge.svelte';

    let { type }: { type: App.Enums.TransactionPresetType } = $props();

    const config: Record<App.Enums.TransactionPresetType, { label: string; color: ColorVariant }> = {
        [TransactionPresetType.Income]:   { label: 'Income',   color: 'success' },
        [TransactionPresetType.Expense]:  { label: 'Expense',  color: 'error'   },
        [TransactionPresetType.Transfer]: { label: 'Transfer', color: 'info'    },
    };

    const badge = $derived(config[type]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
```

- [ ] **Create `resources/js/components/module/recurring-preset/recurring-frequency-badge.svelte`**

```svelte
<script lang="ts">
    import RecurringFrequency from '@wayfinder/App/Enums/RecurringFrequency';
    import type { App } from '@wayfinder/types';
    import type { ColorVariant } from '@/data/theme';
    import Badge from '@components/ui/badge.svelte';

    let { frequency }: { frequency: App.Enums.RecurringFrequency } = $props();

    const config: Record<App.Enums.RecurringFrequency, { label: string; color: ColorVariant }> = {
        [RecurringFrequency.Daily]:       { label: 'Daily',       color: 'warning'   },
        [RecurringFrequency.Weekly]:      { label: 'Weekly',      color: 'info'      },
        [RecurringFrequency.Fortnightly]: { label: 'Fortnightly', color: 'secondary' },
        [RecurringFrequency.Monthly]:     { label: 'Monthly',     color: 'primary'   },
        [RecurringFrequency.Yearly]:      { label: 'Yearly',      color: 'accent'    },
    };

    const badge = $derived(config[frequency]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
```

---

## Task 4: Module Form Components

Extract all form logic into module components. Pages become thin — they import the module component and pass page-prop accounts/categories as options.

### `preset-form.svelte` — transfer conditional fields

When `type === 'transfer'`, three additional fields appear via `show: (form) => ...` in the schema extension:
- `default_source_account_id`
- `default_destination_account_id`
- `default_transfer_fee`

These are added inside `extendSchema()` rather than in the static schema because their `options` come from page props.

- [ ] **Create `resources/js/components/module/transaction-preset/preset-form.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';
    import type { InertiaForm } from '@inertiajs/svelte';
    import { TransactionPresetsController } from '@wayfinder/App/Http/Controllers/TransactionPresetsController';
    import TransactionPresetType from '@wayfinder/App/Enums/TransactionPresetType';
    import { DataComposer } from '@utilities/data-composer';
    import { transactionPresetSchema } from '@schema/transaction-preset.schema';
    import Card from '@components/ui/card.svelte';
    import FormGenerator from '@components/ui/forms/form-generator.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';

    interface Props {
        accounts: App.Models.Account[];
        categories: App.Models.Category[];
        preset?: App.Models.TransactionPreset;
        onSuccess?: () => void;
        onCancel?: () => void;
    }

    let { accounts, categories, preset, onSuccess, onCancel }: Props = $props();

    let form: InertiaForm<any> = $state(null!);

    const isEdit = $derived(!!preset);

    const accountOptions = $derived(
        accounts.map((a) => ({ value: a.id, label: a.name }))
    );

    const categoryOptions = $derived([
        { value: '', label: '— None —' },
        ...categories.flatMap((parent) => [
            { value: parent.id, label: parent.name },
            ...(parent.children ?? []).map((child: App.Models.Category) => ({
                value: child.id,
                label: `  ${child.name}`,
            })),
        ]),
    ]);

    const formSchema = $derived(() => {
        const composer = DataComposer.from(transactionPresetSchema).extendSchema({
            default_category_id: {
                label: 'Default Category',
                form: () => ({
                    type: 'select',
                    name: 'default_category_id',
                    options: categoryOptions,
                }),
            },
            default_source_account_id: {
                label: 'Source Account',
                form: () => ({
                    type: 'select',
                    name: 'default_source_account_id',
                    show: (f: any) => f.type === TransactionPresetType.Transfer,
                    options: accountOptions,
                }),
            },
            default_destination_account_id: {
                label: 'Destination Account',
                form: () => ({
                    type: 'select',
                    name: 'default_destination_account_id',
                    show: (f: any) => f.type === TransactionPresetType.Transfer,
                    options: accountOptions,
                }),
            },
            default_transfer_fee: {
                label: 'Transfer Fee',
                form: () => ({
                    type: 'number',
                    name: 'default_transfer_fee',
                    show: (f: any) => f.type === TransactionPresetType.Transfer,
                    inputProps: { inputmode: 'decimal', min: 0, step: 0.01, placeholder: '0' },
                }),
            },
        });

        if (isEdit && preset) {
            return composer.toFormGenerator({
                name: preset.name,
                type: preset.type,
                default_amount: preset.default_amount != null ? Number(preset.default_amount) : null,
                default_description: preset.default_description ?? '',
                default_category_id: preset.default_category_id ?? '',
                default_source_account_id: preset.default_source_account_id ?? '',
                default_destination_account_id: preset.default_destination_account_id ?? '',
                default_transfer_fee: preset.default_transfer_fee != null ? Number(preset.default_transfer_fee) : null,
            });
        }

        return composer.toFormGenerator({
            name: '',
            type: TransactionPresetType.Expense,
            default_amount: null,
            default_description: '',
            default_category_id: '',
            default_source_account_id: '',
            default_destination_account_id: '',
            default_transfer_fee: null,
        });
    });

    const action = $derived(
        isEdit && preset
            ? TransactionPresetsController.update.url({ preset: preset.id })
            : TransactionPresetsController.store.url()
    );

    const method = $derived(isEdit ? 'put' : undefined);
    const submitLabel = $derived(isEdit ? 'Save Changes' : 'Create Template');

    const submitOptions = $derived(
        isEdit
            ? undefined
            : { onSuccess: () => { form?.reset?.(); onSuccess?.(); } }
    );
</script>

<Card>
    <FormGenerator
        id="preset-form"
        bind:form
        formSchema={formSchema()}
        {action}
        {method}
        withoutSubmit
        {submitOptions}
    />
</Card>

<div class="mt-4">
    <FormAction
        {form}
        formId="preset-form"
        labelSubmit={submitLabel}
        labelCancel="Cancel"
        onCancel={onCancel ?? (() => window.history.back())}
        withoutCancel={!onCancel && !isEdit}
    />
</div>
```

- [ ] **Create `resources/js/components/module/recurring-preset/recurring-preset-form.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';
    import type { InertiaForm } from '@inertiajs/svelte';
    import { RecurringPresetsController } from '@wayfinder/App/Http/Controllers/RecurringPresetsController';
    import TransactionPresetType from '@wayfinder/App/Enums/TransactionPresetType';
    import RecurringFrequency from '@wayfinder/App/Enums/RecurringFrequency';
    import { DataComposer } from '@utilities/data-composer';
    import { recurringPresetSchema } from '@schema/recurring-preset.schema';
    import Card from '@components/ui/card.svelte';
    import FormGenerator from '@components/ui/forms/form-generator.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';

    interface Props {
        accounts: App.Models.Account[];
        categories: App.Models.Category[];
        preset?: App.Models.TransactionRecurringPreset;
        onSuccess?: () => void;
        onCancel?: () => void;
    }

    let { accounts, categories, preset, onSuccess, onCancel }: Props = $props();

    let form: InertiaForm<any> = $state(null!);

    const isEdit = $derived(!!preset);

    const accountOptions = $derived(
        accounts.map((a) => ({ value: a.id, label: a.name }))
    );

    const categoryOptions = $derived([
        { value: '', label: '— None —' },
        ...categories.flatMap((parent) => [
            { value: parent.id, label: parent.name },
            ...(parent.children ?? []).map((child: App.Models.Category) => ({
                value: child.id,
                label: `  ${child.name}`,
            })),
        ]),
    ]);

    const formSchema = $derived(() => {
        const composer = DataComposer.from(recurringPresetSchema).extendSchema({
            account_id: {
                label: 'Account',
                form: () => ({
                    type: 'select',
                    name: 'account_id',
                    required: true,
                    options: accountOptions,
                }),
            },
            category_id: {
                label: 'Category',
                form: () => ({
                    type: 'select',
                    name: 'category_id',
                    options: categoryOptions,
                }),
            },
        });

        if (isEdit && preset) {
            return composer.toFormGenerator({
                account_id: preset.account_id,
                category_id: preset.category_id ?? '',
                name: preset.name,
                type: preset.type,
                frequency: preset.frequency,
                amount: Number(preset.amount),
                description: preset.description ?? '',
                next_run_date: preset.next_run_date,
                recurrence_end_date: preset.recurrence_end_date ?? '',
            });
        }

        return composer.toFormGenerator({
            account_id: accounts[0]?.id ?? '',
            category_id: '',
            name: '',
            type: TransactionPresetType.Expense,
            frequency: RecurringFrequency.Monthly,
            amount: 0,
            description: '',
            next_run_date: '',
            recurrence_end_date: '',
        });
    });

    const action = $derived(
        isEdit && preset
            ? RecurringPresetsController.update.url({ preset: preset.id })
            : RecurringPresetsController.store.url()
    );

    const method = $derived(isEdit ? 'put' : undefined);
    const submitLabel = $derived(isEdit ? 'Save Changes' : 'Create Rule');

    const submitOptions = $derived(
        isEdit
            ? undefined
            : { onSuccess: () => { form?.reset?.(); onSuccess?.(); } }
    );
</script>

<Card>
    <FormGenerator
        id="recurring-preset-form"
        bind:form
        formSchema={formSchema()}
        {action}
        {method}
        withoutSubmit
        {submitOptions}
    />
</Card>

<div class="mt-4">
    <FormAction
        {form}
        formId="recurring-preset-form"
        labelSubmit={submitLabel}
        labelCancel="Cancel"
        onCancel={onCancel ?? (() => window.history.back())}
        withoutCancel={!onCancel && !isEdit}
    />
</div>
```

---

## Task 5: Transaction Presets Page

The page manages the user's "templates" carousel. Create and edit happen inline via the `PresetForm` module component (toggled with a flag). Delete uses `ConfirmationModal`.

- [ ] **Create `resources/js/pages/transaction-presets/index.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';
    import { TransactionPresetsController } from '@wayfinder/App/Http/Controllers/TransactionPresetsController';
    import { router } from '@inertiajs/svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';
    import PresetTypeBadge from '@components/module/transaction-preset/preset-type-badge.svelte';
    import PresetForm from '@components/module/transaction-preset/preset-form.svelte';

    interface Props {
        presets: App.Models.TransactionPreset[];
        accounts: App.Models.Account[];
        categories: App.Models.Category[];
    }

    let { presets, accounts, categories }: Props = $props();

    let showCreateForm = $state(false);
    let editingPreset = $state<App.Models.TransactionPreset | null>(null);
    let deletingPresetId = $state<number | null>(null);

    const deletingPreset = $derived(
        deletingPresetId !== null ? presets.find((p) => p.id === deletingPresetId) : null
    );

    function destroy(): void {
        if (!deletingPresetId) { return; }
        router.delete(TransactionPresetsController.destroy.url({ preset: deletingPresetId }), {
            onFinish: () => (deletingPresetId = null),
        });
    }
</script>

<div class="p-4">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-bold">Templates</h1>
        <Button
            color="primary"
            size="sm"
            onclick={() => { showCreateForm = !showCreateForm; editingPreset = null; }}
        >
            <i class="iconify size-4 ph--plus-bold"></i>
            Add
        </Button>
    </div>

    {#if showCreateForm}
        <div class="mb-4">
            <PresetForm
                {accounts}
                {categories}
                onSuccess={() => (showCreateForm = false)}
                onCancel={() => (showCreateForm = false)}
            />
        </div>
    {/if}

    {#if editingPreset}
        <div class="mb-4">
            <div class="mb-2 flex items-center justify-between">
                <p class="text-sm font-medium text-base-content/60">Editing: {editingPreset.name}</p>
                <Button color="light" variant="ghost" size="sm" onclick={() => (editingPreset = null)}>
                    Cancel
                </Button>
            </div>
            <PresetForm
                {accounts}
                {categories}
                preset={editingPreset}
                onSuccess={() => (editingPreset = null)}
                onCancel={() => (editingPreset = null)}
            />
        </div>
    {/if}

    {#if presets.length === 0}
        <div class="flex flex-col items-center justify-center py-16 text-base-content/50">
            <i class="iconify mb-3 size-12 ph--lightning-bold"></i>
            <p class="text-sm">No templates yet</p>
            <p class="mt-1 text-xs text-base-content/40">Templates pre-fill the quick-add form</p>
            <Button
                color="primary"
                size="sm"
                class="mt-4"
                onclick={() => (showCreateForm = true)}
            >
                Create your first template
            </Button>
        </div>
    {:else}
        <div class="space-y-3">
            {#each presets as preset (preset.id)}
                <Card>
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <p class="font-semibold">{preset.name}</p>
                            <div class="flex items-center gap-1">
                                <PresetTypeBadge type={preset.type} />
                                {#if preset.default_amount != null}
                                    <span class="text-xs text-base-content/50">
                                        {Number(preset.default_amount).toLocaleString('id-ID')}
                                    </span>
                                {/if}
                            </div>
                            {#if preset.default_description}
                                <p class="text-xs text-base-content/50">{preset.default_description}</p>
                            {/if}
                        </div>
                        <div class="flex items-center gap-1">
                            <Button
                                color="light"
                                variant="ghost"
                                class="btn-circle btn-sm"
                                onclick={() => { editingPreset = preset; showCreateForm = false; }}
                            >
                                <i class="iconify size-4 ph--pencil-simple-bold"></i>
                            </Button>
                            <Button
                                color="error"
                                variant="ghost"
                                class="btn-circle btn-sm"
                                onclick={() => (deletingPresetId = preset.id)}
                            >
                                <i class="iconify size-4 ph--trash-bold"></i>
                            </Button>
                        </div>
                    </div>
                </Card>
            {/each}
        </div>
    {/if}
</div>

<ConfirmationModal
    bind:open={deletingPresetId !== null}
    title="Delete Template"
    confirmText="Delete"
    cancelText="Cancel"
    onConfirm={destroy}
    onCancel={() => (deletingPresetId = null)}
    confirmButtonProps={{ color: 'error' }}
>
    {#if deletingPreset}
        Delete <strong>{deletingPreset.name}</strong>? This cannot be undone.
    {/if}
</ConfirmationModal>
```

---

## Task 6: Recurring Presets Page

The page lists active and inactive recurring rules with next run date, frequency badge, and a toggle button. Create and edit happen inline. Delete uses `ConfirmationModal`.

- [ ] **Create `resources/js/pages/recurring-presets/index.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';
    import { RecurringPresetsController } from '@wayfinder/App/Http/Controllers/RecurringPresetsController';
    import { router } from '@inertiajs/svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import Badge from '@components/ui/badge.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';
    import PresetTypeBadge from '@components/module/transaction-preset/preset-type-badge.svelte';
    import RecurringFrequencyBadge from '@components/module/recurring-preset/recurring-frequency-badge.svelte';
    import RecurringPresetForm from '@components/module/recurring-preset/recurring-preset-form.svelte';

    interface Props {
        presets: App.Models.TransactionRecurringPreset[];
        accounts: App.Models.Account[];
        categories: App.Models.Category[];
    }

    let { presets, accounts, categories }: Props = $props();

    let showCreateForm = $state(false);
    let editingPreset = $state<App.Models.TransactionRecurringPreset | null>(null);
    let deletingPresetId = $state<number | null>(null);

    const deletingPreset = $derived(
        deletingPresetId !== null ? presets.find((p) => p.id === deletingPresetId) : null
    );

    function toggle(preset: App.Models.TransactionRecurringPreset): void {
        router.post(RecurringPresetsController.toggle.url({ preset: preset.id }), {}, {
            preserveScroll: true,
        });
    }

    function destroy(): void {
        if (!deletingPresetId) { return; }
        router.delete(RecurringPresetsController.destroy.url({ preset: deletingPresetId }), {
            onFinish: () => (deletingPresetId = null),
        });
    }

    function formatDate(dateStr: string | null): string {
        if (!dateStr) { return '—'; }
        return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    }
</script>

<div class="p-4">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-bold">Recurring Rules</h1>
        <Button
            color="primary"
            size="sm"
            onclick={() => { showCreateForm = !showCreateForm; editingPreset = null; }}
        >
            <i class="iconify size-4 ph--plus-bold"></i>
            Add
        </Button>
    </div>

    {#if showCreateForm}
        <div class="mb-4">
            <RecurringPresetForm
                {accounts}
                {categories}
                onSuccess={() => (showCreateForm = false)}
                onCancel={() => (showCreateForm = false)}
            />
        </div>
    {/if}

    {#if editingPreset}
        <div class="mb-4">
            <div class="mb-2 flex items-center justify-between">
                <p class="text-sm font-medium text-base-content/60">Editing: {editingPreset.name}</p>
                <Button color="light" variant="ghost" size="sm" onclick={() => (editingPreset = null)}>
                    Cancel
                </Button>
            </div>
            <RecurringPresetForm
                {accounts}
                {categories}
                preset={editingPreset}
                onSuccess={() => (editingPreset = null)}
                onCancel={() => (editingPreset = null)}
            />
        </div>
    {/if}

    {#if presets.length === 0}
        <div class="flex flex-col items-center justify-center py-16 text-base-content/50">
            <i class="iconify mb-3 size-12 ph--clock-countdown-bold"></i>
            <p class="text-sm">No recurring rules yet</p>
            <p class="mt-1 text-xs text-base-content/40">Set up rent, salary, and subscriptions once</p>
            <Button
                color="primary"
                size="sm"
                class="mt-4"
                onclick={() => (showCreateForm = true)}
            >
                Create your first rule
            </Button>
        </div>
    {:else}
        <div class="space-y-3">
            {#each presets as preset (preset.id)}
                <Card wrapperClass={preset.is_active ? '' : 'opacity-60'}>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex items-center gap-2">
                                <p class="truncate font-semibold">{preset.name}</p>
                                {#if !preset.is_active}
                                    <Badge color="light" variant="outline">Paused</Badge>
                                {/if}
                            </div>
                            <div class="flex flex-wrap items-center gap-1">
                                <PresetTypeBadge type={preset.type} />
                                <RecurringFrequencyBadge frequency={preset.frequency} />
                                <span class="text-xs text-base-content/50">
                                    {Number(preset.amount).toLocaleString('id-ID')}
                                </span>
                            </div>
                            <div class="flex items-center gap-3 text-xs text-base-content/50">
                                <span>Next: {formatDate(preset.next_run_date)}</span>
                                {#if preset.last_run_date}
                                    <span>Last: {formatDate(preset.last_run_date)}</span>
                                {/if}
                                {#if preset.recurrence_end_date}
                                    <span>Ends: {formatDate(preset.recurrence_end_date)}</span>
                                {/if}
                            </div>
                            {#if preset.account}
                                <p class="text-xs text-base-content/40">{preset.account.name}</p>
                            {/if}
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            <!-- Toggle active/paused -->
                            <Button
                                color={preset.is_active ? 'warning' : 'success'}
                                variant="ghost"
                                class="btn-circle btn-sm"
                                title={preset.is_active ? 'Pause' : 'Activate'}
                                onclick={() => toggle(preset)}
                            >
                                <i class="iconify size-4 {preset.is_active ? 'ph--pause-bold' : 'ph--play-bold'}"></i>
                            </Button>
                            <Button
                                color="light"
                                variant="ghost"
                                class="btn-circle btn-sm"
                                onclick={() => { editingPreset = preset; showCreateForm = false; }}
                            >
                                <i class="iconify size-4 ph--pencil-simple-bold"></i>
                            </Button>
                            <Button
                                color="error"
                                variant="ghost"
                                class="btn-circle btn-sm"
                                onclick={() => (deletingPresetId = preset.id)}
                            >
                                <i class="iconify size-4 ph--trash-bold"></i>
                            </Button>
                        </div>
                    </div>
                </Card>
            {/each}
        </div>
    {/if}
</div>

<ConfirmationModal
    bind:open={deletingPresetId !== null}
    title="Delete Recurring Rule"
    confirmText="Delete"
    cancelText="Cancel"
    onConfirm={destroy}
    onCancel={() => (deletingPresetId = null)}
    confirmButtonProps={{ color: 'error' }}
>
    {#if deletingPreset}
        Delete <strong>{deletingPreset.name}</strong>? Future transactions will no longer be generated.
    {/if}
</ConfirmationModal>
```

---

## Task 7: Update Layout Assignment in `app.ts`

The two new page prefixes (`transaction-presets`, `recurring-presets`) must be added to the `AppLayout` case in `resources/js/app.ts` so they render inside the app shell with the bottom nav.

- [ ] **Update `resources/js/app.ts` — add layout cases for new pages**

In the existing `layout()` function, add to the `AppLayout` cases:

```typescript
case name.startsWith('transaction-presets'):
case name.startsWith('recurring-presets'):
```

The final switch should include all existing cases plus these two:

```typescript
switch (true) {
    case name.startsWith('accounts'):
    case name.startsWith('categories'):
    case name.startsWith('household'):
    case name.startsWith('settings/theme'):
    case name.startsWith('transaction-presets'):
    case name.startsWith('recurring-presets'):
        return AppLayout;
    case name.startsWith('dashboard'):
        return DashboardLayout;
    default:
        return null;
}
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

Expected: No type errors. If Wayfinder types are missing for the new controllers or enums, re-run `php artisan wayfinder:generate --no-interaction`.

---

## Task 9: Commit

- [ ] **Stage all new and modified frontend files**

```bash
git add resources/js/schema/transaction-preset.schema.ts resources/js/schema/recurring-preset.schema.ts resources/js/components/module/transaction-preset/ resources/js/components/module/recurring-preset/ resources/js/pages/transaction-presets/ resources/js/pages/recurring-presets/ resources/js/app.ts resources/js/wayfinder/
```

- [ ] **Commit**

```bash
git commit -m "$(cat <<'EOF'
feat(automation): add transaction preset and recurring preset frontend

DataComposer schemas for both preset types. Enum badge components for
TransactionPresetType and RecurringFrequency using Wayfinder constants.
Module form components with conditional transfer fields via show(). Both
index pages support inline create/edit with ConfirmationModal deletes.
Recurring presets page includes toggle (pause/activate) per rule.

Co-Authored-By: Claude Sonnet 4.6 (1M context) <noreply@anthropic.com>
EOF
)"
```
