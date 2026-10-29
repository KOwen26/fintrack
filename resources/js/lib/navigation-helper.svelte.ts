import { router, setLayoutProps } from '@inertiajs/svelte';

/**
 * Wires the client-side navigation tracker that `backOrRedirectTo()` reads.
 * Called once at app boot (see `app.ts`). Inertia's `navigate` event observes
 * SPA navigation (visits and history navigation), unlike `document.referrer`,
 * which is frozen at document load and never reflects SPA navigation.
 * `initial` events (the page that loaded the document) are ignored: the entry
 * behind them may be external.
 *
 * @returns Callback that detaches the listener and resets the tracker
 */
let previousUrl: string | null = null;
let currentUrl: string | null = null;

export function initializeBackNavigation(): () => void {
    if (typeof window === 'undefined') {
        return () => {};
    }

    currentUrl = window.location.pathname + window.location.search;

    return router.on('navigate', (event) => {
        if (event.detail.type === 'initial') return;

        previousUrl = currentUrl;
        currentUrl = event.detail.page.url;
    });
}

/**
 * Creates the back handler for layout `backUrl` props. The returned function
 * navigates back when the current tab has an in-app page behind it (restoring
 * scroll and remembered state via Inertia's popstate handling), otherwise
 * visits `fallbackUrl` (e.g. a canonical parent URL).
 *
 * Safe against double-taps: a second tap before the back navigation completes
 * falls back to a fresh visit instead of stepping back twice.
 *
 * @param fallbackUrl - URL visited when there is no in-app history to go back to
 * @returns Back handler invoked by the header's back button
 */
export function backOrRedirectTo(fallbackUrl: Parameters<(typeof router)['visit']>[0]): () => void {
    return () => {
        if (previousUrl === null) {
            router.visit(fallbackUrl);

            return;
        }

        previousUrl = null;
        history.back();
    };
}

/**
 * Page-level `setLayoutProps()` with automatic cleanup on unmount.
 *
 * Inertia resets layout props automatically on navigation — except when
 * `preserveState` is enabled, which includes every form submission
 * (`router.post/put/patch/delete` default to it). The store also merges
 * instead of replacing, so props from the outgoing page leak into the next
 * page after those visits.
 *
 * The props are set inside `$effect` (not synchronously at init) and cleared
 * in its cleanup — the same set-then-clear convention the layout-slot
 * components (header context, bottom action bar, mobile page layout) follow.
 * Both halves are required: Svelte's BranchManager creates the incoming page
 * branch before destroying the outgoing one, so a synchronous init-time set
 * would be wiped by the outgoing page's cleanup one step later, while the
 * `$effect` body runs after that teardown — after the wipe, before the
 * layout renders.
 *
 * Must be called synchronously during page component initialization.
 */
export function pageLayoutProps(props: Record<string, unknown>): void {
    $effect(() => {
        setLayoutProps(props);

        return () => {
            setLayoutProps(Object.fromEntries(Object.keys(props).map((key) => [key, undefined])));
        };
    });
}
