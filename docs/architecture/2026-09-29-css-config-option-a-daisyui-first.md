# CSS Architecture Proposal A — DaisyUI-First Consolidation

> **Status:** Proposal — not implemented.
> **Siblings:** `2026-09-29-css-config-option-b-shadcn-first.md` (shadcn-first, inverted
> bridge), `2026-09-30-css-config-option-c-app-first.md` (app-first, dual-vocabulary
> theme files), and `2026-09-30-css-config-option-d-drop-daisyui.md` (drop DaisyUI
> entirely — full shadcn).
> Evaluate both against the same current-state baseline below.

## TL;DR

Keep DaisyUI's `@plugin 'daisyui/theme'` DSL as the **single palette database**, and keep
`shadcn.css` as the **only alias layer** (direction: DaisyUI names → shadcn names). This is
the conservative consolidation: it deletes the duplicated palette data, flattens the import
graph, and fixes the wiring bugs, but it keeps the current dependency direction and the
DaisyUI vocabulary as first-class.

```
palettes (DaisyUI theme DSL)  ──►  shadcn alias layer  ──►  shadcn utilities
        │                                (shadcn.css)
        └──► DaisyUI component classes + DaisyUI color utilities
```

---

## 1. Current state (baseline shared with Proposal B)

### Files

| File | Size | Role |
|---|---|---|
| `resources/css/app.css` | 8.0 KB | entry: fonts, imports, iconify plugin, 2× `@custom-variant dark`, `@theme inline` (fonts, ~120 lines of `color-mix` ramps), base styles |
| `resources/css/colors.css` | 12.8 KB | 11 themes as plain `[data-theme]` CSS variables |
| `resources/css/daisyui.css` | 15.6 KB | `@plugin 'daisyui'` config + 11 `@plugin 'daisyui/theme'` blocks — the **same palettes** in DSL form |
| `resources/css/shadcn.css` | 2.5 KB | semantic bridge: shadcn tokens ← DaisyUI tokens (`@theme inline`) |
| `resources/css/shadcn-token.css` | 2.4 KB | stock shadcn neutral template, kept as **reference** — not imported |
| `resources/css/flatpickr.css` | 25.4 KB | flatpickr theme + its own `:root` token bridge |

### Import graph (7 edges for 5 active files)

```
             ┌─► colors.css  ◄── daisyui.css ─┐
app.css ────┼─► daisyui.css ◄── shadcn.css   ├─► runtime cascade
             ├─► shadcn.css                  │
             └─► flatpickr.css ──────────────┘
   (shadcn-token.css — reference only, outside the chain)
```

### Problems (verified 2026-09-29)

1. **Every palette exists twice** — `colors.css` (plain CSS, `oklch(0.42 0.11 160)`) and the
   `@plugin 'daisyui/theme'` blocks in `daisyui.css` (`oklch(42% 0.11 160)`). Same 11 themes,
   two syntaxes, guaranteed drift. The plugin blocks load later and win the cascade, so
   `colors.css` is effectively shadowed data.
2. **"Default theme" declared in three conflicting places** — `colors.css` puts `:root` on
   `verdant`, the DaisyUI config marks `cobalt --default`, and
   `resources/js/lib/theme-handler.svelte.ts` sets `DEFAULT_THEME = 'cobalt'`.
3. **`@custom-variant dark` declared 3×** — `app.css:22`, `app.css:23` (two different forms),
   and `shadcn.css:3`. The surviving form `&:is(.dark *)` does not match the `.dark` element
   itself, only descendants.
4. **Two dark axes that don't compose** — named palettes via `data-theme` (including three
   `-dark` palettes) × an independent `.dark` appearance class. The shadcn bridge maps to
   `base-100`/`base-content`, which respond only to `data-theme`; so `theme=electric` +
   `appearance=dark` yields a light `bg-background` with `dark:` utilities active.
5. **Ramp wall** — ~120 lines of `color-mix` 50–950 ramps in `app.css` whose only real
   consumer is the unused `colorThemes` map in `resources/js/data/theme.ts` (dev color page).
6. **Live bug** — `components/ui/button.svelte` `dark` link variant references
   `var(--color-dark)`, which is defined nowhere; the hover silently does nothing.
7. **`--radius` collision** — `shadcn.css` sets `--radius: 0.625rem`, `flatpickr.css` re-defines
   `--radius: var(--radius-box)`; the winner depends on import order.
8. **Fonts** — 6 Google families fetched remotely; only Nunito + Instrument Sans are in the
   active stacks, and the app is a PWA where remote fonts break offline.
9. **Usage split** (483 svelte/ts files scanned): ~74 files use shadcn-semantic classes
   (`bg-background`, `text-card-foreground`…), ~79 use DaisyUI tokens/classes
   (`text-base-content`, `btn-primary`…). Neither vocabulary can be removed wholesale.

---

## 2. Proposed architecture (Option A)

### Principle

DaisyUI theme plugins are the only place **palette values** live. The shadcn bridge
(`shadcn.css`) is the only place the **two vocabularies meet**. `app.css` is the only file
with wiring (`@import` / `@plugin` / `@custom-variant` / `@source`).

### Target structure

```
resources/css/
├── app.css            # entry ONLY: tailwind import, fonts, @source, iconify plugin,
│                      # @custom-variant dark (exactly once)
├── fonts.css          # @font-face (self-hosted, 2 families)
├── themes.css         # @plugin 'daisyui' + all 11 @plugin 'daisyui/theme' blocks
│                      #   (or themes/*.css — one file per theme — if preferred)
├── shadcn.css         # the ONLY bridge: @theme inline shadcn ← daisy, radius scale,
│                      # body/scrollbar base styles
├── vendors/
│   └── flatpickr.css  # vendor theme, consuming bridged runtime vars
└── reference/
    └── shadcn-stock.css   # the current shadcn-token.css — reference template,
                           # deliberately outside the import chain
```

Deleted: `colors.css` (shadowed duplicate), the inner `@import` edges, all duplicate
declarations.

### Build-time import graph (flat)

```
app.css
 ├── @import 'tailwindcss'
 ├── @import 'tw-animate-css'
 ├── @import './fonts.css'
 ├── @import './themes.css'        ← single palette store (plugin DSL)
 ├── @import './shadcn.css'        ← single bridge
 ├── @import './vendors/flatpickr.css'
 ├── @plugin '@iconify/tailwind4' { … }
 ├── @custom-variant dark (&:where(.dark, .dark *));   ← once
 └─ @source …
```

No file imports another except `app.css` → everything.

### Runtime token flow

```
 class="bg-primary"                 class="bg-background"            class="btn-primary"
       │                                  │                                │
 DaisyUI plugin registers           shadcn.css bridge (@theme inline)  DaisyUI component CSS
 utilities → var(--color-primary)   --color-background:                --btn-color:
       │                            var(--color-base-100)              var(--color-primary)
       │                                  │                                │
       └──────────────┬───────────────────┴────────────────────────────────┘
                      ▼
      themes.css @plugin 'daisyui/theme' blocks emit:
      [data-theme='cobalt'] { --color-primary: …; --color-base-100: …; … }
                      ▼
                resolved color ✅
```

One palette store (plugin blocks) feeds both vocabularies. Editing a palette touches one
block in one file.

### Theme-switch flow

```
user picks "Electric Dark"
  ├─ theme-handler.svelte.ts: documentElement.dataset.theme = 'electric-dark'
  ├─ CSS cascade flips: [data-theme='electric-dark'] { …dark palette values… }
  │    utilities, DaisyUI components, flatpickr recolor (pure var indirection)
  ├─ derived dark flag: html.classList.toggle('dark', theme.endsWith('-dark'))
  │    → dark: utilities and the palette always agree
  └─ persist: localStorage + cookie + PUT UserThemeController.update
```

`.dark` stops being an independent axis and becomes derived from `data-theme`.

### Vocabulary ownership

| Layer | Allowed | Forbidden |
|---|---|---|
| `themes.css` plugin blocks | DaisyUI palette vars + structural vars (`--radius-*`, `--size-*`, `--border`, `--depth`, `--noise`, `color-scheme`) | shadcn names |
| `shadcn.css` | the alias table + `@theme inline` mappings + base styles | raw palette values |
| `ui/` components | both vocabularies (daisy classes are implementation detail) | new inline `color-mix` ramps |
| app code (pages, modules) | both, gradually preferring shadcn-semantic names | — |
| `vendors/*` | consume runtime vars | define tokens |

---

## 3. Migration steps (safe order)

| # | Step | Risk |
|---|---|---|
| 1 | Move `shadcn-token.css` → `reference/shadcn-stock.css` (intent: reference, unreachable by imports) | none |
| 2 | Delete `colors.css` + its two import edges (app.css, daisyui.css). Plugin blocks already hold identical data and win the cascade today | low |
| 3 | Fix the default: keep `cobalt --default` in the plugin config (matches JS `DEFAULT_THEME`); the stray verdant `:root` disappears with `colors.css` | low |
| 4 | Rename `daisyui.css` → `themes.css`; remove its inner `@import './colors.css'` | none |
| 5 | Flatten imports into `app.css`; remove `shadcn.css`'s inner `@import './daisyui.css'` | none |
| 6 | Declare `@custom-variant dark (&:where(.dark, .dark *))` exactly once in `app.css`; delete the other two declarations | low |
| 7 | Fix `--radius` collision: flatpickr consumes `--radius-box` directly in its `--fp-radius-*` knobs; it must not re-define `--radius` | low |
| 8 | Derive `.dark` from the `-dark` theme suffix in `theme-handler.svelte.ts`; remove the independent appearance axis (or keep the toggle, mapping light/dark → base palette / `-dark` sibling) | medium |
| 9 | Delete the ramp wall from `app.css`; replace `button.svelte` hand-rolled `color-mix` hovers with opacity modifiers (`hover:text-primary/80`) — fixes the `--color-dark` bug for free; prune unused maps from `data/theme.ts` | medium |
| 10 | Self-host Nunito + Instrument Sans (`@fontsource-variable/*`), drop the remote Google Fonts URL and the 4 unused families | medium |

**Verification per step:** `npm run build` + visual pass over all 11 themes (light set +
dark set) + the dev color page; steps 1–5 should be pixel-identical.

---

## 4. Trade-offs

**Pros**

- Lowest-risk consolidation; everything stays on DaisyUI's officially supported theming path
  (`@plugin 'daisyui/theme'`, `--default`, and `prefersdark` remain available).
- No adapter to maintain — DaisyUI emits its own vocabulary; the existing `shadcn.css`
  bridge is already proven in production.
- Existing `text-base-content` / `btn-primary` usage stays first-class forever.
- Palette edits stay in one file/format.

**Cons**

- Palettes live in a plugin DSL (`oklch(42% …)` percent syntax, `name:`/`default:` options)
  — harder to diff/lint/script than plain CSS.
- The dependency direction keeps DaisyUI naming as the "source" vocabulary, while the code
  you write daily is already ~50/50 and the shadcn ecosystem components you install
  (bits-ui/shadcn-svelte style) speak shadcn names.
- `@plugin 'daisyui/theme'` still carries machinery you don't use (theme-controller
  `:has()` selectors, `prefersdark`) into every build.

**Choose A when:** you want the fastest path to "clean and boring", minimal behavioral
change risk, and to keep DaisyUI's theming conventions long-term.

**Choose B when:** you'd rather own the token system in plain CSS and treat DaisyUI purely
as a component library — see the sibling proposal.
