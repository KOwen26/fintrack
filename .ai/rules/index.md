# Project Rules Index

Committed, area-grouped conventions for this codebase. Before creating or editing any file,
read every rule file whose globs cover the path(s) in scope, and grep this directory for
relevant keywords — a path match alone can miss cross-cutting rules.

| Glob                              | Rule file(s)                                            |
| --------------------------------- | ------------------------------------------------------- |
| `app/Http/Controllers/**`         | `backend-http.md`                                       |
| `routes/**`                       | `backend-http.md`                                       |
| `app/Services/**`                 | `backend-architecture.md`                               |
| `app/Listeners/**`                | `backend-architecture.md`                               |
| `app/Helpers/**`                  | `backend-architecture.md`                               |
| `app/Console/Commands/**`         | `console.md`                                            |
| `app/Models/**`                   | `backend-models.md`                                     |
| `app/**` (cross-cutting)          | `backend-architecture.md` (PHPDoc style, dates, eager loading) |
| `database/migrations/**`          | `database.md`                                           |
| `tests/**`                        | `testing.md`                                            |
| `resources/js/**`                 | `frontend.md`                                           |
| `resources/views/**`              | `frontend.md`                                           |
| `resources/css/**`                | `frontend.md`                                           |

Structural architecture conventions (service pattern, DTOs, events/listeners, enums,
policies, caching core, Form Requests, migration column types) are documented separately in
`.ai/guidelines/laravel-inertia-svelte/rules/` — these files complement them with the
project-specific conventions the guidelines do not cover.
