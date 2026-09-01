<script lang="ts">
    import type { ComponentProps } from 'svelte';

    import DashboardSidebarMenu from '../menu/dashboard-sidebar-menu.svelte';

    import { menus } from '@data/menu';
    import { router, usePage } from '@inertiajs/svelte';
    import auth from '@wayfinder/routes/auth';

    import * as DropdownMenu from '@components/ui/atoms/dropdown-menu/index.js';
    import * as Sidebar from '@components/ui/atoms/sidebar/index.js';
    import { useSidebar } from '@components/ui/atoms/sidebar/index.js';

    let {
        ref = $bindable(null),
        collapsible = 'icon',
        side = 'left',
        ...restProps
    }: ComponentProps<typeof Sidebar.Root> = $props();

    const page = usePage();

    const user = $derived(page.props?.auth?.user);

    const sidebar = useSidebar();

    // The mobile drawer slides in from the right; desktop keeps the configured side.
    const resolvedSide = $derived(sidebar.isMobile ? 'right' : side);

    const handleLogout = () => {
        router.post(auth.logout().url);
    };
</script>

<Sidebar.Root {collapsible} side={resolvedSide} {...restProps}>
    <Sidebar.Header>
        <div class="p-2">
            <i class="iconify ph--map-trifold-duotone"></i>
        </div>
    </Sidebar.Header>
    <Sidebar.Content>
        <DashboardSidebarMenu {menus} />
    </Sidebar.Content>
    <Sidebar.Footer>
        {@render navigationUser(user)}
    </Sidebar.Footer>
    <Sidebar.Rail />
</Sidebar.Root>

{#snippet navigationUser(user)}
    <Sidebar.Menu>
        <Sidebar.MenuItem>
            <DropdownMenu.Root>
                <DropdownMenu.Trigger>
                    {#snippet child({ props })}
                        <Sidebar.MenuButton
                            class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                            size="lg"
                            {...props}>
                            <div class="avatar">
                                <div class="size-8 rounded-full bg-neutral text-neutral-content">
                                    <img alt={user.name} src={user.avatar} />
                                    <!-- <span class="text-3xl">{user.name}</span> -->
                                </div>
                            </div>
                            <div class="grid flex-1 text-left text-sm leading-tight">
                                <span class="truncate font-medium">{user.name}</span>
                                <span class="truncate text-xs">{user.email}</span>
                            </div>
                            <i class="ml-auto iconify size-4 ph--caret-up-down-duotone"></i>
                        </Sidebar.MenuButton>
                    {/snippet}
                </DropdownMenu.Trigger>
                <DropdownMenu.Content
                    class="w-(--bits-dropdown-menu-anchor-width) min-w-56 rounded-lg"
                    align="end"
                    side={sidebar.isMobile ? 'bottom' : 'right'}
                    sideOffset={4}>
                    <DropdownMenu.Label class="p-0 font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                            <div class="avatar">
                                <div class="size-8 rounded-full bg-neutral text-neutral-content">
                                    <img alt={user.name} src={user.avatar} />
                                    <!-- <span class="text-3xl">{user.name}</span> -->
                                </div>
                            </div>
                            <div class="grid flex-1 text-left text-sm leading-tight">
                                <span class="truncate font-medium">{user.name}</span>
                                <span class="truncate text-xs">{user.email}</span>
                            </div>
                        </div>
                    </DropdownMenu.Label>
                    <DropdownMenu.Separator />
                    <DropdownMenu.Group>
                        <DropdownMenu.Item>
                            <i class="iconify ph--sparkle-duotone"></i>
                            Upgrade to Pro
                        </DropdownMenu.Item>
                    </DropdownMenu.Group>
                    <DropdownMenu.Separator />
                    <DropdownMenu.Group>
                        <DropdownMenu.Item>
                            <i class="iconify ph--seal-check-duotone"></i>
                            Account
                        </DropdownMenu.Item>
                        <DropdownMenu.Item>
                            <i class="iconify ph--credit-card-duotone"></i>
                            Billing
                        </DropdownMenu.Item>
                        <DropdownMenu.Item>
                            <i class="iconify ph--bell-duotone"></i>
                            Notifications
                        </DropdownMenu.Item>
                    </DropdownMenu.Group>
                    <DropdownMenu.Separator />
                    <DropdownMenu.Item onclick={handleLogout}>
                        <i class="iconify ph--sign-out-duotone"></i>
                        Log out
                    </DropdownMenu.Item>
                </DropdownMenu.Content>
            </DropdownMenu.Root>
        </Sidebar.MenuItem>
    </Sidebar.Menu>
{/snippet}
