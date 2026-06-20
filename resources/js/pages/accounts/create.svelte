<script lang="ts">
    import type { App } from '@wayfinder/types';

    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import AccountForm from '@components/module/account/account-form.svelte';
    import Button from '@components/ui/button.svelte';

    let { providers }: { providers: App.Models.Provider[] } = $props();
</script>

<div class="p-4">
    <div class="mb-4 flex items-center gap-3">
        <Button
            class="btn-circle btn-sm"
            color="light"
            href={AccountController.index.url()}
            variant="ghost">
            <i class="iconify size-5 ph--arrow-left-bold"></i>
        </Button>
        <h1 class="text-xl font-bold">New Account</h1>
    </div>

    <AccountForm {providers} />
</div>
