# CSS Architecture Proposal D — Drop DaisyUI, Full shadcn

> **Status:** Proposal — **incremental execution in progress** (see §1.5).
> **Updated 2026-10-05:** Forms, Badge, and Card families complete; inventories below carry
> per-item migration status. Pending counts are from the 2026-09-30 baseline scan.
> **Siblings:** `2026-09-29-css-config-option-a-daisyui-first.md`,
> `2026-09-29-css-config-option-b-shadcn-first.md`,
> `2026-09-30-css-config-option-c-app-first.md`. Shared baseline: §1 of Proposal A.
>
> Usage data below comes from a full-codebase scan (2026-09-30, 484 files: 336 `.svelte`,
> 147 `.ts`, 1 blade) with every candidate token verified against the installed
> `node_modules/daisyui@5.7.22` CSS before counting.

## TL;DR

Remove DaisyUI entirely — plugin, theme CSS, and package. shadcn utilities plus the
existing bits-ui-based `ui/` components take over. **"Drop DaisyUI" is two decisions
disguised as one:**

1. **The token layer** — solved by Proposal B or C (`tokens.css` registers the color
    vocabulary DaisyUI's plugin used to register). This is work you'd do under B/C anyway.
2. **The component purge** — replace DaisyUI component classes. The scan says this is
    smaller than it looks: **125 direct production occurrences, ~10 wrapper rewrites,
    and exactly one component with no shadcn equivalent (Dock)**.

```
1,012 daisy-dependent class occurrences (2026-09-30 baseline)
├─ 783 color utilities (bg-primary, text-base-content…)  → survive via tokens.css ✅
├─  76 contained in ui/ wrappers                          → 19 done (input family, card-title, spinner); button/alert/modal/menu remain
└─ 128 direct component classes (125 prod, 3 dev)         → 44 done (card + badge + input families); ~84 remain
```

---

## 1. What actually needs replacing — the three buckets

### Bucket 1 — Color utilities (783 occurrences): survive untouched

`bg-primary`, `text-base-content`, `border-base-content/10`, `text-secondary-content`, …
are **Tailwind utilities** that resolve `--color-*` CSS variables at runtime. They are
registered by DaisyUI's plugin (`functions/variables.js`), not by its component CSS.
Removing `@plugin 'daisyui'` removes the registration — so `tokens.css` must add it back:

```css
@theme inline {
    /* already in B/C for the shadcn side */
    --color-background: var(--background);
    --color-primary: var(--primary);
    …

    /* D addition: register the daisy-vocab names the codebase still uses */
    --color-base-100: var(--background);
    --color-base-200: var(--card);
    --color-base-300: var(--border);
    --color-base-content: var(--foreground);
    --color-primary-content: var(--primary-foreground);
    --color-secondary: var(--secondary);
    --color-secondary-content: var(--secondary-foreground);
    --color-accent: var(--accent);            /* … etc. for accent/neutral/info/ */
    --color-success: var(--success);          /* success/warning/error + -content  */
    --color-info: var(--info);
    --color-warning: var(--warning);
    --color-error: var(--destructive);
    --radius-box: var(--radius);              /* rounded-box (3 uses) */
}
```

Distribution: 423 `base-*`, 408 `*-content`; by prefix `text` 394 / `bg` 296 /
`border` 95. Top direct usages: `text-base-content/50` ×83, `text-base-content` ×37,
`text-base-content/40` ×32, `border-base-content/10` ×28. All keep working, then migrate
names opportunistically (`text-base-content` → `text-foreground`) — at the end of that
road the daisy-vocab registrations themselves can be deleted.

The 25 custom shade utilities (`bg-primary-100`…`-950`, from the `app.css` ramp wall)
depend only on the base vars, not on the plugin — same story as B/C step "delete the ramps".

### Bucket 2 — Contained in `ui/` wrappers (76 occurrences): rewrite the wrapper only

| Wrapper | Daisy classes inside | Rewrite |
|---|---|---|
| `ui/button.svelte` | `btn` + 10 colors + `btn-outline/ghost/soft/link` | **Mostly deletion** — the full shadcn `tv()` variants already coexist in the file; extend with `size="xs/icon"` support |
| `ui/badge.svelte` | `badge` + 10 colors + `badge-soft/outline/dash` | ✅ **DONE (10-05)** — utility color maps + `size` prop (`sm/default/lg`) |
| `ui/alert.svelte` | `alert` + `alert-info/success/warning/error/soft/outline/dash` | ✅ **DONE (10-05)** — utility maps + grid base; no-op variants fixed (§5) |
| Input family | `input` ×9, `textarea`, `radio`, `toggle`, `file-input` | ✅ **DONE (10-05)** — `inputClasses`/`inputGroupClasses` exports, responsive `min-h-12 md:min-h-10` rhythm |
| Modal wrappers | `modal` ×8, `btn-square` ×2 | ✅ **done** — bits-ui Dialog + atoms/alert-dialog, pure; the `btn-square` close button belongs to the Button pass |
| Drawer wrappers | `drawer-content` ×2, `drawer-overlay` | ✅ **done** — atoms/drawer, pure utilities |
| Menu wrappers | `dropdown-content` ×2, `menu`/`dropdown`/`dropdown-end` ×2 each | existing bits-ui dropdown-menu |
| Misc | `tooltip-content`, `carousel-item`, ~~`card-title` ×3~~, `skeleton`, `rounded-box` ×2 | tooltip/carousel/skeleton ✅ done; `rounded-box` ×2 remain (bottom-nav, datatable-row-action) |
| Spinner | `loading`, `loading-sm`, `loading-spinner` | ✅ **DONE (10-05)** — iconify spinner + `animate-spin` |

No page or module changes — this is the payoff of the wrapper discipline.

### Bucket 3 — Direct usage outside `ui/` (128 occurrences; 125 production, 3 dev)

The complete replacement list, by descending count:

| Daisy class (direct) | Count | Replace with | Migration status |
|---|---|---|---|
| `btn-sm` | 18 | `<Button size="sm">` | ⏳ pending — mechanical |
| `btn-circle` | 13 | `<Button class="size-8 rounded-full p-0">` or new `size="icon"` | ⏳ pending — mechanical |
| `btn-xs` | 12 | `<Button size="xs">` (add size to tv config) | ⏳ pending — mechanical |
| `card` | 11 | `<Card>` — composes `atoms/card` | ✅ **done (10-05)** |
| `card-title` | 7 | `<CardTitle>` / `CardHeader` | ✅ **done (10-05)** |
| `card-actions` | 7 | `<CardFooter>` or right-aligned flex in header action | ✅ **done (10-05)** |
| `card-body` | 7 | `<CardContent>` | ✅ **done (10-05)** |
| `skeleton` | 5 | `ui/skeleton.svelte` | ✅ done — component in use |
| `btn` | 4 | `<Button>` | ⏳ pending |
| `avatar` | 4 | shadcn Avatar | ✅ done — already migrated |
| `badge` | 3 | `ui/badge.svelte` | ✅ **done (10-05)** |
| `input` | 3 | `ui/input.svelte` | ✅ **done (10-05)** |
| `modal` | 2 | existing dialog/alert-dialog wrappers | ✅ done — bits-ui, pure |
| `btn-square` | 2 | `<Button class="size-8 p-0">` | ⏳ pending |
| `badge-neutral` | 2 | `ui/badge.svelte color="dark"` | ✅ **done (10-05)** |
| `btn-primary` | 2 | `<Button color="primary">` | ⏳ pending |
| `badge-sm` | 2 | `ui/badge.svelte size="sm"` | ✅ **done (10-05)** |
| `btn-active` | 2 | `ui/toggle-group` (exists) | ⏳ pending |
| `btn-wide` | 2 | `<Button class="w-full max-w-64">` | ⏳ pending |
| `hero` | 2 | flex utilities div | ✅ done — already migrated |
| `input-sm` | 2 | `ui/input.svelte` | ✅ **done (10-05)** |
| `dropdown`, `dropdown-end`, `menu`, `menu-title` | 1 each | `ui/dropdown-menu` (dashboard-header) | ⏳ pending |
| `divider` | 1 | separator utilities / `ui/separator` | ⏳ pending |
| `btn-ghost`, `btn-neutral`, `btn-block` | 1 each | Button props | ⏳ pending |
| `join` | 1 | `ui/toggle-group` (toggleable-grid) | ⏳ pending |
| `table`, `table-sm` | 1 each | `ui/table` (reports/trend) | ✅ done — already migrated |
| `rounded-box` | 1 | `rounded-lg` | ⏳ pending — 2 left (bottom-nav, datatable-row-action) |
| **`dock`, `dock-sm`, `dock-active`, `dock-label`** | 1 each | in-house `ui/dock.svelte` | ✅ done — hand-rolled with variant/position maps |

**Dev-only (188 occurrences, 7 files)**: `pages/dev/design-system/color.svelte` (59),
`dev/examples/dashboard.svelte` (58), `dev/color.svelte` (36), `design-system/accounts.svelte`
(17), `layouts/form/design.svelte` — mostly a DaisyUI color-token swatch gallery; rewrite
against the new token names or delete the stale pages.

---

## 1.5 Migration progress (updated 2026-10-05)

Executing in small bits, family by family.

### ✅ Complete

| Family | Scope | What shipped |
|---|---|---|
| **Forms** (`ui/forms/**`) | `input` ×9, `textarea`, `radio`, `toggle`, `file-input`, `loading-*` spinner, `masked/password/phone/currency` inputs, `account/category/date` selects, svelecte CSS vars | `input.svelte` exports `inputClasses` / `fileInputClasses` / `inputGroupClasses`; single responsive rhythm `min-h-12 md:min-h-10` · `px-3` · `text-base md:text-sm`; groups consume `inputGroupClasses` (with `items-center`); password → overlay-button pattern; `--sv-*` vars use `--color-border` |
| **Badge** | `ui/badge.svelte` + all direct usages (`security.svelte` ×2, `account-badge`) | color×variant utility maps (solid/outline/dash/soft); `size` prop `sm/default/lg` — default preserves the daisy-md metrics the app ships; `account-badge` passes decoration colors through Svelte `--props`; zero daisy badge classes remain |
| **Card** | `ui/card.svelte` now composes `atoms/card/*`; 6 raw cards in `profile.svelte`/`security.svelte` migrated | route-3 consolidation — composite is sugar over the atoms; atoms `border-neutral-500` bug fixed → `border-border`; new `description`/`descriptionClass` props; shadcn padding model (`py-5` container + `px-6` sections); dashboard's 5 redundant `class="p-5"` removed (would double-pad); `card-title`/`card-footer` classes eliminated |
| **Alert** | `ui/alert.svelte` (10-05) | Badge pattern: color×variant utility maps; **fixes the no-op variants bug** — `primary/secondary/accent/neutral` were never DaisyUI classes, now they're real; grid base `grid-cols-[auto_1fr]` (iconify `<i>`-friendly, unlike the placeholder's svg-only collapse trick) |
| **Modals** | already migrated outside this log | bits-ui Dialog (`ui/modals/modal.svelte`) + atoms/alert-dialog (`alert-modal` and its wrappers) — pure utilities; only a `btn-square` on the close button remains, which belongs to the Button pass |

**Verified already-migrated during the 10-05 audit** (missed by the baseline scan review):
**Dock** (in-house `ui/dock.svelte` with variant/position utility maps — the hand-roll is
done), **hero**, **skeleton** (`ui/skeleton.svelte` in use), **avatar**, **table**,
**drawer-content/overlay**, **tooltip-content**, **carousel-item**. No daisy classes found
for any of these in class-string context.

Verified zero via class-context greps: `card*`, `badge*`, the forms input family, and
`var(--input-color)`. Remaining `'input'`/`'textarea'` grep hits are FormGenerator type
strings and flatpickr DOM queries — not classes.

**Also shipped during migration:**

- **`--input-color` latent breakage fixed** — the DaisyUI-internal var was used by
  password/phone/currency/account-select and svelecte's `--sv-*` vars; all now resolve
  `--color-border`, so these survive the plugin removal.
- **`account-select` / `category-select` removed from the FormGenerator vocabulary**
  (`field-input` + `form-helper`) — domain selects compose directly, as `transaction-form`
  always did. Their trigger styling comes from `inputGroupClasses`.
- flatpickr's `altInputClass` concern (§5) is resolved — `date-input` passes `inputClasses`.

### ⏳ Remaining (verified 10-05 — much shorter than the baseline)

1. **Button** — the big one: wrapper rewrite (the full shadcn `tv()` variants already
   coexist in the file, so it's mostly deletion) + the remaining direct `btn-*` occurrences
   (`class="btn-sm"` on `<Button>` calls across settings/nav/transaction pages,
   `btn-square` ×2 incl. modal.svelte's close button, `btn-circle` in dashboard-header,
   `btn btn-block btn-primary` in transaction-list-filter) + fix the `--color-dark`
   link-variant bug (§5).
2. **dashboard-header.svelte** — raw `dropdown dropdown-end` + `menu`/`menu-title` + `divider`.
3. **datatable-row-action + datatable-v8-row-action** — `dropdown dropdown-end` +
   `menu dropdown-content rounded-box` ×2 files.
4. **toggleable-grid** — `join` + `btn-active` ×2; **bottom-nav dockItem** — `rounded-box` ×1.
5. Dev color-gallery pages.
6. Token layer (B or C) + `@plugin 'daisyui'` removal — the unchanged finish line (§6).

---

## 2. Preview — the final CSS

What the CSS layer looks like when D is done. Four files, no DaisyUI anywhere.

### 2.1 `themes/electric.css` — unchanged from Proposal B/C

Theme files are identical to B/C — D only changes `tokens.css`, `app.css`, and components:

```css
/* Theme: Electric — navy, lime & teal (shape: reference/shadcn-stock.css) */
[data-theme='electric'] {
    color-scheme: light;

    --background: oklch(98.5% 0.001 288.5);
    --foreground: oklch(30.1% 0 23.7);
    --card: oklch(97% 0.003 265.4);
    --card-foreground: var(--foreground);
    --popover: var(--background);
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

    /* app-specific status colors (first-class, not aliases) */
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
    --radius: 0.5rem;
}
```

### 2.2 `tokens.css` — transitional (migration step 2)

The full shadcn registration **plus one compatibility block** that keeps the 783 daisy-vocab
utilities alive while names migrate. This is the entire "bridge" under D — and it's
explicitly temporary:

```css
@theme inline {
    /* ── Typography & effects (absorbed from app.css) ── */
    --font-sans: 'Nunito', 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
    --font-heading: 'Instrument Sans', 'Nunito', ui-sans-serif, system-ui, sans-serif;
    --text-2xs: 0.625rem;
    --shadow-popover: 0px 7px 12px 3px hsla(0 0% 0% / 30%);

    /* ── Surfaces ── */
    --color-background: var(--background);
    --color-foreground: var(--foreground);
    --color-card: var(--card);
    --color-card-foreground: var(--card-foreground);
    --color-popover: var(--popover);
    --color-popover-foreground: var(--popover-foreground);

    /* ── Brand + status (app-specific extensions included) ── */
    --color-primary: var(--primary);
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
    --color-success-foreground: var(--success-foreground);
    --color-info: var(--info);
    --color-info-foreground: var(--info-foreground);
    --color-warning: var(--warning);
    --color-warning-foreground: var(--warning-foreground);
    --color-error: var(--error);
    --color-error-foreground: var(--error-foreground);

    /* ── Structure ── */
    --color-border: var(--border);
    --color-input: var(--input);
    --color-ring: var(--ring);
    --radius-sm: calc(var(--radius) - 4px);
    --radius-md: calc(var(--radius) - 2px);
    --radius-lg: var(--radius);
    --radius-xl: calc(var(--radius) + 4px);

    /* ── Charts & sidebar — derived defaults, overridable per theme ── */
    --color-chart-1: var(--primary);
    --color-chart-2: var(--secondary);
    --color-chart-3: var(--accent);
    --color-chart-4: var(--success);
    --color-chart-5: var(--info);

    --color-sidebar: var(--background);
    --color-sidebar-foreground: var(--foreground);
    --color-sidebar-primary: var(--primary);
    --color-sidebar-primary-foreground: var(--primary-foreground);
    --color-sidebar-accent: var(--accent);
    --color-sidebar-accent-foreground: var(--accent-foreground);
    --color-sidebar-border: var(--border);
    --color-sidebar-ring: var(--primary);

    /* ═══ DaisyUI-vocabulary compatibility — DELETE when name migration completes ═══ */
    --color-base-100: var(--background);
    --color-base-200: var(--card);
    --color-base-300: var(--border);
    --color-base-content: var(--foreground);
    --color-primary-content: var(--primary-foreground);
    --color-secondary-content: var(--secondary-foreground);
    --color-accent-content: var(--accent-foreground);
    --color-neutral: var(--secondary);            /* 6 remaining text-neutral uses */
    --color-neutral-content: var(--secondary-foreground);
    --color-success-content: var(--success-foreground);
    --color-info-content: var(--info-foreground);
    --color-warning-content: var(--warning-foreground);
    --color-error-content: var(--error-foreground);
    --radius-box: var(--radius-lg);               /* last rounded-box user */
}

@layer base {
    body {
        @apply bg-background text-foreground;
    }

    button {
        @apply cursor-pointer;
    }

    /* Headings use the heading family (absorbed from app.css) */
    h1, h2, h3, h4, h5, h6, legend, th {
        font-family: var(--font-heading);
    }

    /* Scrollbar rules — consolidated from app.css / shadcn.css / flatpickr.css */
}
```

Note `bg-primary`, `text-error`, `text-success-foreground` etc. resolve from the **main**
block already — the compatibility block only carries what pure shadcn doesn't name:
`base-*`, `*-content` pairs, `neutral`, `rounded-box`.

### 2.3 `tokens.css` — end state (compat block deleted)

After the opportunistic rename pass (`text-base-content` → `text-foreground`,
`text-primary-content` → `text-primary-foreground`, `bg-base-200` → `bg-card`, …), the
file above loses its entire compatibility block. What remains is **the pure shadcn token
layer plus the app's status-color extensions** — roughly 60 lines, one vocabulary, zero
translation. That deletion is the finish line of D.

### 2.4 `app.css` — final wiring (~20 lines)

```css
@import 'tailwindcss';
@import 'tw-animate-css';
@import './fonts.css';
@import './themes/index.css';
@import './tokens.css';
@import './vendors/flatpickr.css';

@plugin '@iconify/tailwind4' {
    prefixes: solar, tabler;
    scale: 1.5;
}

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.js';
@source '../**/*.ts';
@source '../**/*.svelte';

@custom-variant dark (&:where(.dark, .dark *));
```

No `@plugin 'daisyui'`. No import of daisy theme CSS. `flatpickr.css` keeps only its
`--fp-*` geometry knobs — it consumes `--popover`, `--primary`, `--border`, `--radius`
directly with no bridge.

### What disappeared vs today

| Removed | Fate |
|---|---|
| `colors.css` (12.8 KB) | absorbed into `themes/*.css` |
| `daisyui.css` (15.6 KB) | deleted — palettes moved to themes, components replaced |
| `shadcn.css` (2.5 KB) | absorbed into `tokens.css` |
| ramp wall (~120 lines in `app.css`) | replaced by opacity modifiers (`bg-primary/10`); the 4 direct shade uses (`from-primary-800`…) migrate too |
| 3× `@custom-variant dark`, duplicate imports, `--radius` collision | deleted with the flatten |
| `daisyui` package | `npm rm daisyui` |
| `reference/shadcn-token.css` | stays as the shape reference for theme files |

## 3. The hand-roll list

1. ~~**Dock**~~ — ✅ done: in-house `ui/dock.svelte` (utility maps for variants and
   positions); `bottom-nav.svelte` consumes it.
2. ~~**Avatar**~~ — ✅ done: already migrated during earlier passes.
3. **Button interaction polish** — still pending: DaisyUI's `.btn` ships active-state
   translate, touch-action, disabled styling for free. The existing `tvButtonVariants`
   base covers disabled/focus/ring; add the active translate + `touch-action: manipulation`.

## 4. Hotspots (where the direct work concentrates)

| File(s) | Occurrences | What's there |
|---|---|---|
| ~~`settings/security.svelte` + `settings/profile.svelte`~~ | 28 + 27 | ✅ fully migrated — Card + Badge + description props; only `class="btn-sm"` on `<Button>` calls remains, for the button pass |
| `mobile-transaction-form` / `transfer-form` / `transaction-form` / `transaction-detail` | 23–25 each | modal, btn-square, input, card |
| `pages/reports/*` (5 files) | ~70 total | `btn-circle`/`btn-xs`/`btn-sm` icon buttons + one raw table |
| `dashboard-header.svelte` | 13 | raw dropdown + menu + divider |
| `bottom-nav.svelte` | 6 | the Dock |
| `dashboard`, `accounts/show`, `base-account-card`, `transaction-list-filter`, `home`, `dashboard-sidebar`, `mobile-page-layout`, `toggleable-grid`, `account-badge`, `categories/index`, `transactions/create`, `dashboard-header-mobile` | 6–18 each | mixed singles from the table above |

## 5. Incidental findings (true regardless of A/B/C/D)

- ~~**Existing bug:** `ui/alert.svelte` no-op color variants~~ — ✅ resolved (10-05): the
  utility maps make `primary/secondary/accent/neutral` real colors (they were never
  DaisyUI classes, so they silently did nothing before).
- ~~flatpickr injects `input` via `altInputClass`~~ — ✅ resolved: `date-input` passes
  `inputClasses`, so the engine-injected class is ours.
- ~~`--input-color` DaisyUI-internal var~~ — ✅ resolved during the forms pass: password/
  phone/currency/account-select + svelecte `--sv-*` vars now use `--color-border`.
- **No dynamic daisy class construction** (`btn-${…}`) anywhere — the entire migration is
  grep-safe.
- 96 shadcn-style semantic class patterns (`select-trigger`, `dropdown-menu-*`,
  `table-row`, `alert-dialog-*`, `drawer-header`, …) are already pure and untouched.
- `ui/card.svelte` (composing `atoms/card`), `ui/checkbox.svelte`, `ui/tabs.svelte`,
  `ui/badge.svelte`, and the forms family contain **zero** daisy classes — the migration
  pattern is proven in-repo.

## 6. Migration steps (safe order)

| # | Step | Risk | Status |
|---|---|---|---|
| 1 | Execute **B or C's token layer first** (themes + tokens.css) — D consumes it | per B/C | ⏳ pending |
| 2 | Add the daisy-vocab utility registrations to `tokens.css` (§1 Bucket 1) + `rounded-box` | low | ⏳ pending |
| 3 | Rewrite the `ui/` wrappers (§1 Bucket 2), incl. tv `size` variants; add Avatar; fix alert no-ops | medium | ◐ partial — input family, badge, spinner, alert done; button + menu wrappers pending |
| 4 | Hand-roll the Dock replacement; parity-test against current bottom-nav | medium | ✅ done — in-house `ui/dock.svelte` |
| 5 | Replace direct usage by hotspot order: settings pages → transaction forms → reports → navigation → misc singles | medium | ◐ partial — settings pages done (44/128 occurrences); transaction forms/reports/navigation pending |
| 6 | Rewrite or delete the dev color-gallery pages | low | ⏳ pending |
| 7 | Remove `@plugin 'daisyui'`, delete `daisyui.css`/`themes.css` plugin blocks, `npm rm daisyui` | low | ⏳ pending |
| 8 | Full visual pass: all 11 themes × key screens (settings, reports, transaction create/detail, mobile nav) | — | ⏳ pending |
| 9 | (Ongoing) migrate `text-base-content` → `text-foreground` etc. on touch; eventually delete the daisy-vocab registrations | opportunistic | ◐ ongoing — new code still adds `base-*` utilities (e.g. card descriptions use `text-base-content/70`) |

**Effort estimate:** token layer (B/C scope) + ~1–2 days wrappers + ~2–4 days direct
swaps + ~1 day Dock ≈ **about a week of focused work**.

## 7. Trade-offs

**Pros**

- **One styling system.** No bridge, no adapter, no dual vocabulary to keep translated —
  the entire A/B/C "where do the vocabularies meet" question eventually dissolves.
- The token registrations in `tokens.css` are the *only* remnant, and they're deletable
  as `text-base-content`-style usage migrates to `text-foreground`.
- Drops DaisyUI's upgrade churn, theme-controller machinery, and unused component CSS.
- The scan shows the codebase is already structurally shadcn — D finishes a migration
  that's ~80% done.

**Cons**

- **Loses free components forever** — Dock (in use), plus anything attractive later
  (timeline, rating, mockup, carousel polish, chat).
- You own button/badge/alert/input visual polish across 11 themes.
- ~1 week of regression surface across ~25 production files.
- One new component to build and maintain (Dock).
- If you keep daisy-vocab utilities as aliases long-term, you still maintain the name
  mapping — just as token aliases instead of component CSS.

## 8. Choosing among A / B / C / D

| Axis | A — daisyui-first | B — shadcn-first | C — app-first | **D — drop daisy** |
|---|---|---|---|---|
| DaisyUI's role | theme engine + components | components + vocab | components + vocab | **removed** |
| Palette store | plugin DSL | plain CSS (shadcn names) | plain CSS (both names) | **B or C's** |
| Bridge/adapter | central bridge | central adapter | per-theme aliases | **token registrations only, deletable over time** |
| Migration effort | lowest | low–medium | medium | **~1 week + B/C token work** |
| New UI code | none | none | none | **Dock + Avatar + button polish** |
| Long-term maintenance | daisy + bridge | adapter | alias guardrail | **single system** |

- **A** — fastest, keep DaisyUI conventions.
- **B** — own tokens centrally; DaisyUI stays as component library.
- **C** — own tokens per-theme; DaisyUI stays as component library.
- **D** — **B or C first**, then subtract DaisyUI entirely: one system, one vocabulary,
  at the cost of the component purge and owning the polish.

**Choose D when:** you're already committed to the B/C token work, want a single styling
system with no translation layer to maintain, and accept ~a week of mostly-mechanical
migration plus one hand-rolled Dock to get there.
