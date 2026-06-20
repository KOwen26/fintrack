import type { BreadcrumbItem } from '@components/ui/breadcrumbs.svelte';
import type { Permissions } from '@utilities/authorization.svelte';
import type { Component, Snippet } from 'svelte';

import { appearance, dashboard } from '@wayfinder/routes';
import accounts from '@wayfinder/routes/accounts';
import dev from '@wayfinder/routes/dev';
import profile from '@wayfinder/routes/profile';
import security from '@wayfinder/routes/security';
import transactions from '@wayfinder/routes/transactions';

import { can } from '@utilities/authorization.svelte';

export type MenuType = 'group-label' | 'menu' | 'submenu-1' | 'submenu-2';

export type Menu = {
    id?: string;
    name: string;
    url?: string;
    route?: string;
    active?: string;
    icon?: string | Component | Snippet;
    type?: MenuType;
    submenu?: Record<string, Submenu>;
    withoutInertia?: boolean;
    permissions?: Permissions | Permissions[];
};

export type Submenu = Omit<Menu, 'icon'>;

export type MenuGroup = {
    name?: string;
    menus: Record<string, Menu>;
    type?: 'collapsible' | 'popover';
};

export type MenuGroups = MenuGroup[];

const icon = 'iconify solar--widget-5-line-duotone';

const devMenu = {
    name: 'Dev',
    type: 'collapsible',
    menus: {
        design: {
            name: 'Design',
            url: dev.design().url,
            route: 'dev.design',
            active: 'dev.design',
            icon: 'iconify solar--widget-5-line-duotone',
            type: 'menu',
        },
        colors: {
            name: 'Colors',
            url: dev.color().url,
            route: 'dev.color',
            active: 'dev.color',
            icon: 'iconify solar--widget-5-line-duotone',
            type: 'menu',
        },
        form: {
            name: 'Form & Inputs',
            url: dev.form().url,
            route: 'dev.form',
            active: 'dev.form',
            icon: 'iconify solar--widget-5-line-duotone',
            type: 'menu',
        },
        contoh_halaman: {
            name: 'Contoh Halaman',
            active: 'dev.design.*',
            icon: 'iconify solar--widget-5-line-duotone',
            type: 'submenu-1',
            submenu: {
                contoh: {
                    name: 'Contoh Halaman',
                    url: dev.design().url,
                    route: 'dev.design',
                    active: 'dev.design',
                    type: 'submenu-2',
                },
                contoh_detail: {
                    name: 'Contoh Detail Halaman',
                    url: dev.design().url,
                    route: 'dev.design',
                    active: 'dev.design',
                    type: 'submenu-2',
                },
            },
        },
    },
} satisfies MenuGroup;

export const dashboardMenu = {
    name: 'Dashboard',
    type: 'popover',
    menus: {
        dashboard: {
            name: 'Dashboard',
            url: dashboard.url(),
            active: 'dashboard',
            icon: 'iconify solar--widget-5-line-duotone',
            route: 'dashboard',
            type: 'menu',
        },
    },
} satisfies MenuGroup;

export const settingsMenu = {
    name: 'Settings',
    type: 'collapsible',
    menus: {
        profile: {
            name: 'Profile',
            url: profile.edit().url,
            route: 'profile.edit',
            active: 'profile.*',
            icon: 'iconify ph--user-duotone',
            type: 'menu',
        },
        security: {
            name: 'Security',
            url: security.edit().url,
            route: 'security.edit',
            active: 'security.*',
            icon: 'iconify ph--lock-key-duotone',
            type: 'menu',
        },
        appearance: {
            name: 'Appearance',
            url: appearance().url,
            route: 'appearance',
            active: 'appearance',
            icon: 'iconify ph--paint-brush-duotone',
            type: 'menu',
        },
    },
} satisfies MenuGroup;

export const homeMenu: MenuGroup = {
    name: 'Accounts',
    type: 'collapsible',
    menus: {
        accounts: {
            name: 'Accounts',
            url: accounts.index().url,
            route: 'accounts.edit',
            active: 'accounts.*',
            icon: 'iconify ph--user-duotone',
            type: 'menu',
        },
        transactions: {
            name: 'Transactions',
            url: transactions.index().url,
            route: 'transactions.edit',
            active: 'transactions.*',
            icon: 'iconify ph--user-duotone',
            type: 'menu',
        },
    },
};

export const menus: MenuGroups = [dashboardMenu, homeMenu, settingsMenu];

const isDev = ['local', 'staging'].includes(import.meta.env?.VITE_APP_ENV || 'local');
if (isDev) {
    // menus.push(devMenu);
}

function menuItemsOf(group: MenuGroup): Menu[] {
    return Object.values(group.menus);
}

export const flatMenu = menus.reduce((acc, group) => {
    menuItemsOf(group).forEach((menu) => {
        acc.push(menu);
        if (menu.submenu) {
            Object.values(menu.submenu).forEach((submenu) => {
                acc.push(submenu);
                if (submenu.submenu) {
                    Object.values(submenu.submenu).forEach((subsubmenu) => {
                        acc.push(subsubmenu);
                    });
                }
            });
        }
    });

    return acc;
}, [] as Menu[]);

export const transformMenuToBreadcrumbs = (menu_groups: MenuGroups = menus) => {
    interface BreadcrumbItemWithRoute extends BreadcrumbItem {
        route?: string;
    }

    const createItem = (
        item: Menu | Submenu,
        override_route?: string
    ): BreadcrumbItemWithRoute => ({
        title: item.name,
        href: item.url,
        route: override_route ?? item.route,
    });

    return (menu_groups ?? []).flatMap((group) => {
        const items = menuItemsOf(group);
        if (!items.length) return [];

        const has_multiple_menus = items.length > 1;
        const group_item = { ...createItem(items[0]), title: group.name };

        return items.map((menu) => {
            const menu_item = createItem(menu);
            const sub_items = menu.submenu
                ? Object.values(menu.submenu).map((sub) => [menu_item, createItem(sub, menu.route)])
                : [];

            const breadcrumbs = [
                ...(has_multiple_menus ? [group_item] : []),
                menu_item,
                ...sub_items,
            ] as BreadcrumbItemWithRoute[];

            breadcrumbs[0].href = undefined;

            const last_item = breadcrumbs.at(-1);
            const name = Array.isArray(last_item) ? last_item.at(-1)?.title : last_item?.title;

            return { name, breadcrumbs };
        });
    });
};

export const filterMenuByPermissions = (menu_groups: MenuGroups): MenuGroups =>
    menu_groups
        .map((group) => {
            const filtered = Object.entries(group.menus).reduce(
                (acc, [key, menu]) => {
                    if (!menu?.permissions || can(menu.permissions)) {
                        acc[key] = menu;
                    }

                    return acc;
                },
                {} as Record<string, Menu>
            );

            if (!Object.keys(filtered).length) return;

            return {
                ...group,
                menus: checkSubmenuPermission(filtered),
            };
        })
        .filter(Boolean);

const checkSubmenuPermission = (menus: Record<string, Menu>): Record<string, Menu> =>
    Object.entries(menus).reduce(
        (acc, [key, menu]) => {
            if (!menu.submenu) {
                acc[key] = menu;

                return acc;
            }

            const submenu = Object.entries(menu.submenu).reduce(
                (sacc, [skey, sub]) => {
                    if (!sub?.permissions || can(sub.permissions)) {
                        sacc[skey] = sub;
                    }

                    return sacc;
                },
                {} as Record<string, Submenu>
            );

            if (Object.keys(submenu).length) {
                acc[key] = { ...menu, submenu };
            }

            return acc;
        },
        {} as Record<string, Menu>
    );

export type MenuKeyDotNotation<TGroup extends MenuGroup> = {
    [K in keyof TGroup['menus']]: K extends string
        ? TGroup['menus'][K]['submenu'] extends Record<string, any>
            ? `${K}` | `${K}.${Extract<keyof TGroup['menus'][K]['submenu'], string>}`
            : `${K}`
        : never;
}[keyof TGroup['menus']];

export const buildBreadcrumbFromMenu = <TGroup extends MenuGroup>(
    group: TGroup,
    menu_key: MenuKeyDotNotation<TGroup>,
    appended: BreadcrumbItem[] = []
): BreadcrumbItem[] => {
    const [main_key, sub_key] = menu_key.split('.');

    const menu = group.menus[main_key];

    const group_item: BreadcrumbItem = {
        title: group.name ?? '',
        href: menu.url,
    };

    const menu_item: BreadcrumbItem = {
        title: menu.name,
        href: menu.url,
    };

    const breadcrumbs = [group_item, menu_item];

    if (sub_key && menu.submenu && menu.submenu[sub_key]) {
        const submenu = menu.submenu[sub_key];
        breadcrumbs.push({
            title: submenu.name,
            href: submenu.url,
        });
    }

    return [...breadcrumbs, ...appended];
};
