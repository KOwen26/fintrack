<script lang="ts">
    import type { App } from '@wayfinder/types';
    import type { Snippet } from 'svelte';

    import AccountCard from './account-card.svelte';

    import { getRandomDecorationColor } from '@data/decorations';
    import { router, useForm } from '@inertiajs/svelte';
    import { DataComposer } from '@lib/data-composer';
    import AccountAccessType from '@wayfinder/App/Enums/AccountAccessType';
    import AccountType from '@wayfinder/App/Enums/AccountType';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import {
        accountAccessTypeOptions,
        accountSchema,
        accountTypeOptions,
        iconForAccountType,
    } from '@schema/account.schema';

    import { cn } from '@utilities/shadcn';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import DecorationColorSelector from '@components/module/decoration-color-selector.svelte';
    import DecorationIconSelector from '@components/module/decoration-icon-selector.svelte';
    import BottomActionBar from '@components/navigation/bottom-action-bar.svelte';
    import ResponsiveCard from '@components/ui/cards/responsive-card.svelte';
    import FieldInput from '@components/ui/forms/field-input.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import Form from '@components/ui/forms/form.svelte';

    interface Props {
        providers: App.Models.Provider[];
        account?: App.Models.Account;
        onCancel?: () => void;
        children?: Snippet;
    }

    let { providers, account, onCancel, children }: Props = $props();

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
            .toFormGenerator(account)
    );

    let form = useForm(() => ({
        ...formSchema.data,
        type: account?.type ?? AccountType.DebitAccount,
        access_type: account?.access_type ?? AccountAccessType.Personal,
        decorations: {
            icon:
                account?.decorations?.icon ??
                iconForAccountType(account?.type ?? AccountType.DebitAccount),
            color: account?.decorations?.color ?? getRandomDecorationColor().slug,
        },
    }));

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

    const formActionProps = $derived({
        cancelClass: 'flex-auto',
        submitClass: 'flex-3/5',
        form,
        formId: 'account-form',
        labelCancel: 'Cancel',
        labelSubmit: submitLabel,
        onCancel:
            onCancel ??
            (() =>
                router.visit(
                    isEdit
                        ? AccountController.show.url({ id: account?.id })
                        : AccountController.index.url()
                )),
    });
</script>

<MobilePageLayout heroClass="flex flex-col justify-center" variant="4/5">
    {#snippet hero()}
        <AccountCard class="w-full" account={previewAccount} hideActions hideEdit hideFooter />
    {/snippet}

    <ResponsiveCard>
        <Form
            id="account-form"
            class="space-y-5"
            {...account
                ? AccountController.update.form({ account: account.id })
                : AccountController.store.form()}
            {form}>
            <Field title="Account Type">
                <div class="flex gap-1 rounded-lg border border-border bg-muted p-1">
                    {#each accountTypeOptions as opt (opt.value)}
                        <button
                            class={cn(
                                'flex flex-1 flex-col items-center gap-1 rounded-md py-2 text-xs font-medium transition',
                                form.type === opt.value
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-muted-foreground/10'
                            )}
                            onclick={() => (form.type = opt.value)}
                            type="button">
                            <i class="iconify {opt.icon} size-6"></i>
                            <span class="h-fit leading-none">{opt.label}</span>
                        </button>
                    {/each}
                </div>
            </Field>

            <div class="grid grid-cols-2 gap-5">
                <Field title="Card Color">
                    <DecorationColorSelector rows={1} bind:value={form.decorations.color} />
                </Field>

                <Field title="Card Icon">
                    <DecorationIconSelector rows={1} bind:value={form.decorations.icon} />
                </Field>
            </div>

            <div class="space-y-5">
                <FieldInput
                    {...formSchema.fields.name}
                    error={form.errors.name}
                    bind:value={form.name} />

                <Field title="Access Type">
                    <div class="grid grid-cols-2 gap-3">
                        {#each accountAccessTypeOptions as opt (opt.value)}
                            {@const selected = form.access_type === opt.value}
                            <button
                                class={cn([
                                    'flex items-center gap-3 rounded-lg border border-transparent bg-card p-3 text-left transition',
                                    selected
                                        ? 'border-primary'
                                        : 'border-border hover:border-border',
                                    'hover:border-primary',
                                ])}
                                onclick={() => (form.access_type = opt.value)}
                                type="button">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg
                                        {selected
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-muted-foreground'}">
                                    <i class="iconify {opt.icon} text-lg"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium">{opt.label}</p>
                                    <p class="text-xs text-muted-foreground">{opt.description}</p>
                                </div>
                            </button>
                        {/each}
                    </div>
                </Field>

                <FieldInput
                    {...formSchema.fields.provider_id}
                    error={form.errors.provider_id}
                    bind:value={form.provider_id} />

                <FieldInput
                    {...formSchema.fields.initial_balance}
                    error={form.errors.initial_balance}
                    bind:value={form.initial_balance} />
            </div>

            <FormAction class="hidden md:flex" {...formActionProps} />
        </Form>
    </ResponsiveCard>

    {#if children}
        {@render children()}
    {/if}
</MobilePageLayout>

<BottomActionBar>
    <FormAction class="w-full" {...formActionProps} />
</BottomActionBar>
