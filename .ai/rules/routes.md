---
paths:
  - 'routes/**'
---

# Routes

## Route handlers and route file organization

Use controller-class handlers for any route with logic. Closures are acceptable only for
pure redirects, static `Inertia::render()` one-liners, or dev-sandbox pages. Split route
files by concern (`auth.php`, `settings.php`, `dev.php`) and `require` them from
`routes/web.php`.

## Middleware on routes, never on controllers

Assign middleware via route groups or per-route `->middleware()`. Never use controller
`HasMiddleware` implementations or `#[Middleware]` attributes. Global adjustments live in
`bootstrap/app.php`.
