---
version: alpha
name: FinTrack
description: Personal finance tracker — Laravel + Inertia + Svelte 5, DaisyUI themes, cobalt default
colors:
  primary: "oklch(38% 0.12 268)"
  primary-content: "oklch(95% 0.005 268)"
  secondary: "oklch(62% 0.06 255)"
  accent: "oklch(72% 0.16 78)"
  success: "oklch(52% 0.12 162)"
  info: "oklch(55% 0.12 230)"
  warning: "oklch(72% 0.16 78)"
  error: "oklch(55% 0.12 28)"
  base-100: "oklch(98% 0.008 80)"
  base-200: "oklch(93% 0.012 80)"
  base-300: "oklch(87% 0.015 80)"
  base-content: "oklch(18% 0.02 268)"
typography:
  body:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.5
  title:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "20px"
    fontWeight: 700
    lineHeight: 1.4
  amount-display:
    fontFamily: "ui-monospace, SFMono-Regular, Menlo, Consolas, monospace"
    fontSize: "48px"
    fontWeight: 600
    letterSpacing: "-0.025em"
  label:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "14px"
    fontWeight: 600
    letterSpacing: "0.1em"
  small:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "12px"
    fontWeight: 500
rounded:
  field: "4px"
  box: "8px"
  chip: "12px"
  pill: "9999px"
spacing:
  page-pad-mobile: "12px"
  page-pad-desktop: "20px"
  section-gap: "24px"
  card-pad-mobile: "20px"
  card-pad-desktop: "24px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.primary-content}"
    rounded: "{rounded.field}"
  button-outline-light:
    backgroundColor: "{colors.base-100}"
    textColor: "{colors.base-content}"
    rounded: "{rounded.field}"
  badge-soft:
    backgroundColor: "color-mix(in oklab, {colors.primary} 13%, transparent)"
    textColor: "{colors.primary}"
    rounded: "{rounded.field}"
  badge-soft-income:
    backgroundColor: "color-mix(in oklab, {colors.success} 13%, transparent)"
    textColor: "{colors.success}"
    rounded: "{rounded.field}"
  badge-soft-expense:
    backgroundColor: "color-mix(in oklab, {colors.error} 13%, transparent)"
    textColor: "{colors.error}"
    rounded: "{rounded.field}"
  badge-soft-transfer:
    backgroundColor: "color-mix(in oklab, {colors.info} 13%, transparent)"
    textColor: "{colors.info}"
    rounded: "{rounded.field}"
  card:
    backgroundColor: "{colors.base-100}"
    textColor: "{colors.base-content}"
    rounded: "{rounded.box}"
    padding: "20px"
  page-canvas:
    backgroundColor: "{colors.base-200}"
  icon-chip:
    backgroundColor: "color-mix(in oklab, {colors.base-content} 10%, transparent)"
    size: "40px"
    rounded: "{rounded.chip}"
---

# Design System: FinTrack

## Overview

**Creative North Star: "The Quiet Ledger"**

FinTrack is a product register surface: the design serves the task of recording and reviewing
money. It stays calm, flat, and precise — tonal depth instead of shadows, hairline borders
instead of outlines-and-glows, duotone icons, and tabular monospace numerals for every
currency value. The chrome (sidebar, header, bottom nav) is quiet DaisyUI; the color energy
is reserved for meaning: transaction flow (success/error/info), category and account
decoration colors chosen by the user.

The system rejects decoration that doesn't inform: no glassmorphism, no gradient text,
no side-stripe accents, no drop shadows on resting cards. Depth comes from the three-step
base surface ramp (100 → 200 → 300) and 1px hairlines derived from the ink color.

**Key Characteristics:**

- Flat tonal surfaces; DaisyUI `--depth: 0` everywhere; one popover shadow token for overlays
- OKLCH color pipeline; four swappable themes (cobalt default, verdant, ember, amethyst)
- Money is always mono/tabular; every amount carries a color-coded flow state
- Solar duotone iconography (Iconify), `bold-duotone` weight default
- Mobile cards are edge-to-edge (border-y only); desktop cards get borders + 8px radius
- User-authored decoration colors (Tailwind palette, Active subset only) tint account & category chips

## Colors

Cobalt is the default theme: a warm paper-white base carrying a deep cobalt-indigo primary,
with amber reserved as the single warm accent.

### Primary
- **Deep Cobalt** (oklch(38% 0.12 268)): primary actions, active navigation, links. The workhorse ink of the UI.
- **Primary Content** (oklch(95% 0.005 268)): text on primary fills.

### Secondary
- **Periwinkle Mist** (oklch(62% 0.06 255)): secondary buttons and quiet selection states.

### Tertiary
- **Amber Gold** (oklch(72% 0.16 78)): accent — highlights, creator chips, sparingly. Shares its hue with warning.
- **Flow Green / Azure / Signal Red** (oklch(52% 0.12 162) / oklch(55% 0.12 230) / oklch(55% 0.12 28)): success = income, info = transfer, error = expense. Never use flow colors decoratively.

### Neutral
- **Paper White** (oklch(98% 0.008 80)): base-100 — card surfaces.
- **Warm Gray 200** (oklch(93% 0.012 80)): base-200 — page canvas.
- **Warm Gray 300** (oklch(87% 0.015 80)): base-300 — pressed states, segmented tracks.
- **Cobalt Ink** (oklch(18% 0.02 268)): base-content — all text and hairlines.

### Named Rules
**The Flow Color Rule.** Green means in, red means out, azure means moved. These three colors
appear only on amounts, type badges, and flow indicators — never as background washes or decoration.

**The Hairline Rule.** Borders are 1px `color-mix(in oklab, base-content 10–15%, transparent)`.
Never pair a border with a soft drop shadow on the same resting element.

## Typography

**Display/Body Font:** Figtree (variable 300–900, with ui-sans-serif / system-ui fallback)
**Label/Mono Font:** system mono stack (`ui-monospace, SFMono-Regular, Menlo, Consolas`) for all currency numerals
**Available specials:** Arvo (serif) and Solway — for marketing moments only, never app UI labels

**Character:** One humanist sans carries everything; hierarchy is built from weight and ink
opacity, not from font changes. Money breaks the rule deliberately — mono numerals make
amounts scannable and honest.

### Hierarchy
- **Page Title** (700, 20px, 1.4): one per page, in the dashboard header.
- **Amount Display** (600, 48px mono, tracking −0.025em): the hero of detail pages; flow-colored.
- **Section Label** (600, 14px, +0.1em tracking, uppercase, ink-50): card section headers ("CATEGORY", "DETAILS") only.
- **Body** (400–500, 14px, 1.5): list rows, form values, notes.
- **Small** (500, 12px): badges, metadata, helper text.

### Named Rules
**The Money-is-Mono Rule.** Every currency value renders in the mono stack with tabular
numerals. If a number is money and isn't mono, it's wrong.

## Layout

**App shell.** Desktop is sidebar + header; mobile swaps the sidebar for a bottom nav.
The dashboard header carries the back arrow, breadcrumb trail, and the page title —
one title per page, always in the header, never repeated in content.

**Spacing scale (small and deliberate — a register, not a gallery):**
- Page gutter: 12px mobile → 20px desktop
- Card padding: 20px mobile → 24px desktop
- Stack rhythm: 20–24px between related elements; 24px section gap

**Surface strategy.** base-200 canvas behind base-100 cards. Mobile cards run edge-to-edge
with hairline `border-y` only — boxing (full border + 8px radius) is a desktop privilege.

**Forms.** Single column by default; `FormGenerator` variants switch to 2/3/4-column grids
at `md+` (768px+).

**Responsive rule.** Behavior is structural, never fluid typography: bottom nav ↔ sidebar,
edge-to-edge ↔ boxed cards, form column counts. The type scale is fixed because users
work in a task at consistent DPI.

## Elevation & Depth

Flat by default. DaisyUI runs at `--depth: 0`; resting cards carry a 1px hairline and no
shadow. Depth is communicated tonally: base-200 canvas behind base-100 cards, base-300 for
pressed/segmented tracks. The single sanctioned shadow is the popover token
(`0 7px 12px 3px hsl(0 0% 0 / 30%)`) for floating layers only — modals, popovers, toasts.

**The Flat-At-Rest Rule.** If a shadow appears at rest on a card or button, remove it.
Shadows exist only while an element floats above the page.

## Shapes

The radius scale is tight and purposeful — four steps, each with a job:

- **Field (4px, `--radius-field`):** inputs and buttons. Barely-there softening that keeps controls precise.
- **Box (8px, `--radius-box`):** cards, modals, popovers.
- **Chip (12px):** the 40×40 icon chips — enough curvature to read as a container for decoration color.
- **Pill (9999px):** badges only; reserved for status text.

Mobile cards carry no radius at all (edge-to-edge); radius joins the border as a desktop
privilege. Nothing rounds past 16px, and pills never apply to cards or buttons.

## Components

### Buttons
- **Shape:** 4px radius (`--radius-field`), 48px default height, 14px/600 label, icon 20px.
- **Primary:** deep cobalt fill + primary-content text. Hover brightens ~10%.
- **Outline:** base-100 fill, 1px hairline border, ink text (used for Edit/secondary actions).
- **Outline-error:** hairline border + error text for destructive secondary actions (Delete).
- **Focus:** 2px info outline, 2px offset. Active: 98% scale.

### Badges
- **Style:** 4px radius (default `rounded`), 12px/500 label. Variants: solid, outline, outline-dash, soft.
- **Soft** is the type-badge standard: 13% color-mix fill + flow-colored text
  (Income → success, Expense → error, Transfer → info).

### Cards / Containers
- **Background:** base-100 on base-200 canvas.
- **Corner/border:** mobile — edge-to-edge, `border-y` hairlines only; desktop — 8px radius with full 1px border.
- **Padding & rhythm:** 20px mobile → 24px desktop; 20–24px stack gaps.

### Icon Chips (signature)
40×40px, 12px radius, decoration color at 12% alpha as fill, matching solid icon at 100%;
fallback `base-content/10` fill with ink icon. Every account and category renders as a chip
with name + type/parent below. Icons come from the curated Solar duotone set only.

### Navigation
Sidebar (desktop) + header with breadcrumbs + bottom nav (mobile). Inactive nav items are
ink at 60%; active items are primary — never accent.

### Forms
DaisyUI inputs at 4px field radius, hairline borders, base-100 fill; focus ring in info.
Complex forms are generated from DataComposer schemas (`FormGenerator`).

## Do's and Don'ts

### Do:
- **Do** render every currency amount in mono with `Rp` as a smaller sibling unit.
- **Do** use the three flow colors exactly where money moves: amount text, type badge, accent bar.
- **Do** derive tints with `color-mix(in oklab, …)` from theme tokens — never hardcode hex in components (decoration colors excepted; they are user data).
- **Do** keep mobile cards edge-to-edge and let desktop own the borders and radii.
- **Do** pick decoration colors only from the Active subset of the Tailwind palette (`resources/js/data/decoration-colors.json`).

### Don't:
- **Don't** use `border-left`/`border-right` stripes wider than 1px as colored accents.
- **Don't** put drop shadows on resting cards, or pair borders with wide shadows anywhere.
- **Don't** use display fonts (Arvo, Solway) in app UI labels, buttons, or data.
- **Don't** round cards beyond 16px (system standard is 8px); pills are for badges only.
- **Don't** invent new icon sets — Solar `bold-duotone` primary, Tabler `line-duotone` secondary.
- **Don't** color large surfaces with flow colors; they are text-and-badge colors, not backgrounds.
