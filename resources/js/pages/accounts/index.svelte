<script lang="ts">
    import type { App } from '@wayfinder/types';

    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import PageSection from '@components/layouts/page-section.svelte';
    import AccountList from '@components/module/account/account-list.svelte';
    import DashboardPageHeader from '@components/navigation/dashboard-page-header.svelte';
    import Button from '@components/ui/button.svelte';

    let { accounts }: { accounts: App.Models.Account[] } = $props();
</script>

<DashboardPageHeader title="Accounts">
    {#snippet actions()}
        <Button color="primary" href={AccountController.create.url()}>
            <i class="iconify size-5 ph--plus-bold"></i>
            Add
        </Button>
    {/snippet}
</DashboardPageHeader>

<PageSection breakMargin>
    <AccountList {accounts} />
</PageSection>
