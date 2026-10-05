import './bootstrap';

import type { ResolvedComponent } from '@inertiajs/svelte';

import { createInertiaApp } from '@inertiajs/svelte';
import { initializeNamedTheme } from '@lib/theme-handler.svelte';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

import { withDirectionalViewTransition } from '@utilities/view-transition';

import DashboardLayout from '@components/layouts/dashboard-layout.svelte';

const appName = import.meta.env?.VITE_APP_NAME || 'Fintrack';

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
                return DashboardLayout;
        }
    },
});

initializeNamedTheme();
// initializeAppearance();
