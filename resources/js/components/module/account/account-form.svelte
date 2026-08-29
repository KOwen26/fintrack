<script lang="ts">
    import type { InertiaForm } from '@inertiajs/svelte';
    import type { App } from '@wayfinder/types';

    import { getDecorationColor } from '@data/decoration-colors';
    import { getDecorationIcon } from '@data/decoration-icons';
    import { useForm } from '@inertiajs/svelte';
    import AccountAccessType from '@wayfinder/App/Enums/AccountAccessType';
    import AccountType from '@wayfinder/App/Enums/AccountType';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import { accountSchema } from '@schema/account.schema';

    import { DataComposer } from '@utilities/data-composer';

    import Card from '@components/ui/card.svelte';
    import DecorationColorSelector from '@components/ui/forms/decoration-color-selector.svelte';
    import DecorationIconSelector from '@components/ui/forms/decoration-icon-selector.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import FormGenerator from '@components/ui/forms/form-generator.svelte';

    interface Props {
        providers: App.Models.Provider[];
        account?: App.Models.Account;
        onCancel?: () => void;
    }

    let { providers, account, onCancel }: Props = $props();

    const isEdit = $derived(!!account);

    const providerOptions = $derived([
        { value: '', label: '— None —' },
        ...providers.map((p) => ({ value: p.id, label: p.name })),
    ]);

    const typeOptions: { value: string; label: string }[] = [
        { value: AccountType.DebitAccount, label: 'Debit / Savings' },
        { value: AccountType.CreditCard, label: 'Credit Card' },
        { value: AccountType.CashWallet, label: 'Cash Wallet' },
        { value: AccountType.EWallet, label: 'E-Wallet' },
        { value: AccountType.Investment, label: 'Investment' },
    ];

    const iconForType = (type: string): string =>
        ({
            [AccountType.DebitAccount]: 'bank-bold',
            [AccountType.CreditCard]: 'credit-card-bold',
            [AccountType.CashWallet]: 'money-bold',
            [AccountType.EWallet]: 'device-mobile-bold',
            [AccountType.Investment]: 'chart-line-bold',
        })[type] ?? 'wallet-bold';

    const formSchema = $derived(() => {
        const composer = DataComposer.from(accountSchema).extendSchema({
            provider_id: {
                label: 'Provider (optional)',
                form: () => ({ type: 'select', name: 'provider_id', options: providerOptions }),
            },
        });

        const exceptKeys = isEdit ? ['type', 'access_type', 'initial_balance'] : ['type'];

        return composer.except(exceptKeys).toFormGenerator(
            isEdit && account
                ? {
                      name: account.name,
                      type: account.type,
                      provider_id: account.provider_id ?? '',
                  }
                : {
                      type: AccountType.DebitAccount,
                      access_type: AccountAccessType.Personal,
                      provider_id: '',
                      initial_balance: 0,
                  }
        );
    });

    let form: InertiaForm<any> = $state(
        useForm({
            ...formSchema().data,
            type: isEdit && account ? account.type : AccountType.DebitAccount,
            decorations: {
                icon:
                    isEdit && account
                        ? (account.decorations?.icon ?? iconForType(account.type))
                        : iconForType(AccountType.DebitAccount),
                color:
                    isEdit && account
                        ? (account.decorations?.color ?? 'emerald-600')
                        : 'emerald-600',
            },
        })
    );

    // Keep the decoration icon in sync with the selected account type.
    $effect(() => {
        form.decorations.icon = iconForType(form.type);
    });

    const selectedProviderName = $derived(
        providers.find((p) => p.id === form.provider_id)?.name ?? '— None —'
    );

    const typeLabel = $derived(typeOptions.find((t) => t.value === form.type)?.label ?? 'Account');

    const previewBalance = $derived(
        isEdit ? (account?.initial_balance ?? 0) : (form.initial_balance ?? 0)
    );

    const action = $derived(
        isEdit && account
            ? AccountController.update.url({ account: account.id })
            : AccountController.store.url()
    );

    const method = $derived(isEdit ? 'put' : undefined);
    const submitLabel = $derived(isEdit ? 'Save Changes' : 'Create Account');
</script>

<div class="space-y-5">
    {@render accountPreview()}

    <Card>
        {@render accountAppearance()}
    </Card>

    <Card>
        <FormGenerator
            id="account-form"
            {action}
            formSchema={formSchema()}
            {method}
            withoutSubmit
            bind:form />
    </Card>

    <div>
        <FormAction
            {form}
            formId="account-form"
            labelCancel="Cancel"
            labelSubmit={submitLabel}
            onCancel={onCancel ?? (() => window.history.back())} />
    </div>
</div>

{#snippet accountPreview()}
    {@const color = getDecorationColor(form.decorations.color)}
    {@const icon = getDecorationIcon(form.decorations.icon)}
    <div
        style="background-color: {color?.oklch}; color: {color?.text_color}"
        class="relative overflow-hidden rounded p-5">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/15">
                <i class="iconify {icon?.value} text-xl"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs tracking-wide uppercase opacity-70">{selectedProviderName}</p>
                <p class="truncate font-semibold">{form.name || 'Account name'}</p>
            </div>
        </div>
        <div class="mt-4">
            <p class="text-xs tracking-wide uppercase opacity-70">{typeLabel}</p>
            <p class="font-mono text-2xl font-semibold">{previewBalance}</p>
        </div>
    </div>
{/snippet}

{#snippet accountAppearance()}
    <div class="space-y-3">
        <div>
            <p class="mb-2 text-sm font-medium">Account Type</p>
            <div class="grid grid-cols-3 gap-3">
                {#each typeOptions as opt (opt.value)}
                    <button
                        class="rounded border px-3 py-2 text-sm font-medium transition {form.type ===
                        opt.value
                            ? 'border-primary bg-primary/10 text-primary'
                            : 'border-base-content/25 bg-base-100 text-base-content/70 hover:bg-base-200'}"
                        onclick={() => (form.type = opt.value)}
                        type="button">
                        {opt.label}
                    </button>
                {/each}
            </div>
        </div>

        <div>
            <p class="mb-2 text-sm font-medium">Card Color</p>
            <DecorationColorSelector bind:value={form.decorations.color} />
        </div>

        <div>
            <p class="mb-2 text-sm font-medium">Card Icon</p>
            <DecorationIconSelector variant="popover" bind:value={form.decorations.icon} />
        </div>
    </div>
{/snippet}
