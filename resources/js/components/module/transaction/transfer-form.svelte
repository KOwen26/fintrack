<script lang="ts">
    import type { InertiaForm } from '@inertiajs/svelte';
    import type { App } from '@wayfinder/types';

    import { useForm } from '@inertiajs/svelte';
    import TransferController from '@wayfinder/App/Http/Controllers/TransferController';

    import AccountSelect from '@components/ui/forms/account-select.svelte';
    import DateInput from '@components/ui/forms/date-input.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import Form from '@components/ui/forms/form.svelte';

    interface Props {
        transfer: App.Models.Transfer & { transactions: App.Models.Transaction[] };
        accounts: App.Models.Account[];
        onCancel?: () => void;
    }

    let { transfer, accounts, onCancel }: Props = $props();

    const sourceTransaction = $derived(
        transfer.transactions.find((row) => row.type === 'transfer' && row.flow === 'outflow')
    );
    const destinationTransaction = $derived(
        transfer.transactions.find((row) => row.type === 'transfer' && row.flow === 'inflow')
    );

    let form: InertiaForm<any> = $state(
        useForm({
            account_id: sourceTransaction?.account_id ?? '',
            destination_account_id: destinationTransaction?.account_id ?? '',
            amount: Number(transfer.amount),
            fee_amount: transfer.fee_amount === null ? '' : Number(transfer.fee_amount),
            transaction_date: transfer.transaction_date,
            description: transfer.description ?? '',
        })
    );

    const action = $derived(TransferController.update.url({ transfer: transfer.id }));
    const defaultBack = () => window.history.back();

    let displayAmount = $state(Number(transfer.amount).toLocaleString('id-ID'));

    function handleAmountInput(e: Event): void {
        const input = e.target as HTMLInputElement;
        const raw = input.value.replace(/\D/g, '');
        const num = parseInt(raw, 10) || 0;
        form.amount = num;
        displayAmount = num ? num.toLocaleString('id-ID') : '';
    }
</script>

<div class="space-y-3">
    <div class="flex items-center justify-between px-1">
        <button
            class="btn btn-square btn-ghost btn-sm"
            aria-label="Kembali"
            onclick={onCancel ?? defaultBack}
            type="button">
            <i class="iconify size-5 solar--arrow-left-line-duotone"></i>
        </button>
        <span class="text-sm font-semibold tracking-tight">Edit Transfer</span>
        <div class="w-9"></div>
    </div>

    <Form id="transfer-form" {action} {form} method="put">
        <div class="card overflow-hidden rounded-2xl border border-base-content/15 bg-base-100">
            <div class="h-1 w-full bg-info"></div>
            <div class="px-5 py-4">
                <p
                    class="text-[0.625rem] font-bold tracking-[0.09em] text-base-content/40 uppercase">
                    Nominal Transfer
                </p>
                <div class="mt-1 flex items-center gap-1.5">
                    <span class="font-mono text-sm font-medium text-base-content/40">Rp</span>
                    <input
                        class="w-full border-none bg-transparent font-mono text-[clamp(2rem,9vw,2.6rem)] leading-none font-medium tracking-tight text-info outline-none"
                        inputmode="numeric"
                        oninput={handleAmountInput}
                        placeholder="0"
                        type="text"
                        value={displayAmount} />
                </div>
            </div>
        </div>

        <div class="card overflow-hidden rounded-2xl border border-base-content/15 bg-base-100">
            <div class="flex flex-col px-5 py-3">
                <label
                    class="text-[0.625rem] font-bold tracking-[0.09em] text-base-content/40 uppercase"
                    for="tf-description">
                    Deskripsi Transfer
                </label>
                <input
                    id="tf-description"
                    class="input mt-0.5 w-full border-none bg-transparent px-0 text-sm font-medium placeholder:text-base-content/30"
                    placeholder="Contoh: Kirim uang bulanan"
                    type="text"
                    bind:value={form.description} />
            </div>

            <div class="mx-5 border-t border-base-content/10"></div>

            <div class="flex items-center px-5 py-3">
                <div class="flex-1">
                    <span
                        class="text-[0.625rem] font-bold tracking-[0.09em] text-base-content/40 uppercase">
                        Akun Asal (Dari)
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

            <div class="flex items-center px-5 py-3">
                <div class="flex-1">
                    <span
                        class="text-[0.625rem] font-bold tracking-[0.09em] text-base-content/40 uppercase">
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

            <div class="mx-5 border-t border-base-content/10"></div>

            <div class="flex items-center px-5 py-3">
                <div class="flex-1">
                    <span
                        class="text-[0.625rem] font-bold tracking-[0.09em] text-base-content/40 uppercase">
                        Biaya Transfer (opsional)
                    </span>
                    <input
                        class="input mt-0.5 w-full border-none bg-transparent px-0 font-mono text-sm font-medium placeholder:text-base-content/30"
                        inputmode="numeric"
                        placeholder="0"
                        type="text"
                        bind:value={form.fee_amount} />
                </div>
            </div>

            <div class="mx-5 border-t border-base-content/10"></div>

            <div class="flex items-center px-5 py-3">
                <div class="flex-1">
                    <label
                        class="text-[0.625rem] font-bold tracking-[0.09em] text-base-content/40 uppercase"
                        for="tf-date">
                        Tanggal
                    </label>
                    <div class="mt-0.5">
                        <DateInput
                            id="tf-date"
                            class="input-sm"
                            placeholder="Pilih tanggal"
                            bind:value={form.transaction_date} />
                    </div>
                </div>
            </div>
        </div>
    </Form>

    <FormAction
        form={form as any}
        formId="transfer-form"
        labelCancel="Batal"
        labelSubmit="Simpan Perubahan"
        onCancel={onCancel ?? defaultBack} />
</div>
