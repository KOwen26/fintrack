import '@inertiajs/core';

import type { HeaderContext } from './header-context';
import type { BreadcrumbItem } from '@components/ui/breadcrumbs.svelte';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        layoutProps: {
            title?: string;
            backUrl?: string;
            breadcrumbs?: BreadcrumbItem[];
            headerContext?: HeaderContext;
        };
    }
}
