<script lang="ts">
    import type { Menu, MenuGroup, MenuGroups, Submenu } from '@data/menu';
    import type { AnyRecord, RestProps } from '@type/index';

    import { useUrlHandler } from '@/hooks/url-handler.svelte';
    import { inertia } from '@inertiajs/svelte';
    import { Collapsible } from 'bits-ui';

    import * as DropdownMenu from '@components/ui/atoms/dropdown-menu';
    import * as Sidebar from '@components/ui/atoms/sidebar';
    import FlexRender from '@components/ui/flex-render.svelte';

    const sidebar = Sidebar.useSidebar();

    interface Props extends RestProps {
        menus: MenuGroups;
    }

    let { menus }: Props = $props();

    const urlHandler = useUrlHandler();

    const isMenuActive = (menu: Menu | Submenu): boolean => {
        if (!menu.url) return false;

        return menu.active?.endsWith('.*')
            ? urlHandler.isCurrentOrParentUrl(menu.url, urlHandler.currentUrl)
            : urlHandler.isCurrentUrl(menu.url, urlHandler.currentUrl);
    };
</script>

{#each menus as item (item)}
    {#if item.type === 'dropdown'}
        {@render menuDropdownGroup(item)}
    {:else}
        {@render menuCollapsibleGroup(item)}
    {/if}
{/each}

{#snippet menuItem(menu: Menu, props: AnyRecord = {})}
    {@const useInertia = menu.url?.length && !menu.withoutInertia ? inertia : undefined}
    <a href={menu.url} {...props} use:useInertia>
        {@render menuIcon(menu.icon)}
        <span class="group-data-[collapsible=icon]:hidden">{menu.name}</span>
    </a>
{/snippet}

{#snippet menuIcon(icon: Menu['icon'])}
    {#if typeof icon === 'function'}
        <FlexRender content={() => icon} context={icon} />
    {:else if typeof icon === 'string' && icon?.length}
        <i class={['size-5 group-data-[collapsible=icon]:mx-auto!', icon]}></i>
    {/if}
{/snippet}

{#snippet menuCollapsibleGroup(menuGroup: MenuGroup)}
    <Sidebar.Group>
        <Sidebar.GroupLabel>{menuGroup.name}</Sidebar.GroupLabel>
        <Sidebar.Menu>
            {#each Object.values(menuGroup.menus) as group, i (i)}
                {@const isActive = isMenuActive(group)}
                {#if !Object.keys(group?.submenu ?? {}).length}
                    <Sidebar.MenuItem>
                        <Sidebar.MenuButton {isActive} tooltipContent={group.name}>
                            {#snippet child({ props })}
                                {@render menuItem(group, props)}
                            {/snippet}
                        </Sidebar.MenuButton>
                    </Sidebar.MenuItem>
                {:else}
                    <Collapsible.Root class="group/collapsible" open={isActive}>
                        {#snippet child({ props })}
                            <Sidebar.MenuItem {...props}>
                                <Collapsible.Trigger>
                                    {#snippet child({ props })}
                                        <Sidebar.MenuButton
                                            {...props}
                                            {isActive}
                                            tooltipContent={group.name}>
                                            {@render menuIcon(group.icon)}
                                            <span>{group.name}</span>
                                            <i
                                                class="ml-auto iconify transition-transform duration-200 ph--caret-right-duotone group-data-[state=open]/collapsible:rotate-90"
                                            ></i>
                                        </Sidebar.MenuButton>
                                    {/snippet}
                                </Collapsible.Trigger>
                                <Collapsible.Content>
                                    <Sidebar.MenuSub>
                                        {#each Object.values(group.submenu ?? {}) as subItem (subItem.name)}
                                            <Sidebar.MenuSubItem>
                                                {@const isActive = isMenuActive(subItem)}
                                                <Sidebar.MenuSubButton {isActive}>
                                                    {#snippet child({ props })}
                                                        {@render menuItem(subItem, props)}
                                                    {/snippet}
                                                </Sidebar.MenuSubButton>
                                            </Sidebar.MenuSubItem>
                                        {/each}
                                    </Sidebar.MenuSub>
                                </Collapsible.Content>
                            </Sidebar.MenuItem>
                        {/snippet}
                    </Collapsible.Root>
                {/if}
            {/each}
        </Sidebar.Menu>
    </Sidebar.Group>
{/snippet}

{#snippet menuDropdownGroup(menuGroup: MenuGroup)}
    <Sidebar.Group class="group-data-[collapsible=icon]:hidden">
        <Sidebar.GroupLabel>{menuGroup.name}</Sidebar.GroupLabel>
        <Sidebar.Menu>
            {#each Object.values(menuGroup.menus) as group (group.name)}
                {@const isActive = isMenuActive(group)}
                {#if !Object.keys(group?.submenu ?? {}).length}
                    <Sidebar.MenuItem>
                        <Sidebar.MenuButton {isActive} tooltipContent={group.name}>
                            {#snippet child({ props })}
                                {@render menuItem(group, props)}
                            {/snippet}
                        </Sidebar.MenuButton>
                    </Sidebar.MenuItem>
                {:else}
                    <Sidebar.MenuItem>
                        <DropdownMenu.Root>
                            <DropdownMenu.Trigger>
                                {#snippet child({ props })}
                                    <Sidebar.MenuButton {isActive} {...props}>
                                        {@render menuIcon(group.icon)}
                                        {group.name}
                                        <i
                                            class="ml-auto iconify transition-transform duration-200 ph--arrows-left-right-duotone group-data-[state=open]/collapsible:rotate-90"
                                        ></i>
                                    </Sidebar.MenuButton>
                                {/snippet}
                            </DropdownMenu.Trigger>
                            <DropdownMenu.Content
                                class="w-48 rounded-lg"
                                align={sidebar.isMobile ? 'end' : 'center'}
                                side={sidebar.isMobile ? 'bottom' : 'right'}>
                                {#each Object.values(group.submenu) as subItem (subItem.name)}
                                    <DropdownMenu.Item>
                                        {@render menuItem(subItem)}
                                    </DropdownMenu.Item>
                                {/each}
                            </DropdownMenu.Content>
                        </DropdownMenu.Root>
                    </Sidebar.MenuItem>
                {/if}
            {/each}
        </Sidebar.Menu>
    </Sidebar.Group>
{/snippet}
