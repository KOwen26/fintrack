# CSS Architecture Proposal C — App-First (Dual-Vocabulary Theme Files)

> **Status:** Proposal — not implemented.
> **Siblings:** `2026-09-29-css-config-option-a-daisyui-first.md`,
> `2026-09-29-css-config-option-b-shadcn-first.md`, and
> `2026-09-30-css-config-option-d-drop-daisyui.md` (drop DaisyUI entirely — consumes
> this proposal's token layer). Shared baseline: §1 of Proposal A.

## TL;DR

One file per app theme, containing **both vocabularies side by side**. The shadcn side
holds the raw palette values; the DaisyUI side is a co-located alias block
(`--color-primary: var(--primary)`, …) plus DaisyUI's structural knobs. The central
bridge (A) / adapter (B) disappears — the mapping between the two naming systems lives
next to the palette it serves.

```
themes/electric.css — one self-contained slice:
   [data-theme='electric'] {
       shadcn vars (raw oklch)  ──►  shadcn utilities (bg-background…)
       daisy aliases (var())    ──►  daisy components + utilities (btn-primary, text-base-content…)
   }
```

### Where the two vocabularies meet — the real decision axis

| | Palette store | DaisyUI ↔ shadcn mapping lives in | Mapping scope |
|---|---|---|---|
| **A — DaisyUI-first** | DaisyUI plugin DSL | `shadcn.css` (central bridge) | one global mapping |
| **B — shadcn-first** | plain CSS, shadcn names | `tokens.css` (central adapter) | one global mapping |
| **C — app-first** | plain CSS, **both names** | **each theme file** (co-located aliases) | per-theme mapping |

A and B differ in *which vocabulary owns the raw values*. C differs in *where the mapping
lives*: centralized (B) vs distributed (C). Classic trade-off — system view vs
self-containment.

---

## 1. The idea

A theme is an **app asset**, not a library asset. Opening `themes/electric.css` shows
everything that theme is: its surfaces, brand colors, radii, and exactly how it maps onto
both component vocabularies. Nothing about a theme is spread across other files.

This also fixes a real defect in the current setup: the central bridge is **lossy**.
Today `shadcn.css` collapses, for every theme alike:

```css
--color-card:    var(--color-base-200);
--color-popover: var(--color-base-200);   /* card == popover, always */
--color-muted:   var(--color-base-300);
--color-border:  var(--color-base-300);   /* muted == border == input, always */
--color-input:   var(--color-base-300);
```

A global mapping cannot express "this palette wants a distinct popover surface" or
"this theme's borders should be softer than its muted fills". A per-theme mapping can —
and in C that capability costs nothing extra, because the mapping is already in the file.

## 2. Mechanics (inherits B's verified foundation)

C uses the same DaisyUI mechanics as B (verified against `daisyui@5.7.22` source — see
Proposal B §1):

- `@plugin 'daisyui' { themes: false; }` → components + utility vocabulary only;
- component CSS and utilities resolve `var(--color-*)` at runtime — whoever defines them wins;
- `@theme inline` registration is theme-agnostic (it maps utility → var *name*, resolution
  is runtime), so it stays central and tiny.

The one additional mechanical fact C relies on: **in-block `var()` aliasing is standard
CSS**. Custom properties resolve per-element, so this is well-defined and cycle-free:

```css
[data-theme='electric'] {
    --primary: oklch(77.9% 0.126 173.5);   /* raw value */
    --color-primary: var(--primary);        /* alias, same block — legal & lazy */
}
```

## 3. Sub-variants

| Variant | Raw values | DaisyUI side | Use when |
|---|---|---|---|
| **C1 — alias (recommended)** | shadcn side only | in-file `var()` aliases | hand-authored themes; one literal per color |
| **C2 — raw** | both sides | literals too | themes are **generated** (JSON → CSS) and you want zero indirection |

C2 writes every value twice (recreating today's duplication, just co-located), so it only
pays off when a generator makes duplication free. The rest of this doc assumes C1.

(The raw side could equally be the DaisyUI names with shadcn aliased — C1 as written keeps
shadcn canonical for consistency with B and the bits-ui-style `ui/` components.)

## 4. Example theme file — `themes/electric.css`

```css
/* Theme: Electric — navy, lime & teal.
   Self-contained: shadcn surface values + DaisyUI aliases + structural knobs.
   Shape reference: reference/shadcn-stock.css */
[data-theme='electric'] {
    color-scheme: light;

    /* ── Surfaces (shadcn) ── */
    --background: oklch(98.5% 0.001 288.5);
    --foreground: oklch(30.1% 0 23.7);
    --card: oklch(97% 0.003 265.4);
    --card-foreground: var(--foreground);
    --popover: var(--background);              /* per-theme choice: popover == background here */
    --popover-foreground: var(--foreground);
    --muted: oklch(94.9% 0.005 275.4);
    --muted-foreground: color-mix(in oklab, var(--foreground) 60%, transparent);
    --border: oklch(91.1% 0.018 273);          /* per-theme choice: border ≠ muted — the lossy collapse, fixed */
    --input: var(--border);
    --ring: var(--primary);

    /* ── Brand & semantics ── */
    --primary: oklch(77.9% 0.126 173.5);
    --primary-foreground: oklch(28.8% 0.05 265.9);
    --secondary: oklch(28.8% 0.05 265.9);
    --secondary-foreground: oklch(89.1% 0 23.7);
    --accent: oklch(95.2% 0.16 120.2);
    --accent-foreground: oklch(28.8% 0.05 265.9);
    --destructive: oklch(58% 0.16 25);
    --destructive-foreground: oklch(98.5% 0.001 288.5);
    --success: oklch(72% 0.17 140);
    --success-foreground: oklch(28.8% 0.05 265.9);
    --info: oklch(62% 0.11 205);
    --info-foreground: oklch(97% 0.01 288);
    --warning: oklch(78% 0.14 85);
    --warning-foreground: oklch(30.1% 0 23.7);
    --error: var(--destructive);
    --error-foreground: var(--destructive-foreground);

    /* ── DaisyUI vocabulary (co-located aliases) ── */
    --color-base-100: var(--background);
    --color-base-200: var(--card);
    --color-base-300: var(--muted);
    --color-base-content: var(--foreground);
    --color-primary: var(--primary);
    --color-primary-content: var(--primary-foreground);
    --color-secondary: var(--secondary);
    --color-secondary-content: var(--secondary-foreground);
    --color-accent: var(--accent);
    --color-accent-content: var(--accent-foreground);
    --color-neutral: var(--secondary);
    --color-neutral-content: var(--secondary-foreground);
    --color-success-content: var(--success-foreground);
    --color-info-content: var(--info-foreground);
    --color-warning-content: var(--warning-foreground);
    --color-error-content: var(--error-foreground);

    /* ── DaisyUI structural knobs — per-theme, native to this file ── */
    --radius-selector: calc(var(--radius) - 4px);
    --radius-field: calc(var(--radius) - 2px);
    --radius-box: var(--radius);
    --radius: 0.5rem;      /* Electric's rounded feel */
    --size-selector: 0.25rem;
    --size-field: 0.25rem;
    --border-width: 1px;   /* see guardrails note on DaisyUI's --border name */
    --depth: 0;
    --noise: 0;
}
```

> ⚠️ **Naming collision to resolve once:** DaisyUI's structural var `--border` (a *width*)
> collides with shadcn's `--border` (a *color*). DaisyUI components read
> `border-width: var(--border)`. Options: (a) point DaisyUI's `--border` at a dedicated
> `--border-width` var as above and let shadcn own the `--border` name for color — verify
> `.btn` etc. render `1px` borders; or (b) rename the shadcn side (`--border-color`).
> Decide during implementation and document it in `tokens.css`. (Option B has the same
> collision hiding in its adapter.)

## 5. What stays central — `tokens.css` (small)

Utility **registration** cannot be per-theme (`@theme inline` maps utility → var *name*,
and must exist once at build time). It is theme-agnostic:

```css
@theme inline {
    --color-background: var(--background);
    --color-foreground: var(--foreground);
    --color-card: var(--card);
    --color-card-foreground: var(--card-foreground);
    --color-popover: var(--popover);
    --color-popover-foreground: var(--popover-foreground);
    --color-primary: var(--primary);          /* also what daisy utilities resolve */
    --color-primary-foreground: var(--primary-foreground);
    --color-secondary: var(--secondary);
    --color-secondary-foreground: var(--secondary-foreground);
    --color-accent: var(--accent);
    --color-accent-foreground: var(--accent-foreground);
    --color-muted: var(--muted);
    --color-muted-foreground: var(--muted-foreground);
    --color-destructive: var(--destructive);
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

@layer base {
    body { @apply bg-background text-foreground; }
    /* scrollbar rules, currently scattered in app.css / shadcn.css / flatpickr.css */
}
```

Note what is **absent**: no adapter section, no `[data-theme]` selector, no palette data.
`tokens.css` shrinks to registration + base styles.

## 6. Graphs

### Build-time import graph

```
app.css
 ├── @import 'tailwindcss'
 ├── @import 'tw-animate-css'
 ├── @import './fonts.css'
 ├── @import './themes/index.css'   → themes/*.css  (self-contained slices)
 ├── @import './tokens.css'         (registration + base only)
 ├── @import './vendors/flatpickr.css'
 ├── @plugin '@iconify/tailwind4' { … }
 ├── @plugin 'daisyui' { themes: false; }
 ├── @custom-variant dark (&:where(.dark, .dark *));   ← once
 └─ @source …
```

Identical shape to B — the difference is entirely inside the theme files.

### Runtime token flow — no central adapter hop

```
 class="bg-background"                class="btn-primary" / "text-base-content"
        │                                        │
 @theme inline registration                daisy component CSS / variables.js
 background-color: var(--background)       var(--color-primary) / var(--color-base-*)
        │                                        │
        └────────────────┬───────────────────────┘
                         ▼
     themes/electric.css  (BOTH vocabularies defined here)
       --background: oklch(…)        ← raw
       --color-base-100: var(--background)   ← alias, same block
                         ▼
                   resolved color ✅
```

One less indirection than B for daisy classes, and the whole chain is visible in one file.

### Theme-switch flow (same as A/B)

```
user picks "Electric Dark"
  ├─ theme-handler: documentElement.dataset.theme = 'electric-dark'
  ├─ both vocabularies flip together (they live in the same block — cannot desync)
  ├─ derived: html.classList.toggle('dark', theme.endsWith('-dark'))
  └─ persist: localStorage + cookie + PUT UserThemeController.update
```

## 7. Vocabulary ownership

| Layer | Allowed | Forbidden |
|---|---|---|
| `themes/*.css` | shadcn raw values + DaisyUI aliases + structural knobs (+ per-theme mapping choices) | new vocabulary names |
| `tokens.css` | `@theme inline` registration + base styles | palette values, aliases |
| `ui/` components | both vocabularies | new inline `color-mix` ramps |
| app code (pages, modules) | shadcn utilities preferred; daisy utilities tolerated (both first-class by design) | — |
| `vendors/*` | consume runtime vars directly | define tokens |

## 8. Migration steps

| # | Step | Risk |
|---|---|---|
| 1 | Move `shadcn-token.css` → `reference/shadcn-stock.css` | none |
| 2 | Create `themes/*.css` — seed from `colors.css` + `daisyui.css` values; shadcn side raw, DaisyUI side aliases; decide the `--border` collision once | low |
| 3 | Create slim `tokens.css` (registration + base only — **no adapter**) | low |
| 4 | Rewire `app.css`: flat imports, `themes: false`, single `@custom-variant dark` | medium |
| 5 | Add first-paint boot script to `app.blade.php` `<head>` (see below) | low |
| 6 | Slim `vendors/flatpickr.css` (consume `--popover/--primary/--border/--radius` directly) | low |
| 7 | Delete `colors.css`, `daisyui.css`, old `shadcn.css` | medium |
| 8 | Derive `.dark` from the `-dark` suffix in `theme-handler.svelte.ts` | medium |
| 9 | Ramp removal, `button.svelte` hover fix (`--color-dark` bug), `data/theme.ts` prune | medium |
| 10 | Self-host fonts | medium |

**First-paint note (applies to B and C):** `themes: false` removes DaisyUI's `:where(:root)`
default-theme CSS, so before JS sets `data-theme` there are **no color vars at all** →
flash risk. Fix once in `app.blade.php`:

```html
<script>
    // pre-paint: cookie → data-theme (+ derived .dark), before CSS applies
</script>
```

(Proposal A does not have this issue — DaisyUI emits the `--default` theme on `:root`.)

## 9. Trade-offs

**Pros**

- **Self-contained themes** — copy a file, edit values, done. The complete mental model of
  a theme fits on one screen.
- **Per-theme mapping freedom** — fixes the global bridge's lossy collapse
  (`card == popover`, `muted == border == input`) for any palette that needs distinction.
- **Structural knobs are native** — per-theme radius/size/depth/noise (Electric's
  `0.5rem` vs others' `0.25rem`) live where they belong, no adapter gymnastics.
- **Both vocabularies first-class forever** — matches the actual ~50/50 usage split; no
  "legacy" vocabulary to migrate.
- **Cannot desync** — both vocabularies flip in the same block; there is no central file
  to forget updating.
- One less runtime hop for daisy classes than B.

**Cons**

- **Alias boilerplate ×11** — ~25 identical lines per theme. With more finance palettes,
  this grows linearly.
- **New drift surface** — a typo in *one* theme's alias block desyncs only that theme
  (B's central adapter cannot drift per-theme). **Guardrail required:** until themes are
  generated, enforce that the DaisyUI-alias section is byte-identical across all theme
  files (trivial lint script / CI grep), or generate `themes/*.css` from a
  `themes.source.json`.
- **No system view** — "what does `base-200` mean app-wide?" is answered by reading a theme
  file, not a central adapter.
- Still needs central `tokens.css` (registration can't be per-theme).
- Same first-paint consideration as B.

## 10. Choosing among A / B / C

| Axis | A — daisyui-first | B — shadcn-first | C — app-first |
|---|---|---|---|
| Palette store | plugin DSL | plain CSS, shadcn names | plain CSS, both names |
| Mapping location | central bridge | central adapter | each theme file |
| Per-theme mapping flexibility | ✗ | ✗ | ✓ |
| Boilerplate per new theme | low | low | +~25 alias lines |
| Drift surfaces | bridge ↔ themes | adapter ↔ themes | alias blocks between themes |
| Vocabulary lock-in | DaisyUI | shadcn | neither (equal citizens) |
| First-paint fallback | free (`--default`) | needs boot script | needs boot script |
| Effort / risk | lowest | low–medium | medium (needs guardrail) |

- **A** — fastest, boring, officially-supported DaisyUI path.
- **B** — you own the token system centrally; best if themes should never vary their
  mapping.
- **C** — themes are app assets: self-contained, freely mappable per palette; accept the
  alias boilerplate + a consistency guardrail (or a generator).

**Choose C when:** you expect themes to keep growing (finance palette candidates), want
each theme fully understandable in isolation, and value per-theme surface mapping freedom
more than DRY central wiring.
