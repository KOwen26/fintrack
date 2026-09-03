# Frontend

Rules for `resources/js/**`, `resources/views/**`, and `resources/css/**`. Svelte 5
runes, naming, directory layout, DataComposer, FormGenerator, badges, and Wayfinder
conventions are documented in `.ai/guidelines/laravel-inertia-svelte/rules/inertia-svelte-frontend.md` — these rules cover what that document does not.

## Icons: Solar/Tabler with `-bold-duotone` weight

Glob: `resources/js/**`

Icons are iconify classes with the `solar--` prefix (primary) or `tabler--` (secondary).
Default weight suffix is `-bold-duotone`; outline variant `-line-duotone`. Never use
`ph--*` (Phosphor) — it is not installed. Only `solar` and `tabler` are enabled in the
`@iconify/tailwind4` plugin config in `app.css`.

## Layout assignment: central switch + self-wrapping auth pages

Glob: `resources/js/app.ts`, `resources/js/pages/**`

The central `layout()` switch in `resources/js/app.ts` maps all app-page prefixes
(accounts, transactions, categories, household, settings/theme, reports, dev, dashboard)
to `DashboardLayout` and returns `null` for everything else. Auth pages get `null` and
self-wrap their content in `AuthLayout`; `home.svelte` self-wraps in `BaseLayout`. Never
declare a layout inside a page component. `AppLayout` is unused — do not reference it.

## Blade is shell-only

Glob: `resources/views/**`

The app has a single Blade host (`app.blade.php`) containing `@vite`, `@inertia`, and
font links. All UI is Svelte — never add Blade components, partials, or `@include`s.

## CSR-only: SSR stays disabled

Glob: `resources/js/**`

SSR configuration is commented out in `vite.config.js` and the app is client-side
rendered only. Do not enable SSR without a team decision.

## No i18n layer

Glob: `resources/js/**`

UI strings are hardcoded per-component in a mixed English/Indonesian vocabulary (e.g.
English labels with `'Simpan'`/`'Batal'` defaults). Do not introduce `__()` calls or
lang files without a team decision.

## Dual design system: DaisyUI + shadcn atoms on Tailwind v4

Glob: `resources/css/**`, `resources/js/components/**`

Tailwind v4 CSS-first configuration — no `tailwind.config.js`; theme lives in `app.css`
(`@theme inline` with CSS-var color scales). Two component families coexist by design:
DaisyUI-flavored primitives in `resources/js/components/ui/` and bits-ui-based shadcn
atoms in `resources/js/components/ui/atoms/` (with `data-slot` attributes and `index.ts`
re-exports). Compose classes through `cn()` (clsx + tailwind-merge); pick colors via
`ColorVariant` from `@/data/theme`, never raw palette classes.

## Toasts: svelte-sonner fed by flash props

Glob: `resources/js/**`

All user feedback flows through svelte-sonner toasts fed by the backend flash props
(`{type, message}`) via `useFlashToast()`. Layouts mount `<Toaster />` and call the hook
— pages never mount their own toaster. Never build alert-style feedback UIs.

## Schema-driven forms, tables, and charts

Glob: `resources/js/**`

Forms and tables are schema-driven: define a `DataSchema` in `resources/js/schema/` and
drive `<FormGenerator>` and TanStack Table through `DataComposer`. Charts use layerchart.
Before building new UI, check `resources/js/pages/dev/*` — it is the living
design-system sandbox demonstrating the components.
