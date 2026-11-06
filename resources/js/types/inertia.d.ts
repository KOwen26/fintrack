import type { Permissions } from '@type/permission';

import '@inertiajs/core';

import type { BottomActionBarContext } from './bottom-action-bar';
import type { HeaderContext } from './header-context';
import type { BreadcrumbItem } from '@components/ui/breadcrumbs.svelte';
import type { ToastProps } from '@lib/toast-handler.svelte';

export type Page = {
    csrf_token?: string;
    auth?: {
        user?: {
            name: string;
            email: string;
        };
        permissions?: Permissions[];
    };
    flash?: ToastProps;
    meta: Partial<{
        title: string;
        app_name: string;
        current_route: string;
    }>;
};

export type LayoutProps = {
    title?: string;
    backUrl?: string;
    breadcrumbs?: BreadcrumbItem[];
    headerContext?: HeaderContext;
    /** Page-declared bottom action bar; replaces the mobile dock while set. */
    bottomActionBar?: BottomActionBarContext;

    hideBottomNav?: boolean;

    /** Page-declared classes for the mobile shell, e.g. background color or top offset. */
    mobileShellClass?: string;
    /** Page-declared classes for the mobile header, e.g. sticky positioning. */
    mobileHeaderClass?: string;
    /** Classes applied to the mobile header once scroll passes the threshold. */
    mobileHeaderScrolledClass?: string;
    /** Scroll position (px) that flips on mobileHeaderScrolledClass. Default 12. */
    mobileHeaderScrollThreshold?: number;
};

// Redeclare PageProps Interface
declare module '@inertiajs/core' {
    export interface PageProps extends Page {
        [key: string]: any;
    }

    export interface InertiaConfig {
        sharedPageProps: Omit<Page, 'flash'>;
        flashDataType: ToastProps;
        errorValueType: string[];
        layoutProps: LayoutProps;
    }
}

export type FormSubmitOptions<TForm> = Parameters<InertiaFormProps<TForm>['submit']>[2];
