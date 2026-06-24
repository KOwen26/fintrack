<script lang="ts">
    import type { Menu } from '@data/menu';

    import { dashboardMenu } from '@data/menu';
    import { useUrlHandler } from '@hooks/url-handler.svelte';
    import { Link } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import { cn } from '@utilities/shadcn';

    interface Props {
        withLabel?: boolean;
    }

    let { withLabel = true }: Props = $props();

    const urlHandler = useUrlHandler();

    const activeChecks = $derived((url) =>
        urlHandler.isCurrentOrParentUrl(url, urlHandler.currentUrl)
    );
</script>

<nav class="md:hidden dock dock-sm">
    {@render dockItem(dashboardMenu.menus.dashboard)}

    {@render dockItem(dashboardMenu.menus.transactions)}

    <div class="relative">
        <div class="bg-primary rounded-lg p-2 min-size-12 absolute -top-1 -translate-y-1/2">
            <Link
                class="size-8 flex items-center justify-center"
                aria-label="Add"
                href={TransactionController.create.url()}>
                <i class="iconify size-8 tabler--plus"></i>
            </Link>
        </div>
    </div>

    {@render dockItem(dashboardMenu.menus.reports)}

    {@render dockItem({
        name: 'Config',
        url: AccountController.index.url(),
        icon: 'solar--wallet-money-linear',
    })}
</nav>

{#snippet dockItem({ name, url, icon }: Menu)}
    {let isActive = $derived(activeChecks(url))}

    <Link class={cn(isActive ? 'dock-active' : '')} aria-label={name} href={url}>
        <i class={cn('iconify size-6 ', icon)}></i>

        {#if withLabel}
            <span class="dock-label">{name}</span>
        {/if}
    </Link>
{/snippet}
