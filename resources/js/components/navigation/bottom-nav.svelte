<script lang="ts">
    import type { Menu } from '@data/menu';
    import type { App } from '@wayfinder/types';

    import { dashboardMenu } from '@data/menu';
    import { Link } from '@inertiajs/svelte';
    import { useUrlHandler } from '@lib/url-handler.svelte';
    import TransactionType from '@wayfinder/App/Enums/TransactionType';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import { cn } from '@utilities/shadcn';

    import Dock from '@components/ui/dock.svelte';
    import Popover from '@components/ui/popover.svelte';

    interface Props {
        withLabel?: boolean;
        fabVariant?: 'button' | 'popup';
    }

    let { withLabel = true, fabVariant = 'popup' }: Props = $props();

    let fabOpen = $state(false);

    const urlHandler = useUrlHandler();

    const activeChecks = $derived.by(
        () => (url: string) => urlHandler.isCurrentOrParentUrl(url, urlHandler.currentUrl)
    );

    interface FabQuickAction {
        type: App.Enums.TransactionType;
        label: string;
        icon: string;
        iconClass: string;
        tileClass: string;
    }

    const fabQuickActions: FabQuickAction[] = [
        {
            type: TransactionType.Expense,
            label: 'Expense',
            icon: 'solar--arrow-up-line-duotone',
            iconClass: 'text-error',
            tileClass: 'bg-error/10 group-hover/action:bg-error/20',
        },
        {
            type: TransactionType.Income,
            label: 'Income',
            icon: 'solar--arrow-down-line-duotone',
            iconClass: 'text-success',
            tileClass: 'bg-success/10 group-hover/action:bg-success/20',
        },
        {
            type: TransactionType.Transfer,
            label: 'Transfer',
            icon: 'solar--transfer-horizontal-bold-duotone',
            iconClass: 'text-info',
            tileClass: 'bg-info/10 group-hover/action:bg-info/20',
        },
    ];

    function closeFab(): void {
        fabOpen = false;
    }
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
        {#if fabVariant === 'button'}
            <div
                class="absolute -top-1 size-12 -translate-y-1/2 rounded-lg bg-primary p-2 text-primary-content">
                <Link
                    class="flex size-8 items-center justify-center"
                    aria-label="Add"
                    href={TransactionController.create.url()}>
                    <i class="iconify size-8 tabler--plus"></i>
                </Link>
            </div>
        {:else}
            <div
                class="absolute -top-1 size-12 -translate-y-1/2 rounded-lg bg-base-100 p-2 text-primary outline-4 outline-primary">
                <Popover
                    class="w-auto min-w-0 rounded-2xl border-base-content/10 bg-base-100 p-2 "
                    align="center"
                    side="top"
                    sideOffset={12}
                    bind:open={fabOpen}>
                    {#snippet trigger()}
                        <i
                            class={cn(
                                'iconify size-8 transition-transform duration-200 tabler--plus',
                                fabOpen && 'rotate-45'
                            )}></i>
                    {/snippet}

                    <div class="grid grid-cols-3 gap-1" aria-label="Quick actions" role="group">
                        {#each fabQuickActions as action (action.type)}
                            {@render fabAction(action)}
                        {/each}
                    </div>
                </Popover>
            </div>
        {/if}
    </div>

    {@render dockItem(dashboardMenu.menus.statistics)}

    {@render dockItem(dashboardMenu.menus.accounts)}
</Dock>

{#snippet dockItem({ name, url, icon }: Menu)}
    {let isActive = $derived(activeChecks(url))}

    <Link
        class={cn(
            'relative mb-2 flex h-full max-w-32 basis-full flex-col items-center justify-center gap-px rounded-lg transition-opacity',
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

{#snippet fabAction(action: FabQuickAction)}
    <Link
        class="group/action flex w-16 flex-col items-center gap-1.5 rounded-2xl p-2 transition-colors hover:bg-base-200/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
        aria-label={`Add ${action.label.toLowerCase()}`}
        href={TransactionController.create.url({ query: { type: action.type } })}
        onclick={closeFab}>
        <span
            class={cn(
                'flex size-10 items-center justify-center rounded-xl transition-colors',
                action.tileClass
            )}>
            <i class={cn('iconify size-5', action.icon, action.iconClass)}></i>
        </span>

        <span class="text-xs font-medium">{action.label}</span>
    </Link>
{/snippet}
