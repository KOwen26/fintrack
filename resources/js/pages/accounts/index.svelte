<script lang="ts">
    import type { App } from '@wayfinder/types';

    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import Section from '@components/layouts/section.svelte';
    import AccountList from '@components/module/account/account-list.svelte';
    import Button from '@components/ui/button.svelte';

    let { accounts }: { accounts: App.Models.Account[] } = $props();
</script>

<div class="mb-4 flex items-center justify-between">
    <h1 class="text-xl font-bold">Accounts</h1>
    <Button color="primary" href={AccountController.create.url()} size="sm">
        <i class="iconify size-4 ph--plus-bold"></i>
        Add
    </Button>
</div>

<Section breakMargin>
    <AccountList {accounts} />
</Section>
