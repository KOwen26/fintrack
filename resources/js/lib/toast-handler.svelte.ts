import type { ToastT } from 'svelte-sonner';

import { router } from '@inertiajs/svelte';
import { toast } from 'svelte-sonner';

export type ToastProps = {
    type: ToastT['type'];
    message: string;
    details?: Array<string>;
};

export const showToast = (data: ToastProps) => {
    if (!data || !('type' in data) || !('message' in data)) return;

    const { type, message } = data;

    if (['success', 'info', 'warning', 'error'].includes(type)) {
        toast[type]?.(message);
    } else {
        toast(message);
    }
};

export function initializeFlashToast(): void {
    // Legacy method
    // const page = usePage();
    // const flash = $derived(page?.flash ?? page?.props?.flash);

    // $effect(() => {
    //     if (flash?.type && flash?.message) {
    //         showToast({ type: flash.type, message: flash.message });
    //     }
    // });

    router.on('flash', ({ detail }) => {
        const data = detail?.flash;

        showToast(data);
    });
}
