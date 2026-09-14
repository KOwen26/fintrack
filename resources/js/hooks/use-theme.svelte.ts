import { router } from '@inertiajs/svelte';
import UserThemeController from '@wayfinder/App/Http/Controllers/UserThemeController';

export const THEMES = ['verdant', 'cobalt', 'ember', 'amethyst'] as const;

export type ThemeName = (typeof THEMES)[number] | (string & {});

export const DEFAULT_THEME = 'cobalt';

const STORAGE_KEY = 'fintrack-theme';

function asValidTheme(value: unknown): string | undefined {
    if (typeof value === 'string' && (THEMES as readonly string[]).includes(value)) {
        return value;
    }

    return undefined;
}

export function resolveTheme(preference: unknown): string {
    return asValidTheme(preference) ?? DEFAULT_THEME;
}

export type ThemeState = {
    theme: {
        value: string;
    };
    updateTheme: (value: string) => void;
};

const theme = $state<{ value: string }>({ value: DEFAULT_THEME });

const applyToDom = (value: string): void => {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.dataset.theme = value;
};

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredTheme = (): string | undefined => {
    if (typeof window === 'undefined') {
        return undefined;
    }

    return asValidTheme(window.localStorage.getItem(STORAGE_KEY));
};

const setStoredTheme = (value: string): void => {
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(STORAGE_KEY, value);
    }

    setCookie(STORAGE_KEY, value);
};

const persistServerTheme = (value: string): void => {
    router.put(
        UserThemeController.update.url(),
        { theme: value },
        {
            preserveScroll: true,
            preserveState: true,
        }
    );
};

export function themePreferenceFromProps(props: unknown): unknown {
    if (typeof props !== 'object' || props === null) {
        return undefined;
    }

    const auth = (props as { auth?: { user?: { theme_preference?: unknown } } }).auth;

    return auth?.user?.theme_preference;
}

export function initializeTheme(serverPreference?: unknown): () => void {
    theme.value = asValidTheme(serverPreference) ?? getStoredTheme() ?? DEFAULT_THEME;
    applyToDom(theme.value);
    setStoredTheme(theme.value);

    const off = router.on('navigate', (event) => {
        const server = asValidTheme(themePreferenceFromProps(event.detail.page.props));

        if (server === undefined) {
            return;
        }

        theme.value = server;
        applyToDom(server);
        setStoredTheme(server);
    }) as unknown as (() => void) | void;

    return typeof off === 'function' ? off : () => {};
}

export function updateTheme(value: string): void {
    const resolved = resolveTheme(value);
    theme.value = resolved;
    applyToDom(resolved);
    setStoredTheme(resolved);
    persistServerTheme(value);
}

export function themeState(): ThemeState {
    return {
        theme,
        updateTheme,
    };
}
