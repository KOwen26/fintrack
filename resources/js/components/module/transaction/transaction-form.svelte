<script lang="ts">
    import type { InertiaForm } from '@inertiajs/svelte';
    import type { App } from '@wayfinder/types';

    import { useForm } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import AccountSelect from '@components/ui/forms/account-select.svelte';
    import CategorySelect from '@components/ui/forms/category-select.svelte';
    import DateInput from '@components/ui/forms/date-input.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import Form from '@components/ui/forms/form.svelte';

    interface Props {
        type?: 'income' | 'expense' | 'transfer';
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

    const today = new Date().toISOString().split('T')[0];

    const isTransferType = $derived(
        isEdit &&
            !!transaction &&
            (transaction.type === 'transfer_out' ||
                transaction.type === 'transfer_in' ||
                transaction.type === 'fee')
    );

    const resolvedType = $derived<string>(
        isEdit && transaction
            ? isTransferType
                ? 'transfer'
                : transaction.type === 'income'
                  ? 'income'
                  : 'expense'
            : type
    );

    const typeConfig = $derived.by(() => {
        switch (resolvedType) {
            case 'income':
                return {
                    accentBar: 'bg-success',
                    textColor: 'text-success',
                    title: 'Tambah Pemasukan',
                    merchantLabel: 'Pemberi Kerja / Sumber',
                    merchantPlaceholder: 'Contoh: Gaji Bulanan PT Teknologi, Freelance',
                    accountLabel: 'Akun Tujuan Penerimaan',
                    showDestination: false,
                };
            case 'transfer':
                return {
                    accentBar: 'bg-info',
                    textColor: 'text-info',
                    title: 'Tambah Transfer',
                    merchantLabel: 'Deskripsi Transfer',
                    merchantPlaceholder: 'Contoh: Kirim uang bulanan, Top-up wallet',
                    accountLabel: 'Akun Asal (Dari)',
                    showDestination: true,
                };
            default:
                return {
                    accentBar: 'bg-error',
                    textColor: 'text-error',
                    title: 'Tambah Pengeluaran',
                    merchantLabel: 'Merchant / Penerima',
                    merchantPlaceholder: 'Contoh: Warteg Bu Sri, Tokopedia',
                    accountLabel: 'Akun Sumber',
                    showDestination: false,
                };
        }
    });

    function buildInitialData(): Record<string, any> {
        if (isEdit && transaction) {
            return {
                amount: Number(transaction.amount),
                transaction_date: transaction.transaction_date,
                category_id: transaction.category_id ?? '',
                description: transaction.description ?? '',
                notes: '',
                account_id: account?.id ?? transaction.account_id ?? '',
                destination_account_id: null,
                fee_amount: null,
            };
        }

        return {
            amount: 0,
            transaction_date: today,
            category_id: '',
            description: '',
            notes: '',
            account_id: account?.id ?? '',
            destination_account_id: null,
            fee_amount: null,
        };
    }

    let form: InertiaForm<any> = $state(useForm(buildInitialData()));

    const action = $derived(
        isEdit && transaction
            ? TransactionController.update.url(transaction.id)
            : TransactionController.store.url()
    );

    const method = $derived<'put' | undefined>(isEdit ? 'put' : undefined);

    const submitLabel = $derived(
        isEdit
            ? 'Simpan Perubahan'
            : resolvedType === 'income'
              ? 'Tambah Pemasukan'
              : resolvedType === 'transfer'
                ? 'Tambah Transfer'
                : 'Tambah Pengeluaran'
    );

    const defaultBack = () => window.history.back();

    // ── Amount display with currency formatting ──────────
    let displayAmount = $state('');

    function handleAmountInput(e: Event): void {
        const input = e.target as HTMLInputElement;
        const raw = input.value.replace(/\D/g, '');
        const num = parseInt(raw, 10) || 0;
        form.amount = num;
        displayAmount = num ? num.toLocaleString('id-ID') : '';
    }

    $effect(() => {
        const num = Number(form.amount) || 0;
        const expected = num ? num.toLocaleString('id-ID') : '';
        if (expected !== displayAmount) {
            displayAmount = expected;
        }
    });
</script>

{#key isEdit ? 'edit' : resolvedType}
    <div class="space-y-3">
        <!-- ── Back header ─────────────────────────────── -->
        <div class="flex items-center justify-between px-1">
            <button
                class="btn btn-ghost btn-sm btn-square"
                aria-label="Kembali"
                onclick={onCancel ?? defaultBack}
                type="button">
                <i class="iconify ph--arrow-left-bold size-5"></i>
            </button>
            <span class="text-sm font-semibold tracking-tight">{typeConfig.title}</span>
            <div class="w-9"></div>
        </div>

        <!-- ── Form wrapper ─────────────────────────────── -->
        <Form id="transaction-form" {action} {form} {method}>
            <!-- Card: Amount -->
            <div class="card bg-base-100 rounded-2xl overflow-hidden border border-base-content/15">
                <div class="h-1 w-full {typeConfig.accentBar}"></div>
                <div class="px-5 py-4">
                    <p
                        class="text-[0.625rem] font-bold tracking-[0.09em] uppercase text-base-content/40">
                        Nominal Transaksi
                    </p>
                    <div class="flex items-center gap-1.5 mt-1">
                        <span class="font-mono text-sm font-medium text-base-content/40">Rp</span>
                        <input
                            class="font-mono text-[clamp(2rem,9vw,2.6rem)] font-medium w-full border-none bg-transparent outline-none leading-none tracking-tight {typeConfig.textColor}"
                            inputmode="numeric"
                            oninput={handleAmountInput}
                            placeholder="0"
                            type="text"
                            value={displayAmount} />
                    </div>
                </div>
            </div>

            <!-- Card: Details -->
            <div class="card bg-base-100 rounded-2xl overflow-hidden border border-base-content/15">
                <!-- Merchant / Description -->
                <div class="flex flex-col px-5 py-3">
                    <label
                        class="text-[0.625rem] font-bold tracking-[0.09em] uppercase text-base-content/40"
                        for="in-merchant">
                        {typeConfig.merchantLabel}
                    </label>
                    <input
                        id="in-merchant"
                        class="input w-full border-none bg-transparent px-0 text-sm font-medium mt-0.5 placeholder:text-base-content/30"
                        placeholder={typeConfig.merchantPlaceholder}
                        type="text"
                        bind:value={form.description} />
                </div>

                <div class="border-t border-base-content/10 mx-5"></div>

                <!-- Account -->
                <div class="flex items-center px-5 py-3">
                    <div class="flex-1">
                        <span
                            class="text-[0.625rem] font-bold tracking-[0.09em] uppercase text-base-content/40">
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

                {#if typeConfig.showDestination}
                    <div class="border-t border-base-content/10 mx-5"></div>

                    <div class="flex items-center px-5 py-3">
                        <div class="flex-1">
                            <span
                                class="text-[0.625rem] font-bold tracking-[0.09em] uppercase text-base-content/40">
                                Akun Tujuan
                            </span>
                            <div class="mt-0.5">
                                <AccountSelect
                                    {accounts}
                                    placeholder="Pilih tujuan"
                                    bind:value={form.destination_account_id} />
                            </div>
                        </div>
                    </div>
                {/if}

                <div class="border-t border-base-content/10 mx-5"></div>

                <!-- Category -->
                <div class="flex items-center px-5 py-3">
                    <div class="flex-1">
                        <span
                            class="text-[0.625rem] font-bold tracking-[0.09em] uppercase text-base-content/40">
                            Kategori
                        </span>
                        <div class="mt-0.5">
                            <CategorySelect {categories} bind:value={form.category_id} />
                        </div>
                    </div>
                </div>

                <div class="border-t border-base-content/10 mx-5"></div>

                <!-- Date -->
                <div class="flex items-center px-5 py-3">
                    <div class="flex-1">
                        <label
                            class="text-[0.625rem] font-bold tracking-[0.09em] uppercase text-base-content/40"
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

            <!-- Card: Notes -->
            <div class="card bg-base-100 rounded-2xl overflow-hidden border border-base-content/15">
                <div class="flex flex-col px-5 py-3">
                    <label
                        class="text-[0.625rem] font-bold tracking-[0.09em] uppercase text-base-content/40"
                        for="in-notes">
                        Catatan
                    </label>
                    <textarea
                        id="in-notes"
                        class="textarea w-full border-none bg-transparent px-0 text-sm font-normal leading-relaxed resize-none mt-0.5 placeholder:text-base-content/30"
                        placeholder="Tambahkan catatan detail transaksi di sini..."
                        rows="2"
                        bind:value={form.notes}></textarea>
                </div>
            </div>
        </Form>

        <!-- ── Actions ──────────────────────────────────── -->
        <FormAction
            form={form as any}
            formId="transaction-form"
            labelCancel="Batal"
            labelSubmit={submitLabel}
            onCancel={onCancel ?? defaultBack} />
    </div>
{/key}
