<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { useForm } from '@inertiajs/svelte';
    import TransactionType from '@wayfinder/App/Enums/TransactionType';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';
    import TransferController from '@wayfinder/App/Http/Controllers/TransferController';

    import Formatter from '@utilities/formatter';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import BottomActionBar from '@components/navigation/bottom-action-bar.svelte';
    import TabsList from '@components/ui/atoms/tabs/tabs-list.svelte';
    import TabsTrigger from '@components/ui/atoms/tabs/tabs-trigger.svelte';
    import Tabs from '@components/ui/atoms/tabs/tabs.svelte';
    import Button from '@components/ui/button.svelte';
    import Drawer from '@components/ui/drawer.svelte';
    import AccountSelect from '@components/ui/forms/account-select.svelte';
    import CalculatorInput from '@components/ui/forms/calculator-input.svelte';
    import CategorySelect from '@components/ui/forms/category-select.svelte';
    import CurrencyInput from '@components/ui/forms/currency-input.svelte';
    import DateInput from '@components/ui/forms/date-input.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import Form from '@components/ui/forms/form.svelte';
    import MaskedInput from '@components/ui/forms/masked-input.svelte';
    import SubmitButton from '@components/ui/forms/submit-button.svelte';
    import Textarea from '@components/ui/forms/textarea.svelte';

    /** Common quick-entry amounts (IDR) when the parent supplies none. */
    const DEFAULT_AMOUNT_PRESETS: number[] = [10_000, 25_000, 50_000, 100_000];

    interface Props {
        accounts: App.Models.Account[];
        categories: App.Models.Category[];
        /** Quick amounts rendered as chips under the hero input. */
        amountPresets?: number[];
    }

    let { accounts, categories, amountPresets = DEFAULT_AMOUNT_PRESETS }: Props = $props();

    let activeType = $state<App.Enums.TransactionType>(TransactionType.Expense);
    let calculatorOpen = $state(false);
    let calculatorValue = $state(0);

    /* Guard instead of debounce: after the drawer closes, ignore open taps for a beat so the dismissal click (overlay / swipe) cannot immediately re-open it. Opening itself stays instant. */
    const CALCULATOR_REOPEN_GUARD_MS = 300;
    let calculatorWasOpen = false;
    let calculatorGuardUntil = 0;

    $effect(() => {
        const isOpen = calculatorOpen;

        if (calculatorWasOpen && !isOpen) {
            calculatorGuardUntil = Date.now() + CALCULATOR_REOPEN_GUARD_MS;
        }

        calculatorWasOpen = isOpen;
    });

    function openCalculator(): void {
        if (Date.now() < calculatorGuardUntil) return;

        calculatorValue = Number(form.amount) || 0;
        calculatorOpen = true;
    }

    const isTransfer = $derived(activeType === TransactionType.Transfer);

    const typeConfig = $derived.by(() => {
        switch (activeType) {
            case TransactionType.Income:
                return {
                    submitLabel: 'Add Income',
                };
            case TransactionType.Transfer:
                return {
                    submitLabel: 'Add Transfer',
                };
            default:
                return {
                    submitLabel: 'Add Expense',
                };
        }
    });

    const form = useForm({
        type: TransactionType.Expense,
        amount: '',
        description: '',
        account_id: '',
        destination_account_id: '',
        fee_amount: '',
        category_id: '',
        transaction_date: new Date(),
    });

    /* The destination depends on a valid source: clearing the source, or moving it onto the current destination, resets the destination before a stale value can silently trip the backend's `different:account_id` rule. */
    $effect(() => {
        if (
            !form.account_id ||
            (form.destination_account_id && form.destination_account_id === form.account_id)
        ) {
            form.destination_account_id = '';
        }
    });

    function swapTransferAccounts(): void {
        if (!form.account_id || !form.destination_account_id) return;

        const source = form.account_id;

        form.account_id = form.destination_account_id;
        form.destination_account_id = source;
    }

    /* A transfer's destination never offers the source account. */
    const destinationAccounts = $derived(
        form.account_id
            ? accounts.filter((account) => String(account.id) !== String(form.account_id))
            : accounts
    );

    $effect(() => {
        if (calculatorOpen) form.amount = calculatorValue.toString();
    });

    form.transform((data) => {
        const payload: Record<string, unknown> = { ...data };

        if (activeType === TransactionType.Transfer) {
            delete payload.type;
            delete payload.category_id;
        } else {
            delete payload.destination_account_id;
            delete payload.fee_amount;
            payload.type = activeType;
        }

        return payload;
    });

    const formAction = $derived(
        isTransfer ? TransferController.store : TransactionController.store
    );
</script>

<MobilePageLayout heroClass="flex flex-col justify-center" variant="4/5">
    {#snippet hero()}
        <div class="w-full px-5">
            <div class="text-center">
                <div class="mt-1 flex items-center justify-center gap-1.5">
                    <span class="text-lg font-medium text-secondary-content/50">Rp</span>
                    <MaskedInput
                        class={[
                            'field-sizing-content min-w-0 border-none bg-transparent text-center leading-none font-medium tracking-tight text-secondary-content tabular-nums transition-all outline-none',
                            form?.amount?.toString().length > 10
                                ? 'text-[--spacing(10)]'
                                : 'text-5xl',
                        ]}
                        inputmode="numeric"
                        maskPreset="currency"
                        onclick={openCalculator}
                        placeholder="10.000"
                        readonly
                        bind:value={form.amount} />
                </div>
                {#if form.errors.amount?.length}
                    <span class="text-xs text-error">{form.errors.amount}</span>
                {/if}
            </div>

            {#if amountPresets.length}
                <div class="mt-3 flex flex-wrap items-center justify-center gap-1.5">
                    {#each amountPresets as preset (preset)}
                        {const selected = $derived(parseInt(form.amount) === preset)}

                        <button
                            class="rounded-full border px-3 py-1.5 text-sm font-medium transition-colors {selected
                                ? 'border-secondary-content/60 bg-secondary-content/15 text-secondary-content'
                                : 'border-secondary-content/25 text-secondary-content/60 hover:border-secondary-content/45 hover:text-secondary-content'}"
                            aria-pressed={selected}
                            onclick={() => (form.amount = preset.toString())}
                            type="button">
                            {Formatter.currency(preset, true)}
                        </button>
                    {/each}
                </div>
            {/if}
        </div>
    {/snippet}

    <Form id="transaction-form" {...formAction.form()} {form}>
        <Tabs onchange={() => form.resetAndClearErrors()} bind:value={activeType}>
            <TabsList class="w-full bg-secondary">
                <TabsTrigger
                    class="data-[state=active]:text-secondary data-[state=inactive]:text-secondary-content"
                    value={TransactionType.Income}>Income</TabsTrigger>
                <TabsTrigger
                    class="data-[state=active]:text-secondary data-[state=inactive]:text-secondary-content"
                    value={TransactionType.Expense}>Expense</TabsTrigger>
                <TabsTrigger
                    class="data-[state=active]:text-secondary data-[state=inactive]:text-secondary-content"
                    value={TransactionType.Transfer}>Transfer</TabsTrigger>
            </TabsList>
        </Tabs>

        <div class="mt-5 space-y-4">
            <Field
                titleClass="uppercase text-base-content/50 tracking-wide font-bold text-xs"
                error={form.errors.account_id}
                required
                title="Account">
                <AccountSelect
                    {accounts}
                    placeholder="Select Account"
                    required
                    bind:value={form.account_id} />
            </Field>

            <!-- 2 · Destination — transfer only -->
            {#if isTransfer}
                <div class="flex justify-center">
                    <button
                        class="flex items-center gap-1.5 rounded-full border border-base-content/15 px-3 py-1 text-xs font-medium text-base-content/50 transition-colors hover:border-base-content/30 hover:text-base-content disabled:cursor-not-allowed disabled:opacity-40"
                        disabled={!form.account_id || !form.destination_account_id}
                        onclick={swapTransferAccounts}
                        type="button">
                        <i class="iconify size-4 solar--transfer-vertical-line-duotone"></i>
                        Switch
                    </button>
                </div>

                <Field
                    titleClass="uppercase text-base-content/50 tracking-wide font-bold text-xs"
                    error={form.errors.destination_account_id}
                    required
                    title="Destination Account">
                    <AccountSelect
                        accounts={destinationAccounts}
                        disabled={!form.account_id}
                        placeholder="Select Destination Account"
                        required
                        bind:value={form.destination_account_id} />
                </Field>
            {:else}
                <!-- 3 · Category — income / expense -->
                <Field
                    titleClass="uppercase text-base-content/50 tracking-wide font-bold text-xs"
                    error={form.errors.category_id}
                    required
                    title="Category">
                    <CategorySelect
                        {categories}
                        groupVariant="text"
                        optionVariant="icon"
                        placeholder="Select category"
                        required
                        variant="modal"
                        bind:value={form.category_id} />
                </Field>
            {/if}

            <!-- 4 · Date -->
            <Field
                id="transaction-date"
                titleClass="uppercase text-base-content/50 tracking-wide font-bold text-xs"
                error={form.errors.transaction_date}
                required
                title="Date">
                <DateInput
                    id="transaction-date"
                    placeholder="Pick a date"
                    bind:value={form.transaction_date} />
            </Field>

            <!-- 5 · Fee — transfer only -->
            {#if isTransfer}
                <Field
                    id="transfer-fee"
                    titleClass="uppercase text-base-content/50 tracking-wide font-bold text-xs"
                    error={form.errors.fee_amount}
                    title="Transfer fee">
                    <CurrencyInput
                        id="transfer-fee"
                        inputmode="numeric"
                        placeholder="0"
                        bind:value={form.fee_amount} />
                </Field>
            {/if}

            <!-- 6 · Notes -->
            <Field
                id="transaction-notes"
                titleClass="uppercase text-base-content/50 tracking-wide font-bold text-xs"
                error={form.errors.description}
                title="Notes">
                <Textarea
                    id="transaction-notes"
                    class="min-h-24"
                    placeholder={isTransfer ? 'e.g. Monthly allowance' : 'e.g. Warteg Bu Sri'}
                    bind:value={form.description} />
            </Field>
        </div>
    </Form>

    <BottomActionBar>
        <div class="flex gap-3">
            <Button class="btn-square" color="secondary" onclick={openCalculator} variant="outline">
                <i class="iconify solar--calculator-minimalistic-bold-duotone"></i>
            </Button>

            <SubmitButton class="grow" form="transaction-form" submitting={form.processing}>
                {typeConfig.submitLabel}
            </SubmitButton>
        </div>
    </BottomActionBar>
</MobilePageLayout>

<Drawer overlay={false} bind:open={calculatorOpen}>
    <div class="p-5">
        {#key calculatorOpen}
            <CalculatorInput
                mode="chain"
                onConfirm={() => (calculatorOpen = false)}
                bind:value={calculatorValue} />
        {/key}
    </div>
</Drawer>
