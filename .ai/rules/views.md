---
paths:
  - 'resources/views/**'
---

# Views

## Blade is shell-only

The app has a single Blade host (`app.blade.php`) containing `@vite`, `@inertia`, and
font links. All UI is Svelte — never add Blade components, partials, or `@include`s.
