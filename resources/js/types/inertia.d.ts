import type { Permissions } from '@type/permission';

import '@inertiajs/core';

import type { ToastProps } from '@utilities/helper.svelte';

type Page = {
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

// Redeclare PageProps Interface
declare module '@inertiajs/core' {
    export interface PageProps extends Page {
        [key: string]: any;
    }

    export interface InertiaConfig {
        sharedPageProps: Omit<Page, 'flash'>;
        flashDataType: ToastProps;
        errorValueType: string[];
    }
}

export type FormSubmitOptions<TForm> = Parameters<InertiaFormProps<TForm>['submit']>[2];
