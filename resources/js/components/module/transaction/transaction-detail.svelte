<script lang="ts">
    import type { Data } from '@type/type';

    import DateTimeHelper from '@utilities/date-time-helper';

    import CurrencyAmount from '@components/data/currency-amount.svelte';
    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import AccountInfo from '@components/module/account/account-info.svelte';
    import CategoryInfo from '@components/module/category/category-info.svelte';
    import Field from '@components/ui/forms/field.svelte';

    interface Props {
        transaction: Data.TransactionDetailData;
    }

    let { transaction }: Props = $props();

    const isInflow = $derived(transaction.flow === 'inflow');
    const isTransfer = $derived(transaction.type === 'transfer');

    /* The DTO already folds the counterpart account — which side this row
       sits on decides which of the two is source and which is destination. */
    const sourceAccount = $derived(
        isTransfer && isInflow ? transaction.destination_account : transaction.account
    );
    const destinationAccount = $derived(
        isTransfer && !isInflow ? transaction.destination_account : transaction.account
    );

    /* Contextual page title — it replaces the type badge and carries the type
       color, since the hero amount stays neutral. */
    const titleConfig = $derived.by(() => {
        switch (transaction.type) {
            case 'income':
                return { label: 'Income Detail', color: 'text-success' };
            case 'transfer':
                return { label: 'Transfer Detail', color: 'text-info' };
            default:
                return { label: 'Expense Detail', color: 'text-error' };
        }
    });

    const amountClass = $derived(
        transaction.amount.toString().length > 10 ? 'text-[--spacing(10)]' : 'text-5xl'
    );
</script>

<MobilePageLayout heroClass="flex flex-col justify-center" variant="4/5">
    {#snippet hero()}
        <div class="w-full px-5">
            <div class="text-center">
                <CurrencyAmount
                    value={transaction.amount}
                    class="mt-1 font-medium tracking-tight text-secondary-content tabular-nums {amountClass}"
                    symbolClass="text-lg text-secondary-content/50"
                    amountClass="leading-none" />
            </div>
        </div>
    {/snippet}

    <h2 class="text-center text-lg font-semibold tracking-tight {titleConfig.color}">
        {titleConfig.label}
    </h2>

    <div class="mt-5 space-y-4">
        <!-- 1 · Source account (the row's own side for outflows / plain rows) -->
        <Field
            titleClass="uppercase text-base-content/50 tracking-wide font-bold text-xs"
            title="Account">
            <AccountInfo account={sourceAccount} />
        </Field>

        <!-- 2 · Destination — transfer only, mirroring the form's switch row -->
        {#if isTransfer}
            <div class="flex justify-center">
                <div
                    class="flex size-8 items-center justify-center rounded-full border border-base-content/15 text-base-content/50">
                    <i class="iconify size-4 solar--transfer-vertical-line-duotone"></i>
                </div>
            </div>

            <Field
                titleClass="uppercase text-base-content/50 tracking-wide font-bold text-xs"
                title="Destination Account">
                <AccountInfo account={destinationAccount} />
            </Field>
        {:else}
            <!-- 3 · Category — income / expense -->
            <Field
                titleClass="uppercase text-base-content/50 tracking-wide font-bold text-xs"
                title="Category">
                <CategoryInfo category={transaction.category} />
            </Field>
        {/if}

        <!-- 4 · Date -->
        <Field
            titleClass="uppercase text-base-content/50 tracking-wide font-bold text-xs"
            title="Date">
            <p
                class="flex min-h-12 items-center rounded-lg px-4 text-sm font-medium transition-colors">
                {DateTimeHelper.format(transaction.transaction_date, 'date')}
            </p>
        </Field>

        <!-- 5 · Notes -->
        {#if transaction.description}
            <Field
                titleClass="uppercase text-base-content/50 tracking-wide font-bold text-xs"
                title="Notes">
                <p class="min-h-12 px-4 py-2 text-sm leading-relaxed font-medium text-pretty">
                    {transaction.description}
                </p>
            </Field>
        {/if}
    </div>
</MobilePageLayout>
