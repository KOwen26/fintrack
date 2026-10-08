<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { setLayoutProps } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import AccountForm from '@components/module/account/account-form.svelte';

    let { providers }: { providers: App.Models.Provider[] } = $props();

    setLayoutProps({ title: 'New Account', backUrl: AccountController.index.url() });
</script>

<AccountForm {providers} />
