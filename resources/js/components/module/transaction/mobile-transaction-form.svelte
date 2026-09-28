<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { useForm } from '@inertiajs/svelte';
    import TransactionType from '@wayfinder/App/Enums/TransactionType';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';
    import TransferController from '@wayfinder/App/Http/Controllers/TransferController';

    import Formatter from '@utilities/formatter';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import TabsList from '@components/ui/atoms/tabs/tabs-list.svelte';
    import TabsTrigger from '@components/ui/atoms/tabs/tabs-trigger.svelte';
    import Tabs from '@components/ui/atoms/tabs/tabs.svelte';
    import AccountSelect from '@components/ui/forms/account-select.svelte';
    import CategorySelect from '@components/ui/forms/category-select.svelte';
    import DateInput from '@components/ui/forms/date-input.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import Form from '@components/ui/forms/form.svelte';
    import MaskedInput from '@components/ui/forms/masked-input.svelte';

    interface Props {
        accounts: App.Models.Account[];
        categories: App.Models.Category[];
        onCancel?: () => void;
    }

    let { accounts, categories, onCancel }: Props = $props();

    let activeType = $state<App.Enums.TransactionType>(TransactionType.Expense);

    const isTransfer = $derived(activeType === TransactionType.Transfer);

    const typeConfig = $derived.by(() => {
        switch (activeType) {
            case TransactionType.Income:
                return {
                    accountLabel: 'To account',
                    submitLabel: 'Add Income',
                };
            case TransactionType.Transfer:
                return {
                    accountLabel: 'From account',
                    submitLabel: 'Add Transfer',
                };
            default:
                return {
                    accountLabel: 'From account',
                    submitLabel: 'Add Expense',
                };
        }
    });

    const form = useForm({
        type: TransactionType.Expense,
        amount: '' as number | '',
        description: '',
        account_id: '',
        destination_account_id: '',
        fee_amount: '' as number | '',
        category_id: '',
        transaction_date: new Date(),
    });

    /* One UI, two request contracts: strip the fields each endpoint
       prohibits, then pin the type for plain rows (the transfer endpoint
       rejects a type field outright). */
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
        isTransfer ? TransferController.store.url() : TransactionController.store.url()
    );

    function handleFeeInput(e: Event): void {
        const input = e.target as HTMLInputElement;
        const raw = input.value.replace(/\D/g, '');
        form.fee_amount = raw ? parseInt(raw, 10) || 0 : '';
    }

    const defaultCancel = () => window.history.back();
</script>

<MobilePageLayout heroClass="flex flex-col justify-center" variant="4/5">
    {#snippet hero()}
        <div class="w-full px-5">
            <div class="text-center">
                <!-- <p class="text-2xs font-bold tracking-widest text-secondary-content/50 uppercase">
                    Amount
                </p> -->
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
                        placeholder="10.000"
                        bind:value={form.amount} />
                </div>
            </div>

            <div class="mt-8">
                <label
                    class="text-2xs font-bold tracking-widest text-secondary-content/50 uppercase"
                    for="mtf-date">
                    Date
                </label>
                <div class="mt-0.5">
                    <DateInput
                        id="mtf-date"
                        class="w-full border-none bg-transparent px-0  text-center text-xl font-medium text-secondary-content/80"
                        placeholder="Pick a date"
                        bind:value={form.transaction_date} />
                </div>
            </div>
        </div>
    {/snippet}

    <Form id="mobile-transaction-form" action={formAction} {form}>
        <Tabs bind:value={activeType}>
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

        <div class="mt-4 divide-y divide-base-content/10">
            <div class="py-3">
                <span class="text-2xs font-bold tracking-widest text-base-content/40 uppercase">
                    {typeConfig.accountLabel}
                </span>
                <div class="mt-0.5">
                    <AccountSelect
                        {accounts}
                        placeholder="Select account"
                        bind:value={form.account_id} />
                </div>
            </div>

            {#if isTransfer}
                <div class="py-3">
                    <span class="text-2xs font-bold tracking-widest text-base-content/40 uppercase">
                        To account
                    </span>
                    <div class="mt-0.5">
                        <AccountSelect
                            {accounts}
                            placeholder="Select destination"
                            bind:value={form.destination_account_id} />
                    </div>
                </div>

                <div class="flex flex-col py-3">
                    <label
                        class="text-2xs font-bold tracking-widest text-base-content/40 uppercase"
                        for="mtf-fee">
                        Transfer fee (optional)
                    </label>
                    <input
                        id="mtf-fee"
                        class="input mt-0.5 w-full border-none bg-transparent px-0 font-mono text-sm font-medium placeholder:text-base-content/30"
                        inputmode="numeric"
                        oninput={handleFeeInput}
                        placeholder="0"
                        type="text"
                        value={form.fee_amount ? Formatter.currency(form.fee_amount, true) : ''} />
                </div>
            {:else}
                <div class="py-3">
                    <span class="text-2xs font-bold tracking-widest text-base-content/40 uppercase">
                        Category
                    </span>
                    <div class="mt-0.5">
                        <CategorySelect
                            {categories}
                            groupVariant="text"
                            optionVariant="icon"
                            placeholder="Select category"
                            variant="modal"
                            bind:value={form.category_id} />
                    </div>
                </div>
            {/if}

            <div class="flex flex-col py-3">
                <label
                    class="text-2xs font-bold tracking-widest text-base-content/40 uppercase"
                    for="mtf-description">
                    Description
                </label>
                <input
                    id="mtf-description"
                    class="input mt-0.5 w-full border-none bg-transparent px-0 text-sm font-medium placeholder:text-base-content/30"
                    placeholder={isTransfer ? 'e.g. Monthly allowance' : 'e.g. Warteg Bu Sri'}
                    type="text"
                    bind:value={form.description} />
            </div>
        </div>

        <FormAction
            class="mt-6 w-full [&_button]:min-w-0"
            submitClass="flex-3/5"
            {form}
            formId="mobile-transaction-form"
            labelCancel="Cancel"
            labelSubmit={typeConfig.submitLabel}
            onCancel={onCancel ?? defaultCancel} />
    </Form>
</MobilePageLayout>
