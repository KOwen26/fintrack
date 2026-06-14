import { route as routeFn } from 'ziggy-js';

// the package is not installed, we use directly from ./vendor/tightenco/ziggy/
// import type { Config, RouteParams } from 'ziggy-js';

// declare global {
// function route(): Config;
// function route(
//     name: string,
//     params?: RouteParams<typeof name> | undefined,
//     absolute?: boolean
// ): string;
// }

declare module 'ziggy-js' {
    // interface TypeConfig {
    //     strictRouteNames: true;
    // }
}

declare global {
    var route: typeof routeFn;
}
