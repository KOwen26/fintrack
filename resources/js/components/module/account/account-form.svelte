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
    import Field from '@components/ui/forms/field.svelte';
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

    const typeOptions: { value: string; label: string; icon: string }[] = [
        { value: AccountType.DebitAccount, label: 'Debit', icon: 'solar--buildings-bold-duotone' },
        { value: AccountType.CreditCard, label: 'Credit', icon: 'solar--wallet-bold-duotone' },
        { value: AccountType.CashWallet, label: 'Cash', icon: 'solar--wallet-money-bold-duotone' },
        { value: AccountType.EWallet, label: 'E-Wallet', icon: 'solar--smartphone-bold-duotone' },
        { value: AccountType.Investment, label: 'Invest', icon: 'solar--chart-bold-duotone' },
    ];

    const accessOptions: { value: string; label: string; description: string; icon: string }[] = [
        {
            value: AccountAccessType.Personal,
            label: 'Personal',
            description: 'Solo ownership',
            icon: 'ph--user-bold',
        },
        {
            value: AccountAccessType.Joint,
            label: 'Joint',
            description: 'Shared access',
            icon: 'ph--users-bold',
        },
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

        const exceptKeys = isEdit
            ? ['type', 'access_type', 'initial_balance']
            : ['type', 'access_type'];

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

    {@render accountAppearance()}

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
            cancelClass="flex-auto"
            submitClass="flex-3/5"
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
        <!-- Decorative card bubbles from mockup -->
        <div class="absolute -top-12 -right-12 size-44 rounded-full bg-white/10"></div>
        <div class="absolute right-14 -bottom-10 size-28 rounded-full bg-white/5"></div>

        <div class="relative z-10">
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
    </div>
{/snippet}

{#snippet accountAppearance()}
    <div class="space-y-5">
        <Card>
            <p class="mb-2 text-sm font-medium">Account Type</p>
            <div class="flex gap-1 rounded-md bg-base-300 p-1">
                {#each typeOptions as opt (opt.value)}
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
        </Card>

        <Card>
            <p class="mb-2 text-sm font-medium">Access Type</p>
            <div class="grid grid-cols-2 gap-3">
                {#each accessOptions as opt (opt.value)}
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
    </div>
{/snippet}
