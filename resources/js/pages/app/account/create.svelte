<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { pageLayoutProps } from '@lib/navigation-helper.svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import AccountForm from '@components/module/account/account-form.svelte';

    let { providers }: { providers: App.Models.Provider[] } = $props();

    pageLayoutProps({ title: 'New Account', backUrl: AccountController.index.url() });
</script>

<AccountForm {providers} />
