<script lang="ts">
    import type { InertiaForm } from '@inertiajs/svelte';
    import type { App } from '@wayfinder/types';

    import AccountAccessType from '@wayfinder/App/Enums/AccountAccessType';
    import AccountType from '@wayfinder/App/Enums/AccountType';
    import { AccountsController } from '@wayfinder/App/Http/Controllers/AccountsController';

    import { accountSchema } from '@schema/account.schema';

    import { DataComposer } from '@utilities/data-composer';

    import Card from '@components/ui/card.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import FormGenerator from '@components/ui/forms/form-generator.svelte';

    interface Props {
        providers: App.Models.Provider[];
        household_id?: number | null;
        account?: App.Models.Account;
        onCancel?: () => void;
    }

    let { providers, household_id, account, onCancel }: Props = $props();

    let form: InertiaForm<any> = $state(null!);

    const isEdit = $derived(!!account);

    const providerOptions = $derived([
        { value: '', label: '— None —' },
        ...providers.map((p) => ({ value: p.id, label: p.name })),
    ]);

    const formSchema = $derived(() => {
        const composer = DataComposer.from(accountSchema).extendSchema({
            provider_id: {
                label: 'Provider (optional)',
                form: () => ({ type: 'select', name: 'provider_id', options: providerOptions }),
            },
        });

        if (isEdit && account) {
            return composer.except(['access_type', 'initial_balance']).toFormGenerator({
                name: account.name,
                type: account.type,
                provider_id: account.provider_id ?? '',
                credit_card_limit: account.credit_card_limit
                    ? Number(account.credit_card_limit)
                    : null,
                currency: account.currency,
            });
        }

        const { fields, data } = composer.toFormGenerator({
            type: AccountType.DebitAccount,
            access_type: AccountAccessType.Personal,
            provider_id: '',
            initial_balance: 0,
            credit_card_limit: null,
            currency: 'IDR',
        });

        return { fields, data: { ...data, household_id: household_id ?? '' } };
    });

    const action = $derived(
        isEdit && account
            ? AccountsController.update.url({ account: account.id })
            : AccountsController.store.url()
    );

    const method = $derived(isEdit ? 'put' : undefined);
    const submitLabel = $derived(isEdit ? 'Save Changes' : 'Create Account');
</script>

<Card>
    <FormGenerator
        id="account-form"
        {action}
        formSchema={formSchema()}
        {method}
        withoutSubmit
        bind:form />
</Card>

<div class="mt-4">
    <FormAction
        {form}
        formId="account-form"
        labelCancel="Cancel"
        labelSubmit={submitLabel}
        onCancel={onCancel ?? (() => window.history.back())}
        withoutCancel={!onCancel && !isEdit} />
</div>
