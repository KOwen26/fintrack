# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Two confirmed primary audiences (owner-confirmed 2026-09-24):

- **Personal trackers** — one person recording and managing their own money.
- **Household (couple) trackers/managers** — two people sharing joint accounts and reviewing shared finances together.

Primary job in both cases: record day-to-day money movement quickly, then understand it through reports.

## Product Purpose

Fintrack is a personal/household finance tracker. Users model the real-world accounts they hold (bank, digital bank, e-wallet, cash wallet, credit card, investment — optionally tied to a provider), record transactions against them, and analyze spending through per-account reports.

Success means: capturing a transaction takes seconds, and the reports users see are trustworthy derivatives of what was actually recorded.

## Positioning

Owner-confirmed priority ladder — when features conflict, the higher rung wins:

1. **Fast, effortless transaction capture** — the product's first job and core differentiator.
2. **Report engine** — trend, category leak, contribution split, fixed-vs-variable insight.
3. **Bookkeeping** — accurate account/provider/category structure underneath.
4. **Integration** — future ambition; scope undecided (open decision).

## Operating Context

- UI language: English (owner-confirmed).
- Currency: Indonesian Rupiah; amounts use `id-ID` formatting (owner-confirmed + codebase).
- Single Laravel application serves both backend and the Inertia/Svelte UI.
 Reports are per-account, read-only, and cache-aware (past months cached permanently, current month ~5 minutes).
- Auth includes Fortify features (2FA, passkeys) — account security matters to both solo and household users.
- Joint accounts are implemented as an access type with shared visibility (no member roster table); contribution split attributes income by the user who recorded each transaction (`transactions.created_by`).

## Capabilities and Constraints

Implemented (verified in code 2026-09-24):

- **Accounts**: debit, credit card, cash wallet, e-wallet, investment; personal or joint access; optional provider; initial/current balance; decorations; archive/restore; soft deletes.
- **Providers**: bank, digital bank, e-wallet, credit/loan, investment with active/inactive status.
- **Transactions**: income, expense, transfer out, transfer in, fee; categorized (input/output categories); per-account.
- **Reports** (per account, GET-only): income-vs-expense trend, category leak, joint contribution split, fixed-vs-variable.
- **Auth**: Fortify login/registration/2FA/passkeys; per-user light/dark theme preference; spatie/laravel-permission scaffolding.

Owner/README-stated roadmap emphasis: MVP focus is Account, Transaction, and a simple spending report; account UI finalization and transaction-form fixes are active work.

Explicitly open (must not be treated as existing features): budgeting, transaction presets, recurring presets, and integrations — referenced in README TODOs and repo conventions but absent from routes/models/migrations. No household member-management UI beyond joint access visibility exists yet.

## Brand Commitments

- Product name: **Fintrack** (inferred from repository name and owner usage; `APP_NAME` env is still the Laravel default — exact brand string is an open decision).
- No other voice, assets, or identity commitments confirmed yet.

## Evidence on Hand

- `docs/mockups/*.html` — visual explorations (account cards/forms/lists, transaction form/list/detail, design palette).
- `docs/plans/2026-07-29-account-detail-direction-1.md` — one prior design-direction plan.
- No customers, testimonials, pricing, or press exist; future work must not fabricate any.

## Product Principles

1. **Capture speed wins.** Any feature that slows transaction entry must justify itself against the priority ladder.
2. **Insight follows recording.** Reports derive from real recorded data; never estimate or fabricate numbers.
3. **Households are first-class.** Couple/manager workflows share equal standing with solo tracking, not a bolted-on mode.
4. **Model real money.** Accounts mirror the providers, account types, and access types users actually hold.
5. **English words, Rupiah numbers.** English UI copy with `id-ID` currency formatting is the durable default.
