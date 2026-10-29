import type { BreadcrumbItem } from '@components/ui/breadcrumbs.svelte';
import type { Menu, MenuGroup, Submenu } from '@data/menu';

import { router } from '@inertiajs/svelte';
import { useUrlHandler } from '@lib/url-handler.svelte';

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

/**
 * Wires the client-side navigation tracker that `backOrRedirectTo()` reads.
 * Called once at app boot (see `app.ts`). Inertia's `navigate` event observes
 * SPA navigation (visits and history navigation), unlike `document.referrer`,
 * which is frozen at document load and never reflects SPA navigation.
 * `initial` events (the page that loaded the document) are ignored: the entry
 * behind them may be external.
 *
 * @returns Callback that detaches the listener and resets the tracker
 */
let previousUrl: string | null = null;
let currentUrl: string | null = null;

export function initializeBackNavigation(): () => void {
    currentUrl = window.location.pathname + window.location.search;

    return router.on('navigate', (event) => {
        if (event.detail.type === 'initial') return;

        previousUrl = currentUrl;
        currentUrl = event.detail.page.url;
    });
}

/**
 * Creates the back handler for layout `backUrl` props. The returned function
 * navigates back when the current tab has an in-app page behind it (restoring
 * scroll and remembered state via Inertia's popstate handling), otherwise
 * visits `fallbackUrl` (e.g. a canonical parent URL).
 *
 * Safe against double-taps: a second tap before the back navigation completes
 * falls back to a fresh visit instead of stepping back twice.
 *
 * @param fallbackUrl - URL visited when there is no in-app history to go back to
 * @returns Back handler invoked by the header's back button
 */
export function backOrRedirectTo(fallbackUrl: Parameters<(typeof router)['visit']>[0]): () => void {
    return () => {
        if (previousUrl === null) {
            router.visit(fallbackUrl);
            return;
        }

        previousUrl = null;
        history.back();
    };
}
