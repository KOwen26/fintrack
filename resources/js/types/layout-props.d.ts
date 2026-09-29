import '@inertiajs/core';

import type { BottomActionBarContext } from './bottom-action-bar';
import type { HeaderContext } from './header-context';
import type { BreadcrumbItem } from '@components/ui/breadcrumbs.svelte';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        layoutProps: {
            title?: string;
            backUrl?: string;
            breadcrumbs?: BreadcrumbItem[];
            headerContext?: HeaderContext;
            /** Page-declared bottom action bar; replaces the mobile dock while set. */
            bottomActionBar?: BottomActionBarContext;
            /** Page-declared classes for the mobile shell, e.g. background color or top offset. */
            mobileShellClass?: string;
            /** Page-declared classes for the mobile header, e.g. sticky positioning. */
            mobileHeaderClass?: string;
            /** Classes applied to the mobile header once scroll passes the threshold. */
            mobileHeaderScrolledClass?: string;
            /** Scroll position (px) that flips on mobileHeaderScrolledClass. Default 12. */
            mobileHeaderScrollThreshold?: number;
        };
    }
}
