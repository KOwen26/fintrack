import type { App } from '@wayfinder/types';

import { page } from '@inertiajs/svelte';

export function useTheme() {
    const current = $derived(
        (page.props.auth?.user as App.Models.User | null)?.theme_preference ?? 'light'
    );

    $effect(() => {
        document.documentElement.dataset.theme = current;
    });

    return {
        get current() {
            return current;
        },
    };
}
