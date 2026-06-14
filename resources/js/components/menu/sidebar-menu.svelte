<script lang="ts">
    import type { Menu, MenuGroup } from '@/data/menu';

    import { dashboardMenu } from '@/data/menu';
    import { useUrlHandler } from '@/hooks/url-handler.svelte';
    import { inertia } from '@inertiajs/svelte';
    import { fly } from 'svelte/transition';

    import { sidebar } from '@states/reactive.svelte';

    let { menus = dashboardMenu }: { menus?: MenuGroup } = $props();

    const { currentUrl, isCurrentUrl } = useUrlHandler();

    let activeSubmenu = $state([]);

    const openSubmenu = (menu: Menu) => {
        if (!menu.submenu) return;

        if (sidebar.is_collapsed) sidebar.collapse();
        const index = menu.url;
        activeSubmenu[index] = !activeSubmenu[index];
    };
</script>

<ul class="text-sm text-accent-300">
    {#each Object.values(menus.menus) as menu (menu)}
        {#if menu.type === 'group-label'}
            {@render menuGroupLabel(menu)}
        {:else}
            {@render menuItem(menu)}
        {/if}
    {/each}
</ul>

{#snippet menuGroupLabel({ name }: Pick<Menu, 'name'>)}
    <li class="my-1">
        {#if sidebar.is_collapsed}
            <hr class="-mx-1 border-neutral-300" transition:fly />
        {:else}
            <span class="font-medium text-white">{name}</span>
        {/if}
    </li>
{/snippet}

{#snippet menuIcon(menu: Pick<Menu, 'type' | 'icon'>)}
    {@const isSubmenu = ['submenu-1', 'submenu-2'].includes(menu.type)}
    {#if isSubmenu}
        <div
            class={[
                'absolute top-0 h-full w-2.5 border-l border-accent',
                menu.type === 'submenu-1' ? 'left-6' : 'left-12',
            ]}>
        </div>
    {:else if typeof menu.icon === 'function'}
        {@render menu.icon?.()}
    {:else}
        <i class={['size-6 text-2xl', menu.icon]}></i>
    {/if}
{/snippet}

{#snippet menuContent(menu: Menu)}
    {@const isActive = menu.url && isCurrentUrl(menu.url, currentUrl)}
    {@const isSubmenu = ['submenu-1', 'submenu-2'].includes(menu.type)}
    <div class="px-2">
        {#if menu.type === 'submenu-2'}
            {@render menuIcon({ type: 'submenu-1' })}
        {/if}
        {@render menuIcon(menu)}
    </div>
    <span
        class={[
            'grow text-accent-400',
            isActive ? 'font-medium text-accent-200' : '',
            sidebar.is_collapsed ? 'line-clamp-2 text-center' : 'text-start',
            isSubmenu ? (menu.type === 'submenu-1' ? 'ml-6' : 'ml-12') : '',
        ]}>
        {menu.name}
    </span>
    {#if !sidebar.is_collapsed}
        {#if menu.submenu}
            <svg
                class="size-5 transition-transform duration-200"
                class:rotate-180={activeSubmenu[menu.url]}
                viewBox="0 0 24 24">
                <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" />
            </svg>
        {/if}
    {/if}
{/snippet}

{#snippet menuItem(menu: Menu)}
    {@const isSubmenu = menu.type === 'submenu-1' || menu.type === 'submenu-2'}
    {@const isLink = menu.url?.length > 0 && !menu?.submenu?.length}
    {@const inertiaLink = isLink ? inertia : undefined}
    {@const triggerClass = [
        'flex items-center w-full cursor-pointer rounded px-1 py-2 hover:bg-accent/20 relative',
        sidebar.is_collapsed ? 'flex-col  gap-1 text-xs' : 'h-10',
    ]}
    <li class={[!isSubmenu ? '-mx-1' : '']}>
        <svelte:element
            this={isLink ? 'a' : 'button'}
            class={triggerClass}
            href={isLink ? menu.url : undefined}
            onclick={isLink ? undefined : () => openSubmenu(menu)}
            role={isLink ? 'link' : 'button'}
            tabindex="0"
            use:inertiaLink>
            {@render menuContent(menu)}
        </svelte:element>

        {#if menu.submenu && !sidebar.is_collapsed && activeSubmenu[menu.url]}
            {@render menuSubmenu(menu)}
        {/if}
    </li>
{/snippet}

{#snippet menuSubmenu(menu: Menu)}
    <ul class="">
        {#each Object.values(menu.submenu) as submenu (submenu)}
            {@render menuItem(submenu)}
        {/each}
    </ul>
{/snippet}
