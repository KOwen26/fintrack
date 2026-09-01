<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { setLayoutProps } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import PageSection from '@components/layouts/page-section.svelte';
    import AccountForm from '@components/module/account/account-form.svelte';
    import DashboardPageHeader from '@components/navigation/dashboard-page-header.svelte';

    let { providers }: { providers: App.Models.Provider[] } = $props();

    setLayoutProps({ backUrl: AccountController.index.url() });
</script>

<DashboardPageHeader title="New Account" />

<PageSection>
    <AccountForm {providers} />
</PageSection>
