# Project Rules — Reader Notes

This directory holds committed, area-grouped conventions for this codebase. The
`index.md` table is **generated** by the `record-rule` tool from each file's
`paths:` frontmatter — edit frontmatter, never the table by hand.

A rule that spans two areas gets **one home** — the file that owns the decision (where
the next agent makes the choice the rule governs) — never a duplicated copy in each
area file. Area files are created lazily: don't add a file until a rule is scoped to
it.

Before creating or editing any file, find the row in `index.md` whose globs match the
path(s) in scope and read that rule file, then grep this directory for relevant
keywords — a path match alone can miss cross-cutting rules.

> **About the examples:** Code samples in these rule files — and in the guidelines
> referenced below — use a neutral sample domain (`User`, `Team`, `UserStatus`) purely
> to illustrate the conventions. These are shapes any Laravel app ships with, **not**
> this app's business scope or a domain spec: never assume models, columns, enum
> values, or factory states from these docs. Always derive the real domain shape from
> the actual code and the Wayfinder-generated types (`@wayfinder/*`).

Structural architecture conventions (service pattern, DTOs, events/listeners, enums,
policies, caching core, Form Requests, migration column types) are documented
separately in `.ai/guidelines/laravel-inertia-svelte/rules/` — these rule files
complement them with the project-specific conventions the guidelines do not cover.

For the application's business domain — entities, business rules, roadmap — start with
the root `README.md`.
