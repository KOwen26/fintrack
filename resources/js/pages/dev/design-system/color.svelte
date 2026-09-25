<script lang="ts">
    import { themeState } from '@hooks/use-theme.svelte';
    import { tick } from 'svelte';

    import { setBreadcrumbItems } from '@utilities/global-states.svelte';

    import ThemeSelector from '@components/ui/theme-selector.svelte';

    setBreadcrumbItems([{ title: 'Dev' }, { title: 'Design System' }, { title: 'Color' }]);

    /* ── Theme ───────────────────────────────────────────── */

    const { theme } = themeState();

    const brandColors = ['primary', 'secondary', 'accent'] as const;
    const statusColors = ['success', 'info', 'warning', 'error'] as const;

    /* Literal class strings so Tailwind generates every tile — concatenated
       class names are invisible to the scanner. */
    const brandTiles: Record<string, string> = {
        primary: 'bg-primary text-primary-content',
        secondary: 'bg-secondary text-secondary-content',
        accent: 'bg-accent text-accent-content',
    };

    const contentTiles: Record<string, string> = {
        primary: 'bg-primary-content text-primary',
        secondary: 'bg-secondary-content text-secondary',
        accent: 'bg-accent-content text-accent',
    };

    const statusTiles: Record<string, string> = {
        success: 'bg-success text-success-content',
        info: 'bg-info text-info-content',
        warning: 'bg-warning text-warning-content',
        error: 'bg-error text-error-content',
    };

    /* Comparison grounds and the ramp combinations shown on each of them —
       solid (500 fill) and soft (100 fill), labeled with each color's
       content pair like the swatch tiles. */
    const backgrounds = [
        { key: 'base-100', class: 'bg-base-100' },
        { key: 'base-200', class: 'bg-base-200' },
        { key: 'base-content', class: 'bg-base-content' },
        { key: 'secondary', class: 'bg-secondary' },
        // { key: 'white', class: 'bg-white' },
        // { key: 'black', class: 'bg-black' },
    ] as const;

    const solidCombos: Record<string, string> = {
        primary: 'bg-primary-500 text-primary-content',
        secondary: 'bg-secondary-500 text-secondary-content',
        accent: 'bg-accent-500 text-accent-content',
        success: 'bg-success-500 text-success-content',
        info: 'bg-info-500 text-info-content',
        warning: 'bg-warning-500 text-warning-content',
        error: 'bg-error-500 text-error-content',
    };

    const softCombos: Record<string, string> = {
        primary: 'bg-primary-100 text-primary-content',
        secondary: 'bg-secondary-100 text-secondary-content',
        accent: 'bg-accent-100 text-accent-content',
        success: 'bg-success-100 text-success-content',
        info: 'bg-info-100 text-info-content',
        warning: 'bg-warning-100 text-warning-content',
        error: 'bg-error-100 text-error-content',
    };
    const buttonVariants = ['solid', 'outline', 'ghost', 'soft'] as const;
    const badgeVariants = ['solid', 'outline', 'soft'] as const;
    const componentColors = [
        'primary',
        'secondary',
        'accent',
        'success',
        'info',
        'warning',
        'error',
        'light',
        'dark',
    ] as const;

    /* Live value readout — after every theme switch, resolve each swatch's
       computed background and print it. This proves the token chain end to
       end instead of trusting the source values. */
    $effect(() => {
        void theme.value;

        tick().then(() => {
            document.querySelectorAll<HTMLElement>('[data-swatch]').forEach((swatch) => {
                const styles = getComputedStyle(swatch);

                document
                    .querySelectorAll<HTMLElement>(`[data-hex="${swatch.dataset.swatch}"]`)
                    .forEach((readout) => {
                        readout.textContent = styles.backgroundColor;
                    });

                document
                    .querySelectorAll<HTMLElement>(`[data-color-hex="${swatch.dataset.swatch}"]`)
                    .forEach((readout) => {
                        readout.textContent = styles.color;
                    });
            });
        });
    });
</script>

<div class="space-y-8 pb-20">
    <div>
        <h1>Design System — Color Tokens</h1>
        <p class="text-sm text-base-content/60">
            The six finance palettes as native DaisyUI themes. The selector below changes the
            app-wide theme — layout chrome, tokens, ramps, and components all follow. Swatch values
            are read live from the browser.
        </p>
    </div>

    <!-- App-wide theme selector -->
    <div
        class="sticky top-0 z-20 -mx-3 flex items-center gap-3 bg-base-100/90 p-3 px-6 backdrop-blur">
        <span class="text-xs font-semibold tracking-wide uppercase">Theme</span>
        <ThemeSelector class="min-w-0 flex-1" />
    </div>

    <!-- Specimen — inherits the app-wide theme -->
    <div class="space-y-8 bg-white text-base-content">
        <section class="px-5">
            <h2>Depth</h2>
            <hr class="mt-2 mb-4" />
            <p class="mb-4 text-sm text-base-content/60">
                Depth as it is actually layered — page ground, raised surface, recessed field, and
                elevated chip. Each layer prints its live value in place.
            </p>

            <!-- Layered composition: every depth token applied, not swatched. -->
            <div class="overflow-hidden rounded-box border border-base-300">
                <!-- base-100 — page ground -->
                <div class="bg-base-100 p-4" data-swatch="depth-ground">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold">Page ground</p>
                        <p class="text-xs text-base-content/50">
                            base-100 · <span data-hex="depth-ground">—</span>
                        </p>
                    </div>

                    <!-- base-200 — raised surface -->
                    <div class="mt-3 rounded-lg bg-base-200 p-4" data-swatch="depth-surface">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs font-semibold">Raised surface</p>
                            <p class="text-xs text-base-content/60">
                                base-200 · <span data-hex="depth-surface">—</span>
                            </p>
                        </div>

                        <!-- base-100 + base-300 border — recessed field -->
                        <div
                            class="mt-3 rounded-md border border-base-300 bg-base-100 p-3"
                            data-swatch="depth-field">
                            <div class="flex items-center gap-2 text-base-content/50">
                                <i class="iconify size-4 solar--magnifer-linear"></i>
                                <span class="text-sm">Recessed field</span>
                            </div>
                            <p class="mt-1 text-xs text-base-content/50">
                                base-100 field · border base-300 ·
                                <span data-hex="depth-field">—</span>
                            </p>
                        </div>

                        <!-- base-300 divider -->
                        <div class="mt-3 border-t border-base-300"></div>

                        <div class="mt-3 flex items-center justify-between gap-2">
                            <p class="text-xs font-semibold text-base-content/80">
                                Row over base-300 divider
                            </p>
                            <!-- base-300 — elevated chip -->
                            <span
                                class="rounded-full bg-base-300 px-2 py-1 text-xs font-semibold"
                                data-swatch="depth-chip">
                                elevated · <span data-hex="depth-chip">—</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-3 gap-2">
                {@render swatch('base-100', 'base-100', 'bg-base-100 text-base-content')}
                {@render swatch('base-200', 'base-200', 'bg-base-200 text-base-content')}
                {@render swatch('base-300', 'base-300', 'bg-base-300 text-base-content')}
                {@render swatch('base-content', 'base-content', 'bg-base-content text-base-100')}
                {@render swatch('neutral', 'neutral', 'bg-neutral text-neutral-content')}
                {@render swatch(
                    'neutral-content',
                    'neutral-content',
                    'bg-neutral-content text-neutral'
                )}
            </div>
        </section>

        <section class="px-5">
            <h2>Brand</h2>
            <hr class="mt-2 mb-4" />
            <div class="grid grid-cols-2 gap-2">
                {#each brandColors as color (color)}
                    {@render swatch(color, color, brandTiles[color])}
                    {@render swatch(color + '-content', color + '-content', contentTiles[color])}
                {/each}
            </div>
        </section>

        <section class="px-5">
            <h2>Status</h2>
            <hr class="mt-2 mb-4" />
            <div class="grid grid-cols-2 gap-2">
                {#each statusColors as color (color)}
                    {@render swatch(color, color, statusTiles[color])}
                {/each}
            </div>
        </section>

        <section class="px-5">
            <h2>Combinations × Backgrounds</h2>
            <hr class="mt-2 mb-4" />
            <p class="mb-4 text-sm text-base-content/60">
                Ramp combinations — solid (500 fill) and soft (100 fill), labeled with each color's
                content pair — compared on base-100, base-300, white, black, and secondary grounds.
            </p>
            <div class="space-y-3">
                {#each backgrounds as background (background.key)}
                    <div class="">
                        <p class="mb-1 text-xs font-semibold tracking-wide uppercase">
                            on {background.key}
                        </p>
                        <div
                            class="-mx-5 grid grid-cols-2 gap-3 overflow-x-auto p-5 lg:grid-cols-4 {background.class}">
                            {#each [...brandColors, ...statusColors] as color (color)}
                                {@render combo(
                                    color + '-solid',
                                    color + ' · 500',
                                    solidCombos[color]
                                )}
                                <!-- {@render combo(
                                    color + '-soft',
                                    color + ' · 100/700',
                                    softCombos[color]
                                )} -->
                            {/each}
                        </div>
                    </div>
                {/each}
            </div>
        </section>

        <!-- Components — commented while focusing on color tokens.
        <section class="px-5">
            <h2>Components</h2>
            <hr class="mt-2 mb-4" />
            <div class="space-y-5">
                {#each buttonVariants as variant (variant)}
                    <div>
                        <p class="mb-2 text-xs font-semibold tracking-wide uppercase">
                            {variant}
                        </p>
                        <div class="grid grid-cols-3 gap-2">
                            {#each componentColors as color (color)}
                                <Button {color} size="sm" {variant}>{color}</Button>
                            {/each}
                        </div>
                    </div>
                {/each}

                <div class="flex flex-wrap gap-2">
                    {#each badgeVariants as variant (variant)}
                        {#each statusColors as color (color)}
                            <Badge {color} shape="pill" {variant}>{color}</Badge>
                        {/each}
                    {/each}
                </div>

                <Card title="Sample Card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-base-content/60">Total balance</p>
                            <p class="text-2xl font-bold">Rp367.289</p>
                        </div>
                        <Badge color="success" variant="soft">+12% income</Badge>
                    </div>
                </Card>

                <Field title="Amount">
                    <Input name="amount" placeholder="Rp0" type="text" />
                </Field>

                <ul class="divide-y divide-base-content/10">
                    <li class="flex items-center gap-3 py-3">
                        <span
                            class="grid size-10 place-items-center rounded-full bg-primary/15 text-primary">
                            <i class="iconify size-5 solar--wallet-bold-duotone"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold">Groceries</span>
                            <span class="block text-xs text-base-content/50"> Food • Today </span>
                        </span>
                        <span class="text-sm font-bold text-error">− Rp42.000</span>
                    </li>
                    <li class="flex items-center gap-3 py-3">
                        <span
                            class="grid size-10 place-items-center rounded-full bg-info/15 text-info">
                            <i class="iconify size-5 solar--transfer-horizontal-bold-duotone"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold">Transfer</span>
                            <span class="block text-xs text-base-content/50">
                                Savings • Today
                            </span>
                        </span>
                        <span class="text-sm font-bold text-success">+ Rp2.500.000</span>
                    </li>
                </ul>
            </div>
        </section>
        -->
    </div>
</div>

{#snippet swatch(key: string, label: string, tileClass: string)}
    <div class="flex h-16 flex-col justify-end rounded-md p-2 {tileClass}" data-swatch={key}>
        <p class="text-xs font-semibold">{label}</p>
        <p class="text-xs opacity-70" data-hex={key}>—</p>
    </div>
{/snippet}

{#snippet combo(key: string, label: string, tileClass: string)}
    <div
        class="flex h-40 w-full shrink-0 flex-col justify-end rounded-md p-3 {tileClass}"
        data-swatch={key}>
        <div class="flex w-full items-end justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold">{label}</p>
                <p class="text-xs leading-tight opacity-70" data-hex={key}>—</p>
            </div>
            <div class="min-w-0 text-right">
                <p class="text-xs font-semibold">content</p>
                <p class="text-xs leading-tight opacity-70" data-color-hex={key}>—</p>
            </div>
        </div>
    </div>
{/snippet}
