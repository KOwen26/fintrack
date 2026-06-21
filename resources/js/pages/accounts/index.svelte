<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { Link } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import Section from '@components/layouts/section.svelte';
    import AccountCard from '@components/module/account/account-card.svelte';
    import AccountList from '@components/module/account/account-list.svelte';
    import Button from '@components/ui/button.svelte';
    import ToggleableCard from '@components/ui/cards/toggleable-card.svelte';

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
