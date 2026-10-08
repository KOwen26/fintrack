import type { VisitOptions } from '@inertiajs/core';

import { appearance, dashboard } from '@wayfinder/routes';
import profile from '@wayfinder/routes/profile';
import security from '@wayfinder/routes/security';
import settings from '@wayfinder/routes/settings';

type ViewTransitionDirection = 'forward' | 'back';

type ViewTransitionStyle = 'slide' | 'overlay' | 'push' | 'fade' | 'zoom';

type ViewTransitionCallback = NonNullable<VisitOptions['viewTransition']>;

const DIRECTION_ATTRIBUTE = 'viewTransitionDirection';
const STYLE_ATTRIBUTE = 'viewTransitionStyle';

type RouteViewTransition = {
    from: string;
    to: string;
    forward: ViewTransitionStyle;
    back: ViewTransitionStyle;
};

type MatchedViewTransition = {
    direction: ViewTransitionDirection;
    style: ViewTransitionStyle;
};

const DASHBOARD_ROUTE = routePathname(dashboard.url());
const SETTINGS_ROUTE = routePathname(settings.index.url());
const PROFILE_ROUTE = routePathname(profile.edit.url());
const SECURITY_ROUTE = routePathname(security.edit.url());
const APPEARANCE_ROUTE = routePathname(appearance.url());

const ROUTE_VIEW_TRANSITIONS: readonly RouteViewTransition[] = [
    {
        from: DASHBOARD_ROUTE,
        to: SETTINGS_ROUTE,
        forward: 'overlay',
        back: 'overlay',
    },
    {
        from: SETTINGS_ROUTE,
        to: PROFILE_ROUTE,
        forward: 'push',
        back: 'push',
    },
    {
        from: SETTINGS_ROUTE,
        to: SECURITY_ROUTE,
        forward: 'push',
        back: 'push',
    },
    {
        from: SETTINGS_ROUTE,
        to: APPEARANCE_ROUTE,
        forward: 'push',
        back: 'push',
    },
];

export function normalizePathname(pathname: string): string {
    return pathname.length > 1 ? pathname.replace(/\/+$/, '') : pathname;
}

function currentPathname(): string {
    if (typeof window === 'undefined') {
        return '';
    }

    return normalizePathname(window.location.pathname);
}

function targetPathname(href: string): string {
    if (typeof window === 'undefined') {
        return '';
    }

    try {
        return normalizePathname(new URL(href, window.location.href).pathname);
    } catch {
        return '';
    }
}

function routePathname(href: string): string {
    return normalizePathname(new URL(href, 'http://localhost').pathname);
}

export function directionalViewTransition(
    direction: ViewTransitionDirection,
    style: ViewTransitionStyle = 'overlay'
): ViewTransitionCallback {
    return (transition) => {
        const root = document.documentElement;

        root.dataset[DIRECTION_ATTRIBUTE] = direction;
        root.dataset[STYLE_ATTRIBUTE] = style;
        transition.finished.finally(() => {
            delete root.dataset[DIRECTION_ATTRIBUTE];
            delete root.dataset[STYLE_ATTRIBUTE];
        });
    };
}

export function withDirectionalViewTransition(href: string, options: VisitOptions): VisitOptions {
    const from = currentPathname();
    const to = targetPathname(href);

    const matchedTransition = findRouteViewTransition(from, to);

    if (matchedTransition) {
        return {
            ...options,
            viewTransition: directionalViewTransition(
                matchedTransition.direction,
                matchedTransition.style
            ),
        };
    }

    return options;
}

export function findRouteViewTransition(from: string, to: string): MatchedViewTransition | null {
    for (const routeTransition of ROUTE_VIEW_TRANSITIONS) {
        if (from === routeTransition.from && to === routeTransition.to) {
            return {
                direction: 'forward',
                style: routeTransition.forward,
            };
        }

        if (from === routeTransition.to && to === routeTransition.from) {
            return {
                direction: 'back',
                style: routeTransition.back,
            };
        }
    }

    return null;
}
