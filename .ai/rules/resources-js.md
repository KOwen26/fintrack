---
paths:
  - 'resources/js/**'
  - 'resources/css/**'
---

# Frontend — JS & CSS

Svelte 5 runes, naming, directory layout, DataComposer, FormGenerator, badges, and
Wayfinder conventions are documented in
`.ai/guidelines/laravel-inertia-svelte/rules/inertia-svelte-frontend.md` — these rules
cover what that document does not.

## Icons: Solar/Tabler with `-bold-duotone` weight

Icons are iconify classes with the `solar--` prefix (primary) or `tabler--` (secondary).
Default weight suffix is `-bold-duotone`; outline variant `-line-duotone`. Never use
`ph--*` (Phosphor) — it is not installed. Only `solar` and `tabler` are enabled in the
`@iconify/tailwind4` plugin config in `app.css`.

## Layout assignment: central switch + self-wrapping auth pages

The central `layout()` switch in `resources/js/app.ts` is a deny-list: only the `auth`
prefix returns `null`; everything else (including `app/*` and `dev/*`) falls through to
`DashboardLayout`. Auth pages get `null` and self-wrap their content in `AuthLayout`.
Never declare a layout inside a page component. `AppLayout` and `BaseLayout` are
unused — do not reference them.

## CSR-only: SSR stays disabled

SSR configuration is commented out in `vite.config.js` and the app is client-side
rendered only. Do not enable SSR without a team decision.

## No i18n layer

UI strings are hardcoded per-component in a mixed English/Indonesian vocabulary (e.g.
English labels with `'Simpan'`/`'Batal'` defaults). Do not introduce `__()` calls or
lang files without a team decision.

## Dual design system: DaisyUI + shadcn atoms on Tailwind v4

Tailwind v4 CSS-first configuration — no `tailwind.config.js`; theme lives in `app.css`
(`@theme inline` with CSS-var color scales). Two component families coexist by design:
DaisyUI-flavored primitives in `resources/js/components/ui/` and bits-ui-based shadcn
atoms in `resources/js/components/ui/atoms/` (with `data-slot` attributes and `index.ts`
re-exports). Compose classes through `cn()` (clsx + tailwind-merge); pick colors via
`ColorVariant` from `@/data/theme`, never raw palette classes.

## Toasts: svelte-sonner fed by flash props

All user feedback flows through svelte-sonner toasts fed by the backend flash props
(`{type, message}`) via `useFlashToast()`. Layouts mount `<Toaster />` and call the hook
— pages never mount their own toaster. Never build alert-style feedback UIs.

## Schema-driven forms, tables, and charts

Forms and tables are schema-driven: define a `DataSchema` in `resources/js/schema/` and
drive `<FormGenerator>` and TanStack Table through `DataComposer`. Charts use layerchart.
Before building new UI, check `resources/js/pages/dev/*` — it is the living
design-system sandbox demonstrating the components.

## Svelte component block order

Top-level blocks in `.svelte` files follow this order: `<script module>` → `<script>` →
markup → `{#snippet}` declarations → `<style>`. Snippets are declared *after* the markup
that references them (Svelte hoists snippet declarations, so textual order is free).
Enforced by the `personal-svelte/sort-tags` ESLint rule (defined inline in
`eslint.config.js`); it auto-fixes on `pnpm exec eslint --fix`.
