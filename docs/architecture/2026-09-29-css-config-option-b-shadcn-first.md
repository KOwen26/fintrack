# CSS Architecture Proposal B — shadcn-First (Inverted Bridge)

> **Status:** Proposal — not implemented.
> **Siblings:** `2026-09-29-css-config-option-a-daisyui-first.md` (conservative
> consolidation), `2026-09-30-css-config-option-c-app-first.md` (app-first,
> dual-vocabulary theme files — same mechanics, mapping distributed into each theme),
> and `2026-09-30-css-config-option-d-drop-daisyui.md` (drop DaisyUI entirely —
> consumes this proposal's token layer).
> Evaluate both against the same current-state baseline (see §1 of Proposal A).

## TL;DR

Invert the bridge. Palettes become **plain CSS files speaking shadcn-canonical variable
names**; DaisyUI runs in **components-only mode** (`themes: false`) behind a ~30-line
adapter in `tokens.css`. DaisyUI stops owning your design system's naming and becomes what
it is best at: prebuilt component classes.

```
themes/*.css (plain CSS, shadcn vars)  ──►  tokens.css adapter  ──►  DaisyUI components + utilities
        │                                        │
        └────────────►  @theme inline  ──►  shadcn utilities (bg-background, text-primary…)
```

Your existing `colors.css` was the seed of this architecture all along — the mess was having
it *and* the plugin DSL.

---

## 1. Why this is mechanically safe (verified against `daisyui@5.7.22` source)

Read directly from `node_modules/daisyui`:

1. **The plugin always registers the color vocabulary** — `functions/variables.js` maps
   Tailwind colors to runtime vars (`primary: "var(--color-primary)"`, `base-100`, radii, …)
   via the `plugin.withOptions` config callback in `index.js`. This happens **regardless of
   the `themes:` option**, so `bg-primary` / `text-base-content` / `rounded-box` utilities
   exist even with `themes: false`.
2. **Component CSS consumes the same runtime vars** — e.g. `components/button.css`:
   `.btn-primary { --btn-color: var(--color-primary); --btn-fg: var(--color-primary-content); }`.
3. **`themes: false` skips theme emission entirely** — `functions/pluginOptionsHandler.js`
   only applies themes when the option is truthy. Components + utilities remain.
4. **`@plugin 'daisyui/theme'` is not magic** — `theme/index.js` is just
   `addBase({ '[data-theme="x"]': { --color-…: … } })`. A hand-written
   `[data-theme='cobalt'] { … }` block is functionally identical (minus the unused
   theme-controller `:has()` sugar).

Consequence: any system that defines the `--color-*` variables at runtime styles DaisyUI
components — which is exactly what the current `colors.css` already does.

---

## 2. Proposed architecture (Option B)

### Principle

One palette store (plain CSS, shadcn-canonical names). One adapter (DaisyUI vocabulary
derived, never authored). One wiring file. The stock shadcn template
(`shadcn-token.css`) becomes the **shape reference** every theme file follows — reference
and reality stop diverging.

### Target structure

```
resources/css/
├── app.css                  # THE wiring file
│    ├─ @import 'tailwindcss'
│    ├─ @import 'tw-animate-css'
│    ├─ @import './fonts.css'
│    ├─ @import './themes/index.css'      → themes/*.css (plain palettes)
│    ├─ @import './tokens.css'            # @theme inline + DaisyUI adapter
│    ├─ @import './vendors/flatpickr.css'
│    ├─ @plugin '@iconify/tailwind4' { … }
│    ├─ @plugin 'daisyui' { themes: false; }   ← components + vocabulary only
│    ├─ @custom-variant dark (&:where(.dark, .dark *));   ← exactly once
│    └─ @source …
├── fonts.css                # @font-face (self-hosted, 2 families)
├── tokens.css               # the ONLY bridge in the system
├── themes/                  # the ONLY place colors are defined
│   ├── index.css            #   imports each theme file
│   ├── cobalt.css           #   [data-theme='cobalt'] { --background: …; --primary: … }
│   ├── cobalt-dark.css      #   same var set, dark values
│   ├── electric.css         #   (may override --radius per theme)
│   └── …                    #   one file per palette (11 total)
├── vendors/
│   └── flatpickr.css        # consumes --popover/--primary/--border/--radius directly
└── reference/
    └── shadcn-stock.css     # the stock template — the canonical shape of a theme file
```

Deleted: `colors.css` (absorbed — renamed into `themes/*.css`), `daisyui.css` (its palette
data moves into the theme files; its structural vars move into the adapter).

### Example theme file — `themes/electric.css`

Seed values are the current palette, renamed to the shadcn set (the inverse of today's
`shadcn.css` bridge):

```css
/* Palette: Electric — navy, lime & teal. Shape follows reference/shadcn-stock.css */
[data-theme='electric'] {
    color-scheme: light;

    --background: oklch(98.5% 0.001 288.5);
    --foreground: oklch(30.1% 0 23.7);
    --card: oklch(97% 0.003 265.4);
    --card-foreground: var(--foreground);
    --popover: oklch(98.5% 0.001 288.5);
    --popover-foreground: var(--foreground);

    --primary: oklch(77.9% 0.126 173.5);
    --primary-foreground: oklch(28.8% 0.05 265.9);
    --secondary: oklch(28.8% 0.05 265.9);
    --secondary-foreground: oklch(89.1% 0 23.7);
    --accent: oklch(95.2% 0.16 120.2);
    --accent-foreground: oklch(28.8% 0.05 265.9);

    --muted: oklch(94.9% 0.005 275.4);
    --muted-foreground: color-mix(in oklab, var(--foreground) 60%, transparent);
    --destructive: oklch(58% 0.16 25);
    --destructive-foreground: oklch(98.5% 0.001 288.5);

    /* App-specific semantic colors (first-class, not DaisyUI aliases) */
    --success: oklch(72% 0.17 140);
    --success-foreground: oklch(28.8% 0.05 265.9);
    --info: oklch(62% 0.11 205);
    --info-foreground: oklch(97% 0.01 288);
    --warning: oklch(78% 0.14 85);
    --warning-foreground: oklch(30.1% 0 23.7);
    --error: var(--destructive);
    --error-foreground: var(--destructive-foreground);

    --border: oklch(94.9% 0.005 275.4);
    --input: var(--border);
    --ring: var(--primary);
    --radius: 0.5rem;   /* per-theme override; adapter derives the DaisyUI radii */
}
```

### The adapter — `tokens.css` (the only DaisyUI↔shadcn translation)

```css
/* 1. Expose shadcn vars to Tailwind utilities */
@theme inline {
    --color-background: var(--background);
    --color-foreground: var(--foreground);
    --color-card: var(--card);
    --color-card-foreground: var(--card-foreground);
    --color-popover: var(--popover);
    --color-popover-foreground: var(--popover-foreground);
    --color-primary: var(--primary);            /* also feeds DaisyUI via the adapter below */
    --color-primary-foreground: var(--primary-foreground);
    --color-secondary: var(--secondary);
    --color-secondary-foreground: var(--secondary-foreground);
    --color-accent: var(--accent);
    --color-accent-foreground: var(--accent-foreground);
    --color-muted: var(--muted);
    --color-muted-foreground: var(--muted-foreground);
    --color-destructive: var(--destructive);
    --color-destructive-foreground: var(--destructive-foreground);
    --color-success: var(--success);
    --color-info: var(--info);
    --color-warning: var(--warning);
    --color-error: var(--error);
    --color-border: var(--border);
    --color-input: var(--input);
    --color-ring: var(--ring);
    --radius-sm: calc(var(--radius) - 4px);
    --radius-md: calc(var(--radius) - 2px);
    --radius-lg: var(--radius);
    --radius-xl: calc(var(--radius) + 4px);
}

/* 2. DaisyUI adapter — the ONLY place DaisyUI vocabulary is derived.
      Components (.btn-primary, .card…) and utilities (text-base-content) resolve here. */
:root,
[data-theme] {
    --color-base-100: var(--background);
    --color-base-200: var(--card);
    --color-base-300: var(--border);
    --color-base-content: var(--foreground);
    --color-primary-content: var(--primary-foreground);
    --color-secondary-content: var(--secondary-foreground);
    --color-accent-content: var(--accent-foreground);
    --color-neutral: var(--secondary);
    --color-neutral-content: var(--secondary-foreground);
    --color-success-content: var(--success-foreground);
    --color-info-content: var(--info-foreground);
    --color-warning-content: var(--warning-foreground);
    --color-error-content: var(--error-foreground);

    /* DaisyUI structural vars (currently buried in each plugin block) */
    --radius-selector: var(--radius-sm);
    --radius-field: var(--radius-md);
    --radius-box: var(--radius-lg);
    --size-selector: 0.25rem;
    --size-field: 0.25rem;
    --border: 1px;
    --depth: 0;
    --noise: 0;
}
```

### Runtime token flow — two vocabularies, one store

```
 class="bg-primary"                      class="btn-primary"
        │                                       │
 utility (daisy plugin registers         DaisyUI component CSS
 primary → var(--color-primary))         --btn-color: var(--color-primary)
        │                                       │
        └───────────────┬───────────────────────┘
                        ▼
   tokens.css adapter:  --color-primary: var(--primary)          ← @theme inline side
                         --color-primary-content: var(--primary-foreground)
                         --color-base-100: var(--background)     ← :root adapter side
                        ▼
   themes/electric.css: [data-theme='electric'] { --primary: …; --background: … }
                        ▼
                  resolved color ✅
```

Properties:

- **Editing a palette** touches exactly one plain-CSS file — no DSL, no dual oklch syntax,
  no drift.
- **Existing daisy-vocab classes keep working untouched** — `text-base-content` resolves
  through the adapter, so the ~79 daisy-vocab files migrate opportunistically (on touch)
  to `text-foreground` etc. No big-bang rename.
- **Flatpickr's bridge collapses** — `--popover`, `--primary`, `--border` are now the
  runtime vars themselves; only the `--fp-*` geometry knobs remain.
- **Charts / sidebar / popover** derivations (`--chart-1: var(--primary)` …) stay in
  `tokens.css` as defaults, overridable per theme.

### Theme-switch flow (mechanism unchanged)

```
user picks "Electric Dark"
  ├─ theme-handler.svelte.ts: documentElement.dataset.theme = 'electric-dark'
  ├─ CSS cascade flips: [data-theme='electric-dark'] { …dark values… }
  │    adapter + utilities + components follow (pure var indirection, zero JS)
  ├─ derived dark flag: html.classList.toggle('dark', theme.endsWith('-dark'))
  └─ persist: localStorage + cookie + PUT UserThemeController.update
```

### Vocabulary ownership

| Layer | Allowed | Forbidden |
|---|---|---|
| `themes/*.css` | shadcn-canonical vars + optional per-theme `--radius` override | any `--color-*` DaisyUI name |
| `tokens.css` | the adapter + `@theme inline` mappings + base styles | raw palette values |
| `ui/` components | shadcn utilities + DaisyUI component classes (`btn-primary`) as implementation detail | new inline `color-mix` ramps |
| app code (pages, modules) | shadcn utilities (legacy daisy usage migrates on touch) | new daisy-vocab usage |
| `vendors/*` | consume runtime vars directly | define tokens |

---

## 3. Migration steps (safe order)

| # | Step | Risk |
|---|---|---|
| 1 | Move `shadcn-token.css` → `reference/shadcn-stock.css`; add a header comment naming it the shape template | none |
| 2 | Create `themes/` — convert `colors.css` + `daisyui.css` values into shadcn-canonical per-theme files (mapping = inverse of today's `shadcn.css` bridge; electric keeps its `--radius: 0.5rem` override) | low |
| 3 | Create `tokens.css` (adapter + `@theme inline` + base styles, absorbing current `shadcn.css`) | low |
| 4 | Rewire `app.css`: flat imports, `@plugin 'daisyui' { themes: false; }`, single `@custom-variant dark` | medium |
| 5 | Slim `vendors/flatpickr.css`: drop its `:root` token bridge; consume `--popover/--primary/--border/--radius` directly | low |
| 6 | Delete `colors.css` + `daisyui.css` + old `shadcn.css` | medium |
| 7 | Derive `.dark` from the `-dark` suffix in `theme-handler.svelte.ts` (same fix as Proposal A) | medium |
| 8 | Delete the ramp wall; replace `button.svelte` `color-mix` hovers with opacity modifiers (fixes the `--color-dark` bug); prune `data/theme.ts` maps | medium |
| 9 | Self-host fonts (Nunito + Instrument Sans only) | medium |

**Verification per step:** `npm run build` + visual pass over all 11 themes + dev color
page; special attention to DaisyUI components (`btn-primary`, selects, modals) under
`themes: false` — the adapter must be complete before step 6.

**First-paint note:** `themes: false` removes DaisyUI's `:where(:root)` default-theme CSS,
so before JS sets `data-theme` there are no color vars at all (flash risk, unlike
Proposal A). Add a pre-paint boot script to `app.blade.php` `<head>` that reads the
`fintrack-theme` cookie and sets `documentElement.dataset.theme` (+ derived `.dark`)
before styles apply. See Proposal C §8 for the full write-up.

---

## 4. Trade-offs

**Pros**

- Palettes in **one plain-CSS format** — diffable, lintable, trivially scriptable (e.g.
  generating more finance palettes later, or a JSON→CSS pipeline).
- **Your majority vocabulary becomes canonical** — shadcn-semantic names, which the
  bits-ui/shadcn-style `ui/` components (243 files) already speak.
- DaisyUI shrinks to a **component library** behind one explicit adapter — its naming stops
  leaking into your design system.
- The reference template and the theme files share the same shape; `shadcn-stock.css`
  finally has a job.
- Flatpickr and any future vendor theme consume tokens with **zero bridging**.

**Cons**

- The adapter must be **complete**: every DaisyUI var a component can reach
  (`--color-*-content`, `--radius-*`, `--size-*`, `--border`, `--depth`, `--noise`,
  `color-scheme`). Missing one surfaces as a subtly-broken component (e.g. `btn` without
  `--border` renders with default border-width).
- You take ownership of the DaisyUI↔shadcn mapping forever; DaisyUI upgrades may add new
  vars the adapter must learn.
- You lose DaisyUI's `prefersdark` and theme-controller machinery (unused today — JS-driven
  `data-theme` switching — but a future capability given up).
- Slightly more upfront work than Proposal A (steps 2–4 vs. deletes/renames).

**Choose B when:** you want the token system to be yours — plain CSS, shadcn-canonical,
library-agnostic — and you accept maintaining one explicit adapter in exchange for never
duplicating palette data again.

**Choose A when:** you want DaisyUI's officially supported theming path with the least
risk — see the sibling proposal.
