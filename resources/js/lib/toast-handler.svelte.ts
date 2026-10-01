import type { ToastT } from 'svelte-sonner';

import { page, router } from '@inertiajs/svelte';
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

export function useFlashToast() {
    $effect(() => {
        const flash = (page.props as Record<string, any>)?.flash as
            { type?: string; message?: string } | undefined;

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
