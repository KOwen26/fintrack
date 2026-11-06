<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { useForm } from '@inertiajs/svelte';
    import ImportController from '@wayfinder/App/Http/Controllers/ImportController';

    import { cn } from '@utilities/shadcn';

    import AccountInfo from '@components/module/account/account-info.svelte';
    import AccountSelect from '@components/module/account/account-select.svelte';
    import Button from '@components/ui/button.svelte';
    import DrawerModal from '@components/ui/drawer-modal.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import FileInput from '@components/ui/forms/file-input.svelte';

    let {
        account = null,
        accounts = [],
        open = $bindable(false),
    }: {
        account?: App.Models.Account | null;
        accounts?: App.Models.Account[];
        open?: boolean;
    } = $props();

    let selectedAccountId = $state<number | undefined>(undefined);
    const resolvedAccount = $derived(
        account ?? accounts.find((acc) => acc.id === selectedAccountId) ?? null
    );

    $effect(() => {
        if (account) selectedAccountId = account.id;
    });

    let form = useForm<{ statement: File | null }>({ statement: null });
    let files = $state<FileList | undefined>(undefined);

    function submit(): void {
        if (!resolvedAccount) return;

        form.statement = files?.item(0) ?? null;

        form.post(ImportController.store.url({ account: resolvedAccount.id }), {
            preserveScroll: true,
        });
    }
</script>

<DrawerModal title="Import Statement" bind:open>
    <div class="space-y-5 p-5">
        <Field title="Account">
            {#if account}
                <AccountInfo {account} />
            {:else}
                <AccountSelect
                    {accounts}
                    placeholder="Select account"
                    required
                    bind:value={selectedAccountId} />
            {/if}
        </Field>

        <Field error={form.errors.statement} required title="Statement">
            <FileInput accept=".csv,.xlsx" disabled={!resolvedAccount} required bind:files />
        </Field>

        <Button
            class="w-full"
            color="primary"
            disabled={!files || files.length === 0 || form.processing || !resolvedAccount}
            onclick={submit}>
            <i
                class={cn(
                    'iconify size-5 solar--upload-square-line-duotone',
                    form.processing && 'animate-spin solar--refresh-line-duotone'
                )}></i>
            {form.processing ? 'Parsing' : 'Parse Statement'}
        </Button>
    </div>
</DrawerModal>
