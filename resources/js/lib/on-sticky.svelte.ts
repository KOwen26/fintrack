/**
 * Reusable Component Attachments
 *
 * @reference https://svelte.dev/docs/svelte/@attach
 */

/**
 * Toggles the given classes on a sticky element while it is stuck to the
 * viewport top, and removes them once it scrolls free again. Unlike a
 * scroll-position threshold, this follows the element itself, so it works
 * wherever the sticky element happens to sit in the layout.
 *
 * @example
 * ```svelte
 * <div {@attach onSticky({ class: 'border-base-300 shadow' })} class="sticky top-0 border-b border-transparent">
 * ```
 */
export function onSticky(options: { class: string }) {
    return (element: HTMLElement) => {
        const classList = options.class.split(' ').filter(Boolean);

        const update = () => {
            const isStuck = element.getBoundingClientRect().top <= 0.5;

            for (const cls of classList) {
                element.classList.toggle(cls, isStuck);
            }
        };

        update();
        window.addEventListener('scroll', update, { passive: true });

        return () => {
            window.removeEventListener('scroll', update);

            for (const cls of classList) {
                element.classList.remove(cls);
            }
        };
    };
}
