<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { getRandomDecorationColor, getRandomDecorationIcon } from '@data/decorations';
    import { useForm } from '@inertiajs/svelte';
    import TransactionType from '@wayfinder/App/Enums/TransactionType';
    import TransactionTemplateController from '@wayfinder/App/Http/Controllers/Settings/TransactionTemplateController';

    import CategorySelect from '@components/module/category/category-select.svelte';
    import DecorationColorSelector from '@components/module/decoration-color-selector.svelte';
    import DecorationIconSelector from '@components/module/decoration-icon-selector.svelte';
    import Card from '@components/ui/card.svelte';
    import CurrencyInput from '@components/ui/forms/currency-input.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import Form from '@components/ui/forms/form.svelte';
    import Input from '@components/ui/forms/input.svelte';
    import Select from '@components/ui/forms/select.svelte';
    import TagsInput from '@components/ui/forms/tags-input.svelte';
    import Textarea from '@components/ui/forms/textarea.svelte';

    interface Props {
        template?: App.Models.TransactionTemplate | null;
        accounts: App.Models.Account[];
        /** Existing tag slugs offered as combobox suggestions. */
        tags?: string[];
        /** Exposed to the page for its external FormAction — the `bind:form` pattern. */
        form?: ReturnType<typeof useForm>;
    }

    let { template = null, accounts, tags = [], form = $bindable() }: Props = $props();

    const isEdit = $derived(template !== null);

    const typeOptions = [
        { value: TransactionType.Income, label: 'Income' },
        { value: TransactionType.Expense, label: 'Expense' },
        { value: TransactionType.Transfer, label: 'Transfer' },
    ];

    form = useForm(() => ({
        name: template?.name ?? '',
        type: template?.type ?? TransactionType.Expense,
        category_id: template?.category_id ?? '',
        amount:
            template?.amount !== null && template?.amount !== undefined
                ? String(template.amount)
                : '',
        description: template?.description ?? '',
        account_id:
            template?.account_id !== null && template?.account_id !== undefined
                ? String(template.account_id)
                : '',
        destination_account_id:
            template?.destination_account_id !== null &&
            template?.destination_account_id !== undefined
                ? String(template.destination_account_id)
                : '',
        fee_amount:
            template?.fee_amount !== null && template?.fee_amount !== undefined
                ? String(template.fee_amount)
                : '',
        decorations: {
            icon: template?.decorations?.icon ?? getRandomDecorationIcon().slug,
            color: template?.decorations?.color ?? getRandomDecorationColor().slug,
        },
        tags: template?.tags ?? [],
    }));

    const isTransfer = $derived(form.type === TransactionType.Transfer);
    const isIncome = $derived(form.type === TransactionType.Income);

    const action = $derived(
        isEdit && template
            ? TransactionTemplateController.update.form({ template: template.id })
            : TransactionTemplateController.store.form()
    );

    const accountOptions = $derived(
        accounts.map((account) => ({ value: String(account.id), label: account.name }))
    );

    /* A type switch drops the type-specific values so nothing stale reaches the payload. */
    let lastType = form.type;

    $effect(() => {
        if (form.type !== lastType) {
            lastType = form.type;
            form.category_id = '';
            form.destination_account_id = '';
            form.fee_amount = '';
            form.clearErrors();
        }
    });

    /* Strip fields the request prohibits for the active type, and empty decorations. */
    form.transform((data) => {
        const payload: Record<string, unknown> = { ...data };

        if (data.type === TransactionType.Transfer) {
            delete payload.category_id;
        } else {
            delete payload.destination_account_id;
            delete payload.fee_amount;
        }

        if (!data.decorations?.icon && !data.decorations?.color) {
            delete payload.decorations;
        }

        return payload;
    });
</script>

<Form id="transaction-template-form" class="space-y-5" {...action} {form}>
    <Card description="How this template appears in your list." title="Template">
        <div class="space-y-3">
            <Field error={form.errors.name} required title="Name">
                <Input placeholder="e.g. Netflix" bind:value={form.name} />
            </Field>

            <div class="grid grid-cols-2 gap-4">
                <Field title="Icon">
                    <DecorationIconSelector rows={1} bind:value={form.decorations.icon} />
                </Field>

                <Field title="Color">
                    <DecorationColorSelector rows={1} bind:value={form.decorations.color} />
                </Field>
            </div>
        </div>
    </Card>

    <Card
        description="Values prefilled when this template is applied."
        title="Transaction Defaults">
        <div class="space-y-3">
            <Field error={form.errors.amount} required title="Amount">
                <CurrencyInput inputmode="numeric" placeholder="0" bind:value={form.amount} />
            </Field>

            <Field error={form.errors.type} required title="Type">
                <Select options={typeOptions} bind:value={form.type} />
            </Field>

            <Field error={form.errors.account_id} title="Source Account">
                <Select
                    options={[{ value: '', label: 'No default account' }, ...accountOptions]}
                    bind:value={form.account_id} />
            </Field>

            {#if !isTransfer}
                <Field error={form.errors.category_id} required title="Category">
                    <CategorySelect
                        cashflow={isIncome ? 'inflow' : 'outflow'}
                        required
                        bind:value={form.category_id} />
                </Field>
            {:else}
                <Field
                    error={form.errors.destination_account_id}
                    required
                    title="Destination Account">
                    <Select options={accountOptions} bind:value={form.destination_account_id} />
                </Field>
            {/if}

            {#if isTransfer}
                <Field error={form.errors.fee_amount} title="Transfer Fee">
                    <CurrencyInput
                        inputmode="numeric"
                        placeholder="0"
                        bind:value={form.fee_amount} />
                </Field>
            {/if}

            <Field error={form.errors.description} title="Note">
                <Textarea
                    class="min-h-20"
                    placeholder="Optional note or memo"
                    bind:value={form.description} />
            </Field>

            <Field error={form.errors.tags} title="Tags">
                <TagsInput bind:value={form.tags} {tags} placeholder="Add tags…" />
            </Field>
        </div>
    </Card>
</Form>
