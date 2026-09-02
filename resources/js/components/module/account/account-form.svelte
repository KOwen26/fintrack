<script lang="ts">
    import type { App } from '@wayfinder/types';

    import AccountCard from './account-card.svelte';

    import { router, useForm } from '@inertiajs/svelte';
    import AccountAccessType from '@wayfinder/App/Enums/AccountAccessType';
    import AccountType from '@wayfinder/App/Enums/AccountType';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import {
        accountAccessTypeOptions,
        accountSchema,
        accountTypeOptions,
        iconForAccountType,
    } from '@schema/account.schema';

    import { DataComposer } from '@utilities/data-composer';

    import Card from '@components/ui/card.svelte';
    import DecorationColorSelector from '@components/ui/forms/decoration-color-selector.svelte';
    import DecorationIconSelector from '@components/ui/forms/decoration-icon-selector.svelte';
    import FieldInput from '@components/ui/forms/field-input.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import Form from '@components/ui/forms/form.svelte';

    interface Props {
        providers: App.Models.Provider[];
        account?: App.Models.Account;
        onCancel?: () => void;
    }

    let { providers, account, onCancel }: Props = $props();

    const isEdit = $derived(!!account);

    const providerOptions = $derived([...providers.map((p) => ({ value: p.id, label: p.name }))]);

    const formSchema = $derived(
        DataComposer.from(accountSchema)
            .extendSchema({
                provider_id: {
                    label: 'Provider',
                    form: () => ({ type: 'select', name: 'provider_id', options: providerOptions }),
                },
            })
            .toFormGenerator(account ?? {})
    );

    let form = useForm({
        ...formSchema.data,
        type: account?.type ?? AccountType.DebitAccount,
        access_type: account?.access_type ?? AccountAccessType.Personal,
        decorations: {
            icon:
                account?.decorations?.icon ??
                iconForAccountType(account?.type ?? AccountType.DebitAccount),
            color: account?.decorations?.color ?? 'emerald-600',
        },
    });

    // Keep the decoration icon in sync with the selected account type.
    $effect(() => {
        form.decorations.icon = iconForAccountType(form.type);
    });

    // Mock account projection of live form state, rendered through the real AccountCard2.
    const previewAccount = $derived({
        id: 0,
        name: form.name || 'Account name',
        type: form.type,
        access_type: form.access_type,
        current_balance: form.initial_balance ?? 0,
        provider: providers.find((p) => p.id === form.provider_id) ?? null,
        decorations: form.decorations,
    });

    const submitLabel = $derived(isEdit ? 'Save Changes' : 'Create Account');
</script>

<div class="space-y-5">
    <AccountCard account={previewAccount} hideActions hideEdit hideFooter />
    <Form
        id="account-form"
        class="space-y-5"
        {...account
            ? AccountController.update.form({ account: account.id })
            : AccountController.store.form()}
        {form}>
        <Card>
            <Field title="Account Type">
                <div class="flex gap-1 rounded-md bg-base-300 p-1">
                    {#each accountTypeOptions as opt (opt.value)}
                        <button
                            class="type-btn flex flex-1 flex-col items-center gap-1 rounded-md py-2 text-[0.6rem] font-medium transition
                                {form.type === opt.value
                                ? 'bg-primary text-primary-content'
                                : 'text-base-content/60 hover:bg-base-200'}"
                            onclick={() => (form.type = opt.value)}
                            type="button">
                            <i class="iconify {opt.icon} text-lg"></i>
                            <span>{opt.label}</span>
                        </button>
                    {/each}
                </div>
            </Field>
        </Card>

        <Card>
            <div class="grid grid-cols-2 gap-5">
                <Field title="Card Color">
                    <DecorationColorSelector rows={1} bind:value={form.decorations.color} />
                </Field>

                <Field title="Card Icon">
                    <DecorationIconSelector rows={1} bind:value={form.decorations.icon} />
                </Field>
            </div>
        </Card>

        <Card>
            <div class="space-y-5">
                <FieldInput
                    {...formSchema.fields.name}
                    error={form.errors.name}
                    bind:value={form.name} />

                <Field title="Access Type">
                    <div class="grid grid-cols-2 gap-3">
                        {#each accountAccessTypeOptions as opt (opt.value)}
                            <button
                                class="flex items-center gap-3 rounded-lg border-2 p-3 text-left transition
                                {form.access_type === opt.value
                                    ? 'border-primary bg-primary/5'
                                    : 'border-base-300 hover:border-base-content/20'}"
                                onclick={() => (form.access_type = opt.value)}
                                type="button">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg
                                    {form.access_type === opt.value
                                        ? 'bg-primary text-primary-content'
                                        : 'bg-base-200 text-base-content/60'}">
                                    <i class="iconify {opt.icon} text-lg"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium">{opt.label}</p>
                                    <p class="text-xs text-base-content/50">{opt.description}</p>
                                </div>
                            </button>
                        {/each}
                    </div>
                </Field>

                <FieldInput
                    {...formSchema.fields.provider_id}
                    error={form.errors.provider_id}
                    bind:value={form.provider_id} />

                {#if !isEdit}
                    <FieldInput
                        {...formSchema.fields.initial_balance}
                        error={form.errors.initial_balance}
                        bind:value={form.initial_balance} />
                {/if}
            </div>
        </Card>

        <FormAction
            cancelClass="flex-auto"
            submitClass="flex-3/5"
            {form}
            formId="account-form"
            labelCancel="Cancel"
            labelSubmit={submitLabel}
            onCancel={onCancel ??
                (() =>
                    router.visit(
                        isEdit
                            ? AccountController.show.url({ id: account?.id })
                            : AccountController.index.url()
                    ))} />
    </Form>
</div>
