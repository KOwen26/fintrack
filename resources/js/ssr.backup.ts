import type { ResolvedComponent } from '@inertiajs/svelte';

import { createInertiaApp } from '@inertiajs/svelte';
import createServer from '@inertiajs/svelte/server';
import { hydrate } from 'svelte';
import { render } from 'svelte/server';

import DashboardLayout from '@components/layouts/dashboard-layout.svelte';

/**
 * ! Ziggy route is not working for svelte SSR at the moment.
 * * https://github.com/tightenco/ziggy-js/issues/75
 *
 * might use laravel-wayfinder instead
 * ? https://github.com/laravel/wayfinder
 */
createServer((page) =>
    createInertiaApp({
        page,
        resolve: async (name) => {
            const pages = import.meta.glob<ResolvedComponent>('./pages/**/*.svelte', {
                eager: false,
            });
            const page = await pages[`./pages/${name}.svelte`]();

            return {
                default: page.default,
                layout: page?.layout || DashboardLayout,
            } as typeof page;
        },
        setup({ el, App, props }) {
            if (el?.dataset?.serverRendered === 'true') {
                hydrate(App, { target: el, props });
            } else {
                render(App, { props });
            }
        },
    })
);
