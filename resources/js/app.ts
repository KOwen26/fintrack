import './bootstrap';

import type { ResolvedComponent } from '@inertiajs/svelte';

import { createInertiaApp } from '@inertiajs/svelte';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

import DashboardLayout from '@components/layouts/dashboard-layout.svelte';

const appName = import.meta.env?.VITE_APP_NAME || 'Fintrack';

createInertiaApp({
    progress: {
        color: 'var(--color-primary)',
    },
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.svelte`,
            import.meta.glob<ResolvedComponent>('./pages/**/*.svelte')
        ),
    layout: (name) => {
        switch (true) {
            case name.startsWith('accounts'):
            case name.startsWith('transactions'):
            case name.startsWith('categories'):
            case name.startsWith('household'):
            case name.startsWith('settings/theme'):
            case name.startsWith('reports'):
            case name.startsWith('dev'):
            case name.startsWith('dashboard'):
                return DashboardLayout;

            default:
                return null;
        }
    },
});
