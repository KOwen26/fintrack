<script lang="ts">
    import type { Menu, MenuGroup } from '@/data/menu';

    import { settingsMenu } from '@data/menu';
    import { useUrlHandler } from '@lib/url-handler.svelte';

    import Link from '@components/ui/link.svelte';

    let { menus = settingsMenu }: { menus?: MenuGroup } = $props();

    const { currentUrl, isCurrentUrl } = useUrlHandler();
</script>

<div class="space-y-3">
    <h6 class="text-sm font-semibold tracking-wide">{menus?.name}</h6>

    <ul class="grid text-sm">
        {#each Object.values(menus.menus) as menu (menu.route ?? menu.name)}
            {@render menuItem(menu)}

            <hr class="my-1 border-border last:hidden" />
        {/each}
    </ul>
</div>

{#snippet menuIcon(menu: Pick<Menu, 'icon'>)}
    {#if typeof menu.icon === 'function'}
        {@render menu?.icon()}
    {:else if typeof menu.icon === 'string'}
        <i class={['iconify size-5', menu.icon]} aria-hidden="true"></i>
    {/if}
{/snippet}

{#snippet menuContent(menu: Menu)}
    <span class="flex min-w-0 items-center gap-3">
        {@render menuIcon(menu)}

        <span class="min-w-0 text-sm font-semibold">{menu.name}</span>
    </span>

    <i class="iconify size-5 shrink-0 text-neutral solar--alt-arrow-right-linear" aria-hidden="true"
    ></i>
{/snippet}

{#snippet menuItem(menu: Menu)}
    {@const isActive = menu.url && isCurrentUrl(menu.url, currentUrl)}
    <li>
        <Link
            class={[
                'flex h-12 w-full items-center justify-between rounded-xl px-4 py-3 text-left transition-colors hover:bg-base-200 focus:bg-base-200 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                isActive ? 'bg-base-200' : '',
            ]}
            aria-current={isActive ? 'page' : undefined}
            href={menu.url}>
            {@render menuContent(menu)}
        </Link>
    </li>
{/snippet}
