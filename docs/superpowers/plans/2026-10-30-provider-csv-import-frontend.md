# Provider Transaction Import — Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use better-executing-plans to implement this plan task-by-task — or better-parallel-subagents-executions if this plan splits into independent slices, or superpowers:subagent-driven-development if workflow mode is `remote`. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The Inertia/Svelte surface for statement imports — an upload drawer on the account page, a settings-level imports history, and a review surface with full row editing (transaction type, house `CategorySelect`, transfer marking), and bulk approve/discard.

**Architecture:** Upload lives in a `DrawerModal` opened from an Import card on the account page (the account's provider decides parser support via the `import_supported` prop) — there is no dedicated upload page. Review lives at `app/settings/imports/review` — a summary bar over a row table built on the house table atoms, an edit modal powered by `FormGenerator` + a DataComposer schema (categories via the house `CategorySelect`, which self-serves from the shared `static.categories` prop) inside the house `Modal` primitive, and `ConfirmationModal` for discard; it doubles as the read-only detail behind the settings imports history. All mutations hit the staging plan's routes through Wayfinder controller imports. Spec: `docs/superpowers/specs/2026-10-30-provider-csv-import-design.md`.

**Tech Stack:** Svelte 5 runes, Inertia v3, Tailwind CSS v4 (DaisyUI + shadcn-style atoms), Wayfinder-generated types, DataComposer.

**Workflow Mode:** `direct` — no automated verify/commit steps. The human reviewer handles builds, tests, and commits as they choose. (Project rules still apply to the executor while implementing: run the svelte MCP autofixer after every `.svelte` edit; if a frontend change doesn't show in the UI, run `pnpm run build`.)

**Complexity:** Medium (all steps concrete code; a handful of verify-against-siblings checks where UI primitives are involved)

**PREREQUISITE (satisfied):** the staging plan (`2026-10-30-provider-csv-import-staging.md`) is fully implemented, ending with its Step "Regenerate Wayfinder types" — this plan consumes `@wayfinder/App/Http/Controllers/ImportController`, `App.Models.ImportBatch`, `App.Models.ImportRow`, and the staging plan's page props (built on the parsing layer, `2026-11-04-provider-csv-import-parsing-excel.md`). **Deltas applied during execution** (this plan predates the parsing-layer refactor): XLSX-only surface (`accept=".xlsx"`, copy updated), `DataComposer`/`DataSchema` import from `@lib/data-composer` (not `@utilities/`), model/DTO types from `@wayfinder/types` + `@type/generated` (no `App.Data.*` in Wayfinder types), the row editor uses the house `Modal` primitive instead of manual overlay markup, the review table is built on the house table atoms, and `DateTimeHelper.format(…, 'datetime')` on the review table.

## Global Constraints

- Svelte 5 runes only (`$props`, `$state`, `$derived`, `$effect`) — never legacy `export let` / `$:`.
- kebab-case file and directory names; pages in `resources/js/pages/app/...`, module components in `resources/js/components/module/import/`, schemas in `resources/js/schema/`.
- Never hardcode URLs — import Wayfinder controllers (e.g. `import ImportController from '@wayfinder/App/Http/Controllers/ImportController';`) and use `.url({ ... })`.
- Typed `$props()` destructuring with `App.*` types from `@wayfinder/types` and DTO types from `@type/generated` — never hand-declare model interfaces.
- Components over raw HTML: `Button`, `Badge`, `FileInput`, `FormGenerator`, `FormAction`, `ConfirmationModal`, `Modal`, `DrawerModal`, `CategorySelect`, and the `@components/ui/atoms/table/*` atoms. No `browser confirm()`.
- Icons: `iconify` class with `solar--` prefix, `-bold-duotone` default weight / `-line-duotone` outline.
- No `app.ts` layout change: verified the layout switch defaults every non-`auth` page to `DashboardLayout`, which covers `app/settings/imports/*` automatically.
- After writing or editing any `.svelte` file, run the svelte MCP autofixer and apply its findings before considering the file done.
- Users pick **transaction type** (income/expense), never raw cashflow — the staging backend maps `transaction_type` to the stored cashflow (`UpdateImportRowRequest` rejects `Transfer`).

## File Structure

- Create: `resources/js/components/module/import/import-upload-form.svelte` — drawer-based upload form (drawer on mobile, modal on desktop).
- Create: `resources/js/components/module/import/import-review-table.svelte` — staged rows table (include toggle, badges, edit trigger) built on the house table atoms.
- Create: `resources/js/components/module/import/import-row-editor-modal.svelte` — full row edit modal (hand-built fields, PATCH).
- Create: `resources/js/pages/app/settings/imports/index.svelte` — settings imports history (batch list across accounts).
- Create: `resources/js/pages/app/settings/imports/review.svelte` — staging review page (summary bar, approve, discard); read-only detail for approved/discarded batches.
- Modify: `resources/js/pages/app/account/show.svelte` — Import card opening the upload drawer.
- Modify: `resources/js/data/menu.ts` — Settings menu entry for Imports.

---

### Task 1: Upload surface — drawer form component, account entry point

**Complexity:** Medium

**Files:**
- Create: `resources/js/components/module/import/import-upload-form.svelte`
- Modify: `resources/js/pages/app/account/show.svelte`

No automated test — the project has no component test suite (Browser tests are smoke-only); the upload behavior is covered by the staging plan's feature tests (`imports.store`). The dedicated upload page (`app/account/import`) and its `imports.create` route/action were dropped during execution: the entry is a card on the account page that opens a `DrawerModal` (drawer on mobile, centered modal on desktop via `DrawerModal`).

**Interfaces:**
- Consumes: Wayfinder controller `@wayfinder/App/Http/Controllers/ImportController` (`store`), shared prop `import_supported` on `app/account/show`.
- Produces: `ImportUploadForm` component (props: `account`, bindable `open`).

- [x] **Step 1: Verify Wayfinder output exists**

`resources/js/wayfinder/App/Http/Controllers/ImportController.ts` exists (generated during the staging plan's exit gate).

- [x] **Step 2: Create the upload form as a DrawerModal component**

XLSX-only surface: the house `FileInput` with `accept=".xlsx"`. A successful store redirects to the review page (the drawer unmounts with the navigation); validation errors render inline and keep the drawer open. `resources/js/components/module/import/import-upload-form.svelte`:

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { useForm } from '@inertiajs/svelte';

    import ImportController from '@wayfinder/App/Http/Controllers/ImportController';

    import Button from '@components/ui/button.svelte';
    import FileInput from '@components/ui/forms/file-input.svelte';
    import DrawerModal from '@components/ui/drawer-modal.svelte';

    let {
        account,
        open = $bindable(false),
    }: {
        account: App.Models.Account;
        open?: boolean;
    } = $props();

    let form = useForm<{ statement: File | null }>({ statement: null });
    let files = $state<FileList | null>(null);

    function submit(): void {
        form.statement = files?.item(0) ?? null;

        form.post(ImportController.store.url({ account: account.id }), {
            preserveScroll: true,
        });
    }
</script>

<DrawerModal bind:open title="Import Statement">
    <div class="space-y-4">
        <label class="block space-y-1">
            <span class="block text-sm font-medium">Statement XLSX</span>
            <FileInput accept=".xlsx" bind:files />
        </label>

        {#if form.errors.statement}
            <p class="text-sm text-error">{form.errors.statement}</p>
        {/if}

        <Button
            class="w-full"
            color="primary"
            disabled={files === null || files.length === 0 || form.processing}
            onclick={submit}>
            {form.processing ? 'Parsing…' : 'Parse Statement'}
            <i class="iconify size-5 solar--upload-square-bold-duotone"></i>
        </Button>
    </div>
</DrawerModal>
```

- [x] **Step 3: Wire the entry card on the account page**

In `resources/js/pages/app/account/show.svelte`: add `let showImport = $state(false);`, import `ImportUploadForm`, swap the Import card's button from `href={ImportController.create.url(...)}` to `onclick={() => (showImport = true)}`, and render the form at the page root:

```svelte
<ImportUploadForm account={account} bind:open={showImport} />
```

The Import card sits inside the account page's `{#if import_supported}` block (after the transactions list, before the detail toggle's else branch).

- [x] **Step 4: Validate the Svelte files**

Svelte MCP autofixer run on all touched files. Findings: the `state_referenced_locally` warning on `pageLayoutProps({ ... account.id ... })` is the established house pattern (init-only helper, 15+ sibling pages pass prop-derived values identically) — accepted; no other findings.

---

### Task 2: Review surface — table, row editor modal, review page

**Complexity:** Medium

**Files:**
- Create: `resources/js/components/module/import/import-review-table.svelte`
- Create: `resources/js/components/module/import/import-row-editor-modal.svelte`
- Create: `resources/js/pages/app/settings/imports/review.svelte`

No automated test — same convention rationale as Task 1; the row-update/approve/discard mutations are covered by the staging plan's feature tests.

**Interfaces:**
- Consumes: page props `batch: App.Models.ImportBatch`, `rows: App.Models.ImportRow[]`, `accounts: App.Models.Account[]`; Wayfinder `ImportController`; house primitives (`Select`, `DateInput`, `CurrencyInput`, `Textarea`, `Checkbox`, `CategorySelect`, `AccountSelect`, `Field`, `Form`, `FormAction`). The row editor is **hand-built** — FormGenerator/DataComposer didn't fit its mixed field set (`CategorySelect`/`AccountSelect` are interactive components, not schema field types), so it seeds `useForm` via a `buildInitialData()` function and binds each field directly. Categories are NOT passed as props — the editor's `CategorySelect` self-serves from the shared `static.categories` Inertia prop, scoped by the picked transaction type.
- Produces: the staging review surface at route `imports.show`.

- [x] ~~**Step 1: Create the DataComposer schema**~~ — **dropped during execution**: the schema/`FormGenerator` concept didn't fit the editor's mixed field set (see Step 3), and `resources/js/schema/import-row.schema.ts` was removed. Users pick **transaction type** — Income/Expense map to the stored cashflow and clear a stale transfer destination; **Transfer** keeps the statement-derived direction, requires the Transfer Destination field, and hides Category.

Users pick **transaction type** (income/expense), never raw cashflow — the staging backend maps `transaction_type` to the stored cashflow and rejects `Transfer`. Category is NOT a schema field (the house `CategorySelect` replaces it in the editor). `resources/js/schema/import-row.schema.ts`:

```ts
import TransactionType from '@wayfinder/App/Enums/TransactionType';
import type { DataSchema } from '@lib/data-composer';
import type { App } from '@wayfinder/types';

export const importRowSchema: DataSchema<App.Models.ImportRow> = {
    transaction_date: {
        label: 'Date',
        form: () => ({
            type: 'date',
            name: 'transaction_date',
            required: true,
        }),
    },
    description: {
        label: 'Description',
        form: () => ({
            type: 'text',
            name: 'description',
            required: true,
            inputProps: { placeholder: 'e.g. Pembayaran QR ke Khas Solo 609015160267' },
        }),
    },
    amount: {
        label: 'Amount',
        form: () => ({
            type: 'number',
            name: 'amount',
            required: true,
            inputProps: { min: '0.01', step: 'any' },
        }),
    },
    transaction_type: {
        label: 'Type',
        form: () => ({
            type: 'select',
            name: 'transaction_type',
            required: true,
            options: [
                { label: 'Income', value: TransactionType.Income },
                { label: 'Expense', value: TransactionType.Expense },
                { label: 'Transfer', value: TransactionType.Transfer },
            ],
        }),
    },
    transfer_destination_account_id: {
        label: 'Transfer Destination',
        form: () => ({
            type: 'select',
            name: 'transfer_destination_account_id',
            required: true,
            show: (form) => form.transaction_type === TransactionType.Transfer,
        }),
    },
};
```

- [x] **Step 2: Create the review table**

Built on the house table atoms (`@components/ui/atoms/table/*`) — `TableRoot` owns the overflow scroll, rows dim when excluded, and the include/edit columns render only when `editable` (staging batches). Date shows the full datetime since Mandiri rows carry times. `resources/js/components/module/import/import-review-table.svelte`:

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import ImportController from '@wayfinder/App/Http/Controllers/ImportController';

    import DateTimeHelper from '@utilities/date-time-helper';

    import Badge from '@components/ui/badge.svelte';
    import Button from '@components/ui/button.svelte';
    import TableBody from '@components/ui/atoms/table/table-body.svelte';
    import TableCell from '@components/ui/atoms/table/table-cell.svelte';
    import TableHead from '@components/ui/atoms/table/table-head.svelte';
    import TableHeader from '@components/ui/atoms/table/table-header.svelte';
    import TableRoot from '@components/ui/atoms/table/table-root.svelte';
    import TableRow from '@components/ui/atoms/table/table-row.svelte';

    let {
        rows,
        batchId,
        onEdit,
        editable = true,
    }: {
        rows: App.Models.ImportRow[];
        batchId: number;
        onEdit: (row: App.Models.ImportRow) => void;
        editable?: boolean;
    } = $props();

    const currency = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    });

    function toggleInclude(row: App.Models.ImportRow): void {
        router.patch(
            ImportController.updateRow.url({ batch: batchId, row: row.id }),
            { is_included: !row.is_included },
            { preserveScroll: true, only: ['batch', 'rows'] }
        );
    }
</script>

<TableRoot>
    <TableHeader>
        <TableRow>
            {#if editable}
                <TableHead class="w-10"></TableHead>
            {/if}
            <TableHead>Date</TableHead>
            <TableHead>Description</TableHead>
            <TableHead class="text-right">Amount</TableHead>
            <TableHead>Flow</TableHead>
            <TableHead>Category</TableHead>
            <TableHead>Flags</TableHead>
            {#if editable}
                <TableHead class="w-10"></TableHead>
            {/if}
        </TableRow>
    </TableHeader>
    <TableBody>
        {#each rows as row (row.id)}
            <TableRow class={row.is_included ? '' : 'opacity-50'}>
                {#if editable}
                    <TableCell>
                        <input
                            aria-label="Include row"
                            checked={row.is_included}
                            class="checkbox checkbox-sm"
                            type="checkbox"
                            onchange={() => toggleInclude(row)} />
                    </TableCell>
                {/if}
                <TableCell>
                    {DateTimeHelper.format(row.transaction_date, 'datetime')}
                </TableCell>
                <TableCell class="max-w-48 truncate" title={row.description ?? ''}>
                    {row.description ?? '—'}
                </TableCell>
                <TableCell class="text-right font-medium">
                    {currency.format(row.amount ?? 0)}
                </TableCell>
                <TableCell>
                    <Badge color={row.flow === 'inflow' ? 'success' : 'error'} variant="soft">
                        {row.flow ?? '—'}
                    </Badge>
                </TableCell>
                <TableCell>{row.category_id ?? '—'}</TableCell>
                <TableCell>
                    <div class="flex flex-wrap gap-1">
                        {#if row.is_duplicate}
                            <Badge color="warning" variant="outline-dash">Duplicate</Badge>
                        {/if}
                        {#if row.parse_errors}
                            <Badge color="error" variant="soft">
                                {Object.values(row.parse_errors)[0] ?? 'Error'}
                            </Badge>
                        {/if}
                        {#if row.transfer_destination_account_id}
                            <Badge color="info" variant="soft">Transfer</Badge>
                        {/if}
                    </div>
                </TableCell>
                {#if editable}
                    <TableCell>
                        <Button
                            class="btn-circle btn-sm"
                            color="light"
                            onclick={() => onEdit(row)}
                            variant="ghost">
                            <i class="iconify size-4 solar--pen-new-square-line-duotone"></i>
                        </Button>
                    </TableCell>
                {/if}
            </TableRow>
        {/each}
    </TableBody>
</TableRoot>
```

- [x] **Step 3: Create the row editor modal**

House pattern applied: the generic `Modal` primitive (`@components/ui/modals/modal.svelte`); the parent guards with `{#if editingRow}` so `row` is non-null inside. Categories render via the house `CategorySelect` in a `Field`, bound straight to the Inertia form property — its catalog self-serves from the shared `static.categories` prop, scoped by the row's cashflow (inflow → income catalog, outflow → expense catalog). The `''` option clears the transfer mark — safe because Laravel's `ConvertEmptyStringsToNull` (default stack) turns it into `null`, satisfying the request's `nullable` rule. `resources/js/components/module/import/import-row-editor-modal.svelte`:

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import TransactionType from '@wayfinder/App/Enums/TransactionType';
    import ImportController from '@wayfinder/App/Http/Controllers/ImportController';

    import { importRowSchema } from '@schema/import-row.schema';
    import { DataComposer } from '@lib/data-composer';

    import CategorySelect from '@components/module/category/category-select.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import FormGenerator from '@components/ui/forms/form-generator.svelte';
    import Modal from '@components/ui/modals/modal.svelte';

    let {
        row,
        batchId,
        batchAccountId,
        accounts,
        onDone,
    }: {
        row: App.Models.ImportRow;
        batchId: number;
        batchAccountId: number;
        accounts: App.Models.Account[];
        onDone: () => void;
    } = $props();

    let open = $state(true);

    // The Inertia form instance, populated by FormGenerator via bind:form.
    let form = $state<any>(null);

    // Any modal dismissal (X button, overlay) hands control back to the parent.
    $effect(() => {
        if (!open) {
            onDone();
        }
    });

    const accountOptions = $derived([
        { label: 'Not a transfer', value: '' },
        ...accounts
            .filter((account) => account.id !== batchAccountId)
            .map((account) => ({ label: account.name, value: String(account.id) })),
    ]);

    const formSchema = $derived.by(() => {
        return DataComposer.from(importRowSchema)
            .extendSchema({
                transfer_destination_account_id: {
                    label: 'Transfer Destination',
                    form: () => ({
                        type: 'select',
                        name: 'transfer_destination_account_id',
                        options: accountOptions,
                    }),
                },
            })
            .toFormGenerator({
                transaction_date: row.transaction_date?.slice(0, 10) ?? '',
                description: row.description ?? '',
                amount: row.amount ?? 0,
                transaction_type: row.flow === 'inflow' ? TransactionType.Income : TransactionType.Expense,
                category_id: row.category_id ?? '',
                transfer_destination_account_id: row.transfer_destination_account_id
                    ? String(row.transfer_destination_account_id)
                    : '',
            });
    });
</script>

<Modal bind:open title="Edit Row #{row.row_number}">
    {#if form.transaction_type !== TransactionType.Transfer}
        <Field title="Category" required error={form.errors.category_id}>
            <CategorySelect
                bind:value={form.category_id}
                cashflow={row.flow === 'inflow' ? 'income' : 'expense'}
                variant="modal" />
        </Field>
    {/if}

    <FormGenerator
        id="import-row-form"
        action={ImportController.updateRow.url({ batch: batchId, row: row.id })}
        formSchema={formSchema}
        method="patch"
        submitOptions={{ onSuccess: onDone }}
        withoutSubmit
        bind:form />

    <div class="mt-4">
        <FormAction
            {form}
            formId="import-row-form"
            labelCancel="Close"
            labelSubmit="Save Row"
            onCancel={onDone} />
    </div>
</Modal>
```

- [x] **Step 4: Create the review page**

`resources/js/pages/app/settings/imports/review.svelte` (the editor modal is mounted under `{#if editingRow}` so the modal component owns a simple `open = true` mount state):

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';

    import { pageLayoutProps } from '@lib/navigation-helper.svelte';

    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';
    import ImportController from '@wayfinder/App/Http/Controllers/ImportController';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import ImportReviewTable from '@components/module/import/import-review-table.svelte';
    import ImportRowEditorModal from '@components/module/import/import-row-editor-modal.svelte';
    import Button from '@components/ui/button.svelte';
    import Badge from '@components/ui/badge.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    let {
        batch,
        rows,
        accounts,
    }: {
        batch: App.Models.ImportBatch;
        rows: App.Models.ImportRow[];
        accounts: App.Models.Account[];
    } = $props();

    pageLayoutProps({
        title: 'Review Import',
        backUrl: AccountController.show.url({ account: batch.account_id }),
    });

    let editingRow = $state<App.Models.ImportRow | null>(null);
    let showDiscard = $state(false);
    let approving = $state(false);

    const includedCount = $derived(rows.filter((row) => row.is_included).length);
    const duplicateCount = $derived(rows.filter((row) => row.is_duplicate && !row.is_included).length);
    const errorCount = $derived(rows.filter((row) => row.parse_errors).length);

    function approve(): void {
        approving = true;
        router.post(ImportController.approve.url({ batch: batch.id }), {}, {
            onFinish: () => (approving = false),
        });
    }

    function discard(): void {
        router.post(ImportController.discard.url({ batch: batch.id }), {
            onFinish: () => (showDiscard = false),
        });
    }
</script>

<MobilePageLayout variant="full">
    <div>
        <h2 class="text-lg font-semibold">Review Import</h2>
        <p class="text-sm text-muted-foreground">
            {batch.source_filename} · {batch.provider?.name ?? 'Statement'}
        </p>
        {#if batch.status !== 'staging'}
            <Badge
                class="mt-2 inline-block"
                color={batch.status === 'approved' ? 'success' : 'warning'}
                variant="soft">
                {batch.status}
            </Badge>
        {/if}
    </div>

    {#if batch.status === 'staging'}
        <div
            class="sticky top-0 z-10 mt-4 flex items-center justify-between gap-2 rounded-lg bg-card p-3 shadow-sm">
            <p class="text-sm">
                <span class="font-semibold">{includedCount}</span> included
                <span class="text-muted-foreground">
                    · {duplicateCount} duplicates excluded · {errorCount} errors
                </span>
            </p>
            <div class="flex gap-2">
                <Button color="error" onclick={() => (showDiscard = true)} variant="outline">
                    Discard
                </Button>
                <Button color="primary" disabled={includedCount === 0 || approving} onclick={approve}>
                    {approving ? 'Importing…' : `Import ${includedCount} rows`}
                </Button>
            </div>
        </div>
    {/if}

    <div class="mt-4">
        <ImportReviewTable
            batchId={batch.id}
            editable={batch.status === 'staging'}
            onEdit={(row) => (editingRow = row)}
            {rows} />
    </div>
</MobilePageLayout>

{#if editingRow}
    <ImportRowEditorModal
        {accounts}
        batchAccountId={batch.account_id}
        batchId={batch.id}
        onDone={() => (editingRow = null)}
        row={editingRow} />
{/if}

<ConfirmationModal
    title="Discard Import"
    confirmText="Discard"
    cancelText="Cancel"
    confirmButtonProps={{ color: 'error' }}
    onConfirm={discard}
    bind:open={showDiscard}>
    The staged rows will be discarded. No transactions will be created.
</ConfirmationModal>
```

- [x] **Step 5: Validate the Svelte files**

Svelte MCP autofixer run on every `.svelte` file. Applied: object `class` syntax on the review table rows (later superseded by the atoms refactor); removed the eslint-disable comment referencing an unconfigured rule in the editor modal. Accepted (house pattern, pre-existing): `state_referenced_locally` on `pageLayoutProps` calls (init-only helper, matches 15+ sibling pages); the `onDone()`-inside-`$effect` dismissal notification in the modal is intentional (parent nulls `editingRow`, unmounting the modal — no loop).

---

### Task 3: Settings imports history (added during execution)

**Complexity:** Low

**Files:**
- Create: `resources/js/pages/app/settings/imports/index.svelte`
- Modify: `resources/js/data/menu.ts` (Settings menu entry)

No automated test — the underlying query/endpoint is covered by the staging plan's `settings imports index lists the creator batches only` feature test.

**Interfaces:**
- Consumes: page props `batches: App.Models.ImportBatch[]` (creator-scoped, eager `account` + `provider`), Wayfinder `imports.show` for detail links, `settings.index` for back navigation.
- Produces: the settings-level batch history at route `imports.index` (URL `settings/imports`), linked from the Settings menu.

- [x] **Step 1: Add the Settings menu entry**

In `resources/js/data/menu.ts`: import the `imports` route barrel (`@wayfinder/routes/imports`) and add to `settingsMenu.menus` (after Transaction Templates):

```ts
        imports: {
            name: 'Imports',
            url: imports.index().url,
            route: 'imports.index',
            active: 'imports.*',
            icon: 'iconify solar--upload-square-line-duotone',
            type: 'menu',
        },
```

- [x] **Step 2: Create the history page**

Status badge per batch (staging/approved/discarded), account · provider, source filename, created datetime, and `imported/total` counts; each batch links to the review page (read-only detail for non-staging batches). `resources/js/pages/app/settings/imports/index.svelte`:

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { setLayoutProps } from '@inertiajs/svelte';
    import ImportController from '@wayfinder/App/Http/Controllers/ImportController';
    import settings from '@wayfinder/routes/settings';

    import DateTimeHelper from '@utilities/date-time-helper';

    import EmptyItemPlaceholder from '@components/data/empty-item-placeholder.svelte';
    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import Badge from '@components/ui/badge.svelte';
    import Link from '@components/ui/link.svelte';

    let { batches }: { batches: App.Models.ImportBatch[] } = $props();

    setLayoutProps({ title: 'Imports', backUrl: settings.index.url() });

    const statusColor: Record<string, 'info' | 'success' | 'warning'> = {
        staging: 'info',
        approved: 'success',
        discarded: 'warning',
    };
</script>

<MobilePageLayout variant="full">
    <h2 class="text-lg font-semibold">Imports</h2>

    <div class="mt-6 space-y-3">
        {#each batches as batch (batch.id)}
            <Link
                class="block rounded-xl border border-border p-4 transition-colors hover:bg-base-200"
                href={ImportController.show.url({ batch: batch.id })}>
                <div class="flex items-center justify-between gap-3">
                    <span class="min-w-0 truncate text-sm font-semibold">
                        {batch.source_filename}
                    </span>
                    <Badge color={statusColor[batch.status] ?? 'info'} variant="soft">
                        {batch.status}
                    </Badge>
                </div>

                <p class="mt-1 text-sm text-muted-foreground">
                    {batch.account?.name ?? '—'} · {batch.provider?.name ?? '—'}
                </p>

                <p class="mt-1 text-xs text-muted-foreground">
                    {DateTimeHelper.format(batch.created_at, 'datetime')}
                    · {batch.imported_rows}/{batch.total_rows} imported
                </p>
            </Link>
        {:else}
            <EmptyItemPlaceholder label="No Imports Yet" />
        {/each}
    </div>
</MobilePageLayout>
```

---

## Post-Plan Notes (not tasks)

- **XLSX-only surface** (applied, matching the parsing layer's refactor): the file input accepts `.xlsx` only, and the staging plan's `ImportUploadRequest` validates `mimes:xlsx`. There is no dedicated upload page — the entry is the account-page card + drawer.
- **Users pick transaction type, not cashflow**: the editor's Type select offers Income/Expense/Transfer (values from the Wayfinder `TransactionType` enum). Income/expense map to the stored cashflow and clear a stale transfer destination; `transfer` keeps the statement-derived direction, shows the required Transfer Destination field, and hides Category (transfers have no category at approve time). Backend mapping/enforcement lives in `UpdateImportRowRequest` + `ImportService::updateRow`.
- **Categories via shared props**: the editor's `CategorySelect` reads the grouped catalog from the shared `static.categories` Inertia prop — the review page no longer receives a `categories` prop, and `ImportController::show` no longer calls `CategoryService::getCategories()`.
- **Known v1 limitations** (from the spec): no auto transfer detection (marking is manual in the editor), no fees on imported transfers, no CSV/PDF upload.
- **Model types status quo**: Wayfinder v2 emits `App.Models.*` as `Record<string, never>` for **all** models in this app (pre-existing behavior, not import-specific) — the frontend treats model props as untyped records, consistent with the rest of the codebase.
