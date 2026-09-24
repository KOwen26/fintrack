<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { useForm } from '@inertiajs/svelte';
    import TransactionType from '@wayfinder/App/Enums/TransactionType';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import Formatter from '@utilities/formatter';

    import AccountSelect from '@components/ui/forms/account-select.svelte';
    import CategorySelect from '@components/ui/forms/category-select.svelte';
    import DateInput from '@components/ui/forms/date-input.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import Form from '@components/ui/forms/form.svelte';

    interface Props {
        type?: 'income' | 'expense';
        account?: App.Models.Account;
        categories: App.Models.Category[];
        accounts?: App.Models.Account[];
        transaction?: App.Models.Transaction;
        onCancel?: () => void;
    }

    let {
        type = 'expense',
        account,
        categories,
        accounts = [],
        transaction,
        onCancel,
    }: Props = $props();

    const isEdit = $derived(!!transaction);

    const resolvedType = $derived<string>(isEdit && transaction ? transaction.type : type);

    const typeConfig = $derived.by(() => {
        switch (resolvedType) {
            case TransactionType.Income:
                return {
                    textColor: 'text-success',
                    title: 'Tambah Pemasukan',
                    merchantLabel: 'Pemberi Kerja / Sumber',
                    merchantPlaceholder: 'Contoh: Gaji Bulanan PT Teknologi, Freelance',
                    accountLabel: 'Akun Tujuan Penerimaan',
                };
            default:
                return {
                    textColor: 'text-error',
                    title: 'Tambah Pengeluaran',
                    merchantLabel: 'Merchant / Penerima',
                    merchantPlaceholder: 'Contoh: Warteg Bu Sri, Tokopedia',
                    accountLabel: 'Akun Sumber',
                };
        }
    });

    function buildInitialData() {
        if (isEdit && transaction) {
            return {
                type: transaction.type,
                amount: Number(transaction.amount),
                transaction_date: transaction.transaction_date,
                category_id: transaction.category_id ?? '',
                description: transaction.description ?? '',
                account_id: account?.id ?? transaction.account_id ?? '',
            };
        }

        return {
            type,
            amount: 0,
            transaction_date: new Date(),
            category_id: '',
            description: '',
            account_id: account?.id ?? '',
        };
    }

    const form = useForm(buildInitialData());

    const submitLabel = $derived(
        isEdit
            ? 'Simpan Perubahan'
            : resolvedType === TransactionType.Income
              ? 'Tambah Pemasukan'
              : 'Tambah Pengeluaran'
    );

    const defaultBack = () => window.history.back();

    function handleAmountInput(e: Event): void {
        const input = e.target as HTMLInputElement;
        const raw = input.value.replace(/\D/g, '');
        form.amount = parseInt(raw, 10) || 0;
    }
</script>

{#key isEdit ? 'edit' : resolvedType}
    <div class="space-y-3">
        <!-- ── Back header ─────────────────────────────── -->
        <div class="flex items-center justify-between px-1">
            <button
                class="btn btn-square btn-ghost btn-sm"
                aria-label="Kembali"
                onclick={onCancel ?? defaultBack}
                type="button">
                <i class="iconify size-5 solar--arrow-left-line-duotone"></i>
            </button>
            <span class="text-sm font-semibold tracking-tight">{typeConfig.title}</span>
            <div class="w-9"></div>
        </div>

        <!-- ── Form wrapper ─────────────────────────────── -->
        <Form
            id="transaction-form"
            class="space-y-3"
            {...transaction
                ? TransactionController.update.form({ transaction: transaction.id })
                : TransactionController.store.form()}
            {form}>
            <!-- Card: Amount -->
            <div class="card overflow-hidden rounded-lg border border-base-content/15 bg-base-100">
                <div class="px-5 py-4">
                    <p class="text-2xs font-bold tracking-wider text-base-content/40 uppercase">
                        Nominal Transaksi
                    </p>
                    <div class="mt-1 flex items-center gap-1.5">
                        <span class="font-mono text-sm font-medium text-base-content/40">Rp</span>
                        <input
                            class="w-full border-none bg-transparent font-mono text-[clamp(2rem,9vw,2.6rem)] leading-none font-medium tracking-tight outline-none {typeConfig.textColor}"
                            inputmode="numeric"
                            oninput={handleAmountInput}
                            placeholder="0"
                            type="text"
                            value={form.amount ? Formatter.currency(form.amount, true) : ''} />
                    </div>
                </div>
            </div>

            <!-- Card: Details -->
            <div class="card overflow-hidden rounded-lg border border-base-content/15 bg-base-100">
                <!-- Merchant / Description -->
                <div class="flex flex-col px-5 py-3">
                    <label
                        class="text-2xs font-bold tracking-wider text-base-content/40 uppercase"
                        for="in-merchant">
                        {typeConfig.merchantLabel}
                    </label>
                    <input
                        id="in-merchant"
                        class="input mt-0.5 w-full border-none bg-transparent px-0 text-sm font-medium placeholder:text-base-content/30"
                        placeholder={typeConfig.merchantPlaceholder}
                        type="text"
                        bind:value={form.description} />
                </div>

                <div class="mx-5 border-t border-base-content/10"></div>

                <!-- Account -->
                <div class="flex items-center px-5 py-3">
                    <div class="flex-1">
                        <span
                            class="text-2xs font-bold tracking-wider text-base-content/40 uppercase">
                            {typeConfig.accountLabel}
                        </span>
                        <div class="mt-0.5">
                            <AccountSelect
                                {accounts}
                                placeholder="Pilih akun"
                                bind:value={form.account_id} />
                        </div>
                    </div>
                </div>

                <div class="mx-5 border-t border-base-content/10"></div>

                <!-- Category -->
                <div class="flex items-center px-5 py-3">
                    <div class="flex-1">
                        <span
                            class="text-2xs font-bold tracking-wider text-base-content/40 uppercase">
                            Kategori
                        </span>
                        <div class="mt-0.5">
                            <CategorySelect
                                {categories}
                                groupVariant="text"
                                optionVariant="icon"
                                variant="modal"
                                bind:value={form.category_id} />
                        </div>
                    </div>
                </div>

                <div class="mx-5 border-t border-base-content/10"></div>

                <!-- Date -->
                <div class="flex items-center px-5 py-3">
                    <div class="flex-1">
                        <label
                            class="text-2xs font-bold tracking-wider text-base-content/40 uppercase"
                            for="in-date">
                            Tanggal
                        </label>
                        <div class="mt-0.5">
                            <DateInput
                                id="in-date"
                                class="input-sm"
                                placeholder="Pilih tanggal"
                                bind:value={form.transaction_date} />
                        </div>
                    </div>
                </div>
            </div>
        </Form>

        <!-- ── Actions ──────────────────────────────────── -->
        <FormAction
            {form}
            formId="transaction-form"
            labelCancel="Batal"
            labelSubmit={submitLabel}
            onCancel={onCancel ?? defaultBack} />
    </div>
{/key}
