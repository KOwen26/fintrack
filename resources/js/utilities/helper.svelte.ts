/**
 * Client side helper functions
 */

import type { Menu } from '@data/menu';

export const getTitleFromMenu = (menu: Menu[]) => {
    if (typeof window === 'undefined') return '';

    const currentMenu = menu.find((menu) => menu.url === window.location.href);
    if (currentMenu) return currentMenu.name;

    return '';
};

export type DebouncedFunction<T extends (...args: any[]) => any> = {
    (...args: Parameters<T>): void;
    cancel(): void;
};

/**
 * Creates a debounced function that delays invoking `fn` until after `wait` milliseconds
 * have elapsed since the last time the debounced function was invoked.
 *
 * @param fn - The function to debounce
 * @param wait - The number of milliseconds to delay (default: 500)
 * @returns Debounced function with `.cancel()` method
 */
export function debounce<T extends (...args: any[]) => any>(
    fn: T,
    wait: number = 500
): DebouncedFunction<T> {
    let timeout: ReturnType<typeof setTimeout> | null = null;

    const debounced = (...args: Parameters<T>): void => {
        if (timeout !== null) clearTimeout(timeout);

        timeout = setTimeout(() => {
            timeout = null;
            fn(...args);
        }, wait);
    };

    debounced.cancel = (): void => {
        if (timeout !== null) {
            clearTimeout(timeout);
            timeout = null;
        }
    };

    return debounced as DebouncedFunction<T>;
}

/**
 * Helper function to trigger Tailwind CSS IntelliSense
 *
 * @param strings - Template strings to join
 * @returns Joined template strings
 */
export const tw = (strings: TemplateStringsArray) => strings.join('');
