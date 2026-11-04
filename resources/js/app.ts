import './bootstrap';

import type { ResolvedComponent } from '@inertiajs/svelte';

import { createInertiaApp } from '@inertiajs/svelte';
import { initializeBackNavigation } from '@lib/navigation-helper.svelte';
import { initializeNamedTheme } from '@lib/theme-handler.svelte';
import { initializeFlashToast } from '@lib/toast-handler.svelte';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

import { withDirectionalViewTransition } from '@utilities/view-transition';

import AppLayout from '@components/layouts/app-layout.svelte';

const appName = import.meta.env?.VITE_APP_NAME || 'Money Mate';

createInertiaApp({
    progress: {
        color: 'var(--color-primary)',
    },
    defaults: {
        visitOptions: (href, options) => withDirectionalViewTransition(href, options),
    },
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.svelte`,
            import.meta.glob<ResolvedComponent>('./pages/**/*.svelte')
        ),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth'):
                return null;

            default:
                return AppLayout;
        }
    },
});

initializeNamedTheme();

initializeFlashToast();

initializeBackNavigation();
