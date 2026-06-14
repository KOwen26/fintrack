import type { BreadcrumbItem } from '@components/ui/breadcrumbs.svelte';
import type { Menu, MenuGroup, Submenu } from '@data/menu';

import { useUrlHandler } from '@/hooks/url-handler.svelte';

/**
 * @deprecated
 *
 *  WARNING: This function is still POC
 *
 * Transforms a specific MenuGroup into a sequence of BreadcrumbItems
 * by finding the active path based on current URL.
 */
export const transformMenuToBreadcrumbs = (
    group: MenuGroup,
    extras: BreadcrumbItem[] = []
): BreadcrumbItem[] => {
    const items: BreadcrumbItem[] = [];
    const { currentUrl, isCurrentUrl, isCurrentOrParentUrl } = useUrlHandler();

    // 1. Root: Add the Group Label (e.g., "Data Siswa")
    // if (group.name) {
    //     items.push({
    //         title: group.name,
    //         // Labels usually aren't clickable links
    //     });
    // }

    // 2. Hierarchical Search: Find the active path
    const findActivePath = (list: (Menu | Submenu)[]): BreadcrumbItem[] => {
        for (const item of list) {
            const isActive =
                item.url &&
                (item.active?.endsWith('.*')
                    ? isCurrentOrParentUrl(item.url, currentUrl)
                    : isCurrentUrl(item.url, currentUrl));
            if (isActive) {
                const breadcrumb: BreadcrumbItem = {
                    title: item.name,
                    href: item.route,
                    // Map icon - Breadcrumbs.svelte handles strings (iconify) and snippets
                    // icon: typeof (item as Menu).icon === 'string' ? (item as Menu).icon : undefined,
                };
                // Recurse into submenus to find deeper active matches
                if (item.submenu) {
                    const subPath = findActivePath(item.submenu);
                    if (subPath.length > 0) {
                        return [breadcrumb, ...subPath];
                    }
                }

                return [breadcrumb];
            }
        }

        return [];
    };

    console.log({ items, active: findActivePath(group.menus), extras });

    // 3. Combine Group + Active Path + Optional Page Extras
    return [...items, ...findActivePath(group.menus), ...extras];
};
