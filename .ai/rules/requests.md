---
paths:
  - 'app/Http/Requests/**'
---

# Requests

## Form Requests for real rulesets; inline validate() for small checks

Use dedicated Form Request classes for endpoints with real validation — business
actions and multi-field payloads. Inline `$request->validate([...])` is fine for
small, non-business checks (a couple of columns, e.g. a password confirmation before
a destructive action) — that is deliberate, not starter-kit drift. `Validator::make()`
stays Fortify-scaffold territory.

## Authorization lives in the Form Request when one exists

When an action is validated by a Form Request, its `authorize()` method owns the
policy check — resolve the route model from the request and authorize there. Actions
without a Form Request (render pages, archive/restore toggles) are authorized in the
controller with `$this->authorize()`.
