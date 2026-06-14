<script lang="ts">
    import { dashboard } from '@wayfinder/routes';
    import { Menubar } from 'bits-ui';

    import { sidebar } from '@states/reactive.svelte';

    interface MenuItem {
        id: number;
        label: string;
        icon: string;
        route?: string;
        submenu?: MenuItem[];
    }

    let activeSubmenu = $state(-1);

    const menus: MenuItem[] = [
        {
            id: 1,
            label: 'Dashboard',
            icon: 'https://placehold.co/24',
            route: dashboard.url(),
        },
        {
            id: 2,
            label: 'User Settings',
            icon: 'https://placehold.co/24',
            submenu: [
                {
                    id: 21,
                    label: 'Profile Picture',
                    icon: 'https://placehold.co/24',
                    route: dashboard.url(),
                },
                { id: 22, label: 'Account Setting', icon: 'https://placehold.co/24' },
            ],
        },
    ];

    const openSubmenu = (item: MenuItem) => {
        if (!item.submenu) return;

        // if (sidebar.is_collapsed) sidebar.collapse();
        activeSubmenu = activeSubmenu === item.id ? -1 : item.id;
    };
</script>

{#snippet menuContent(menu: MenuItem, isSubmenu = false)}
    {@const isLink = menu.route?.length > 0 && !sidebar.is_collapsed}
    <li
        class={[
            '-mx-1 flex flex-col',
            // !isSubmenu ? '-mx-1' : 'ml-[calc(--spacing(11))]', // 11 (10 + 1)
        ]}>
        <svelte:element
            this={isLink ? 'a' : 'button'}
            class={[
                'hover:bg-secondary/40 flex w-full cursor-pointer gap-x-[calc(--spacing(5.5))] rounded px-3 py-2',
                sidebar.is_collapsed ? 'flex-col items-center gap-1 text-xs' : '',
            ]}
            href={isLink ? menu.route : undefined}
            onclick={() => openSubmenu(menu)}
            role="button"
            tabindex="0">
            {#if !isSubmenu}
                <img class="size-6" alt="" src={menu.icon} />
            {:else}
                <div class="-my-2.5 ml-3.5 w-2.5 border-l-2 border-white"></div>
            {/if}
            <span class={['grow', sidebar.is_collapsed ? 'text-center' : 'text-start']}
                >{menu.label}</span>
            {#if !sidebar.is_collapsed}
                {#if menu.submenu}
                    <svg
                        class="size-5 transition-transform duration-200"
                        class:rotate-180={activeSubmenu === menu.id}
                        viewBox="0 0 24 24">
                        <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" />
                    </svg>
                {/if}
            {/if}
        </svelte:element>

        {#if menu.submenu && activeSubmenu === menu.id && !sidebar.is_collapsed}
            <ul class="mt-2 space-y-1">
                {#each menu.submenu as submenu (submenu)}
                    {@render menuItem(submenu, true)}
                {/each}
            </ul>
        {/if}
    </li>
{/snippet}

{#snippet menuItem(item: MenuItem, isSubmenu = false)}
    {@const contentClass = 'focus-override z-50 w-32 bg-white focus-visible:outline-hidden'}
    {@const menuClass = 'px-3.5 py-2 text-sm hover:bg-secondary/40'}
    <Menubar.Menu>
        <Menubar.Trigger class="w-full">
            {@render menuContent(item, isSubmenu)}
        </Menubar.Trigger>
        <Menubar.Portal>
            <Menubar.Content class={contentClass} align="start" side="right" sideOffset={20}>
                <Menubar.Item class={menuClass}>Undo</Menubar.Item>
                <Menubar.Separator />
                <Menubar.Sub>
                    <Menubar.SubTrigger
                        class={[
                            menuClass,
                            'data-highlighted:bg-secondary/20 data-[state=open]:bg-secondary/20',
                        ]}>
                        Find
                    </Menubar.SubTrigger>
                    <Menubar.SubContent
                        class={contentClass}
                        align="start"
                        side="right"
                        sideOffset={0}>
                        <Menubar.Item class={menuClass}>Search the web</Menubar.Item>
                        <Menubar.Item class={menuClass}>Find Previous</Menubar.Item>
                    </Menubar.SubContent>
                </Menubar.Sub>
            </Menubar.Content>
        </Menubar.Portal>
    </Menubar.Menu>
{/snippet}

<ul class="space-y-1 text-sm">
    {#if sidebar.is_collapsed}
        <Menubar.Root class="mx-0">
            {#each menus as menu (menu)}
                {@render menuItem(menu)}
            {/each}
        </Menubar.Root>
    {:else}
        {#each menus as menu (menu)}
            {@render menuContent(menu)}
        {/each}
    {/if}
</ul>
