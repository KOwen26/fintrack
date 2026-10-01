<script lang="ts">
    import type { Menu } from '@data/menu';

    import { dashboardMenu } from '@data/menu';
    import { Link } from '@inertiajs/svelte';
    import { useUrlHandler } from '@lib/url-handler.svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import { cn } from '@utilities/shadcn';

    import Dock from '@components/ui/dock.svelte';

    interface Props {
        withLabel?: boolean;
    }

    let { withLabel = true }: Props = $props();

    const urlHandler = useUrlHandler();

    const activeChecks = $derived((url) =>
        urlHandler.isCurrentOrParentUrl(url, urlHandler.currentUrl)
    );
</script>

<Dock
    class="md:hidden"
    contentClass="flex items-stretch justify-around"
    aria-label="Main"
    role="navigation"
    variant="flat">
    {@render dockItem(dashboardMenu.menus.dashboard)}

    {@render dockItem(dashboardMenu.menus.transactions)}

    <div class="relative flex basis-full items-center justify-center">
        <div
            class="min-size-12 absolute -top-1 -translate-y-1/2 rounded-lg bg-primary p-2 text-primary-content">
            <Link
                class="flex size-8 items-center justify-center"
                aria-label="Add"
                href={TransactionController.create.url()}>
                <i class="iconify size-8 tabler--plus"></i>
            </Link>
        </div>
    </div>

    {@render dockItem(dashboardMenu.menus.reports)}

    {@render dockItem(dashboardMenu.menus.accounts)}
</Dock>

{#snippet dockItem({ name, url, icon }: Menu)}
    {let isActive = $derived(activeChecks(url))}

    <Link
        class={cn(
            'relative mb-2 flex h-full max-w-32 basis-full flex-col items-center justify-center gap-px rounded-box transition-opacity',
            isActive ? 'text-primary' : 'text-base-content/60 hover:opacity-80'
        )}
        aria-current={isActive ? 'page' : undefined}
        aria-label={name}
        href={url}>
        <i class={cn('iconify size-6 ', icon)}></i>

        {#if withLabel}
            <span class="text-xs">{name}</span>
        {/if}

        {#if isActive}
            <span class="absolute bottom-0 h-1 w-10 rounded-full bg-current"></span>
        {/if}
    </Link>
{/snippet}
