import type { ResolvedComponent } from '@inertiajs/svelte';

import { createInertiaApp } from '@inertiajs/svelte';
import createServer from '@inertiajs/svelte/server';
import { hydrate } from 'svelte';
import { render } from 'svelte/server';

import AppLayout from '@components/layouts/app-layout.svelte';

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
                layout: page?.layout || AppLayout,
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
