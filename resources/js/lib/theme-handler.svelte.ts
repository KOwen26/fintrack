import { router } from '@inertiajs/svelte';
import ProfileController from '@wayfinder/App/Http/Controllers/Settings/ProfileController';

// ──────────────────────────────────────────────
// Shared private helpers
// ──────────────────────────────────────────────

function setCookie(name: string, value: string, days = 365): void {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
}

// ──────────────────────────────────────────────
// Named Theme (cobalt, electric, azure, …)
// ──────────────────────────────────────────────

export interface ThemeOption {
    value: string;
    label: string;
}

/** Selectable themes — finance palettes first (rated), then the base app themes. */
export const THEME_OPTIONS: ThemeOption[] = [
    { value: 'electric', label: 'Electric — navy, lime & teal' },
    { value: 'electric-dark', label: 'Electric Dark — navy, lime & teal' },
    { value: 'azure', label: 'Azure — deep azure & cyan' },
    { value: 'azure-dark', label: 'Azure Dark — deep azure & cyan' },
    { value: 'mint', label: 'Mint — azure & jade' },
    { value: 'royal', label: 'Royal — navy & gold' },
    { value: 'royal-dark', label: 'Royal Dark — navy & gold' },
    { value: 'desert', label: 'Desert — earth & gold' },
    { value: 'cobalt', label: 'Cobalt (default)' },
    { value: 'verdant', label: 'Verdant' },
    { value: 'ember', label: 'Ember' },
];

export const THEMES = THEME_OPTIONS.map(({ value }) => value);

export type ThemeName = string;

export const DEFAULT_THEME = 'electric';

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
        ProfileController.updateTheme.url(),
        { theme: value },
        {
            preserveScroll: true,
            preserveState: true,
        }
    );
};

const DEFAULT_STATUS_BAR_COLOR = '#FDFDFC';

export function getCssColor(variableName: string, fallback = '#FDFDFC'): string {
    if (typeof window === 'undefined') return fallback;

    const value = getComputedStyle(document.documentElement).getPropertyValue(variableName).trim();

    return value || fallback;
}

export function setStatusBarTheme(color: string = DEFAULT_STATUS_BAR_COLOR): void {
    if (typeof window === 'undefined' || !document) return;

    const meta = document.querySelector<HTMLMetaElement>('meta[name="theme-color"]');

    if (meta) {
        meta.content = color;
    }
}

// ──────────────────────────────────────────────
// Appearance (light / dark / system)
// ──────────────────────────────────────────────

export type Appearance = 'light' | 'dark' | 'system';

export type ResolvedAppearance = 'light' | 'dark';

export type AppearanceState = {
    appearance: {
        value: Appearance;
    };
    resolvedAppearance: () => ResolvedAppearance;
    updateAppearance: (value: Appearance) => void;
};

const appearance = $state<{ value: Appearance }>({ value: 'system' });

let themeChangeMediaQuery: MediaQueryList | null = null;

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const isDarkMode = (value: Appearance): boolean => {
    return value === 'dark' || (value === 'system' && prefersDark());
};

const getResolvedAppearance = (): ResolvedAppearance => {
    return isDarkMode(appearance.value) ? 'dark' : 'light';
};

const applyAppearance = (value: Appearance): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const isDark = isDarkMode(value);
    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
};

const getStoredAppearance = (): Appearance => {
    if (typeof window === 'undefined') {
        return 'system';
    }

    const stored = localStorage.getItem('appearance');

    return stored === 'light' || stored === 'dark' || stored === 'system' ? stored : 'system';
};

const handleSystemThemeChange = (): void => {
    applyAppearance(appearance.value);
};

const detachThemeChangeListener = (): void => {
    if (!themeChangeMediaQuery) {
        return;
    }

    themeChangeMediaQuery.removeEventListener('change', handleSystemThemeChange);
    themeChangeMediaQuery = null;
};

export function initializeAppearance(): () => void {
    if (typeof window === 'undefined') {
        return () => {};
    }

    if (!localStorage.getItem('appearance')) {
        localStorage.setItem('appearance', 'system');
        setCookie('appearance', 'system');
    }

    appearance.value = getStoredAppearance();
    applyAppearance(appearance.value);

    detachThemeChangeListener();
    themeChangeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    themeChangeMediaQuery.addEventListener('change', handleSystemThemeChange);

    return detachThemeChangeListener;
}

export function updateAppearance(value: Appearance): void {
    appearance.value = value;

    if (typeof window !== 'undefined') {
        localStorage.setItem('appearance', value);
    }

    setCookie('appearance', value);
    applyAppearance(value);
}

export function appearanceState(): AppearanceState {
    return {
        appearance,
        resolvedAppearance: getResolvedAppearance,
        updateAppearance,
    };
}

export function themePreferenceFromProps(props: unknown): unknown {
    if (typeof props !== 'object' || props === null) {
        return undefined;
    }

    const auth = (props as { auth?: { user?: { theme_preference?: unknown } } }).auth;

    return auth?.user?.theme_preference;
}

export function initializeNamedTheme(serverPreference?: unknown): () => void {
    // Backend theme resolution disabled temporarily — localStorage resolves first,
    // the server preference only acts as a fallback on initial boot.
    theme.value = getStoredTheme() ?? asValidTheme(serverPreference) ?? DEFAULT_THEME;
    applyToDom(theme.value);
    setStoredTheme(theme.value);

    // const off = router.on('navigate', (event) => {
    //     const server = asValidTheme(themePreferenceFromProps(event.detail.page.props));

    //     if (server === undefined) {
    //         return;
    //     }

    //     theme.value = server;
    //     applyToDom(server);
    //     setStoredTheme(server);
    // }) as unknown as (() => void) | void;

    // return typeof off === 'function' ? off : () => {};
    return () => {};
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
