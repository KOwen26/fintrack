import type { ToastProps } from '@utilities/helper.svelte';

import { page, router } from '@inertiajs/svelte';
import { toast } from 'svelte-sonner';

import { showToast } from '@utilities/helper.svelte';

export function useFlashToast() {
    $effect(() => {
        const flash = (page.props as Record<string, any>)?.flash as
            | { type?: string; message?: string }
            | undefined;

        if (flash?.type && flash?.message) {
            showToast({ type: flash.type as any, message: flash.message });
        }
    });
}

export function initializeFlashToast(): void {
    router.on('flash', ({ detail }) => {
        const flash = detail?.flash;
        const data = flash?.toast as ToastProps;

        if (!data) {
            return;
        }

        toast[data.type](data.message);
    });
}
