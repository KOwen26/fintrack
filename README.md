# FinTrack

Personal finance tracker for the Indonesian market. Track balances across bank accounts, digital banks, e-wallets, cash wallets and investments; record income, expenses and transfers; organize spending in a two-level category tree; and review activity through monthly reports. Joint (shared) accounts are supported.

> **Domain source of truth:** this document is the conceptual narrative. Schema truth lives in `database/migrations/**`, domain vocabulary in `app/Enums/**`, business rules in `app/Services/**`, and generated frontend types in `resources/js/wayfinder/`. Setup & tooling live in [`STACK.md`](STACK.md).

## Domain Overview

```
User ──owns──> Account ──has──> Transaction ──> Category (2-level tree)
                │                  │
                └── Provider       └── Transfer (aggregate: source + destination + optional fee)
```

A user owns multiple financial accounts, each optionally linked to a provider (institution). Every money movement is a transaction on exactly one account. Transfers between two accounts are modeled as a first-class aggregate that owns its member rows. Spending is classified through a global two-level category tree. Reports aggregate transaction history per account — or across accounts.

## Core Concepts & Business Rules

### Account

- **Types** (`AccountType`): `debit_account`, `credit_card`, `cash_wallet`, `e_wallet`, `investment`.
- **Access** (`AccountAccessType`): `personal` or `joint`. Viewing is allowed for the owner or anyone when the account is joint; mutating (update, delete, restore, archive) is owner-only.
- **Lifecycle**: soft deletes plus a separate `archived_at` timestamp — archiving is user-facing and distinct from deletion; lists exclude archived accounts.
- **Balance is dual-tracked**: `current_balance` is denormalized and observer-maintained: account creation copies `initial_balance`; editing `initial_balance` applies the delta; every transaction create/update/delete/restore applies `±amount` by flow direction. On-demand truth is recomputed by `BalanceService` as `initial_balance + Σ inflows − Σ outflows` (SQL aggregate, cached per account — past data indefinitely, current month briefly).
- **Portfolio summary**: total balance; "available" balance counts only debit + cash + e-wallet accounts (credit cards excluded); investment balance; oldest-account age.

### Transaction

- Two independent axes: **type** (`income`, `expense`, `transfer`) and **flow** (`inflow`, `outflow`). A transfer books as *(transfer, outflow)* on the source account and *(transfer, inflow)* on the destination — never as dedicated in/out types.
- **Uncategorized transactions are not allowed.** `category_id` is nullable solely because of the transfer mechanism (fee rows may book uncategorized when the Admin Fees category does not resolve); direct categorization for transfers may be added later.
- Amounts are stored as decimals but carried as whole numbers (rupiah-scale).
- `transaction_date` is a **datetime**.
- `created_by` is always recorded — significant for joint accounts.

### Transfer (aggregate)

- A `transfers` row owns 2–3 member transactions: the source *(transfer, outflow)*, the destination *(transfer, inflow)*, and an optional fee.
- The fee books as an *(expense, outflow)* row on the **source** account, described "Transfer fee", under the **Admin Fees** child category when it exists.
- **Editing** updates the aggregate in place, soft-deletes all member rows (observers reverse their balance effects) and re-creates them from the updated payload — atomically in one transaction.
- **Deleting any member deletes the whole unit** — aggregate and every member row, including the fee. The pairing is app-enforced.

### Category

- Two-level tree (parent → children). Type: `input` (income) or `output` (spending). Manual sort order. `is_fixed_cost` marks fixed recurring costs.
- `is_fixed_cost` is currently only a **marker** consumed by the Fixed-vs-Variable report; broader semantics (budgeting) are future intent.
- Categories are **global by design** (no per-user scoping — per-user categorization is distant roadmap). Defaults are seeded: Income, Finance, Food & Drinks, Utilities, Service & Housing, and children such as Salary, Dining Out, Electricity — each with icon/color decorations.

### Provider

- Reference metadata for financial institutions — banks, digital banks, e-wallets, credit loans, investments — seeded with Indonesian institutions (BCA, Mandiri, Jenius, GoPay, OVO, Dana, ShopeePay, LinkAja, …).
- **Metadata-only today**: attaches to accounts for display. Deeper integration is future roadmap. Not soft-deletable; carries `active`/`inactive` status and a unique slug.

### Decorations

- Accounts, categories and providers carry a `decorations` JSON payload (icon + color) cast to `DecorationData`, powering colorful UI rendering.
- Palettes are sourced from `resources/js/data/decoration-icons.json` and `decoration-colors.json` via the Sushi-backed `DecorationIcon` / `DecorationColor` models — writes flow through the models so the JSON stays in sync.

### Reports

All reports are SQL-aggregated and cached per account (past months indefinitely, current month briefly):

- **Trend** — income vs expense per month, net, surplus rate.
- **Category Leak** — expenses ranked by category with share of period total.
- **Contribution Split** — income share per member; **joint accounts only** (personal accounts return an empty split, not an error).
- **Fixed vs Variable** — spending split by the fixed-cost marker; "safety margin" equals the variable share.
- **Global category spending** — aggregates across multiple accounts and groups child-category spend under parents (synthesizing a parent when only children have spend).

### Users & Access

- Fortify authentication: required email verification, two-factor auth, passkeys.
- Spatie roles/permissions are wired but no domain-specific roles are in use yet.
- Per-user `theme_preference` (light/dark).

## Non-Goals

- Multi-currency — single implied currency with whole-rupiah amounts.
- Credit-card limit / utilization tracking — descoped.
- Uncategorized transactions — other than the transfer-fee mechanism above.
- Budget enforcement — budgeting is roadmap, not implemented.

## Roadmap

### Current focus

1. Define MVP-ready scope (Account, Transaction, simple spending report)
2. Fix Transaction form & behavior
3. Implement / rework budgeting
4. Implement / rework transaction presets (including recurring execution)

### Later

- Household / shared-account membership — joint accounts are currently visible to all users as a placeholder until membership exists
- Deeper Provider integration (provider data is metadata-only today)

### Distant

- Per-user category scoping

## Tech Stack

| Layer      | Stack                                                                                     |
| ---------- | ----------------------------------------------------------------------------------------- |
| Backend    | Laravel (PHP 8.4), Fortify (2FA, passkeys), Spatie Laravel Data, Spatie Permission        |
| Frontend   | Svelte 5, Inertia v3, Tailwind CSS v4 (DaisyUI + shadcn-style atoms), TypeScript          |
| Data/Query | Wayfinder-generated types, TanStack Table, layerchart                                     |
| Quality    | Pest, Pint, Rector, ESLint, Prettier                                                      |
| Dev tools  | Telescope, Laravel Debugbar                                                               |

Full setup commands, install steps, project structure and aliases: [`STACK.md`](STACK.md). Engineering conventions: `.ai/guidelines/` (architecture patterns) and `.ai/rules/` (project-specific rules).
