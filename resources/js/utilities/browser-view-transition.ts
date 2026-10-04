import { page, router } from '@inertiajs/svelte';

import { findRouteViewTransition, normalizePathname } from '@utilities/view-transition';

/**
 * Experimental browser history integration for Inertia view transitions.
 * Normal Inertia visits already animate through `withDirectionalViewTransition()`.
 */
type DocumentWithOptionalViewTransitions = Document & {
    startViewTransition?: (updateCallback: () => void | Promise<void>) => {
        finished: Promise<void>;
    };
};

const NAVIGATION_TIMEOUT_MS = 500;

let popstateHandler: (() => void) | null = null;

function pathnameOf(value: string): string {
    return normalizePathname(new URL(value, window.location.href).pathname);
}

function currentPathname(): string {
    return pathnameOf(page.url || window.location.href);
}

function destinationPathname(): string {
    return pathnameOf(window.location.href);
}

function waitForInertiaNavigation(pathname: string): Promise<void> {
    return new Promise<void>((resolve) => {
        let isFinished = false;
        let stopListening: () => void = () => {};
        // eslint-disable-next-line prefer-const
        let timeout: number | undefined;

        const finish = () => {
            if (isFinished) {
                return;
            }

            isFinished = true;
            window.clearTimeout(timeout);
            stopListening();
            resolve();
        };

        stopListening = router.once('navigate', () => {
            if (pathnameOf(page.url) === pathname) {
                finish();
            }
        });

        timeout = window.setTimeout(finish, NAVIGATION_TIMEOUT_MS);
    });
}

function handlePopstate(): void {
    const documentWithViewTransitions = document as DocumentWithOptionalViewTransitions;
    const startViewTransition = documentWithViewTransitions.startViewTransition;

    if (!startViewTransition || window.history.state === null) {
        return;
    }

    const from = currentPathname();
    const to = destinationPathname();
    const matchedTransition = findRouteViewTransition(from, to);

    if (!matchedTransition) {
        return;
    }

    document.documentElement.dataset.viewTransitionDirection = matchedTransition.direction;
    document.documentElement.dataset.viewTransitionStyle = matchedTransition.style;

    const transition = startViewTransition.call(document, async () => {
        await waitForInertiaNavigation(to);
    });

    transition.finished.finally(() => {
        delete document.documentElement.dataset.viewTransitionDirection;
        delete document.documentElement.dataset.viewTransitionStyle;
    });
}

export function initializeBrowserViewTransitions(): () => void {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return () => {};
    }

    if (popstateHandler) {
        return () => {};
    }

    popstateHandler = handlePopstate;
    window.addEventListener('popstate', popstateHandler, true);

    return () => {
        if (!popstateHandler) {
            return;
        }

        window.removeEventListener('popstate', popstateHandler, true);
        popstateHandler = null;
    };
}
