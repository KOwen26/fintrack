# Spec: Foundation

**Date:** 2026-06-14
**Status:** Approved
**Scope:** Providers, Households, Accounts, Categories — the prerequisite layer that must exist before any transaction can be recorded.

---

## 1. Overview

Foundation establishes every shared reference entity in FinTrack. It covers:

- **Providers** — seeded reference data for account institutions (banks, e-wallets, etc.)
- **Households** — the unit of sharing between a user and their partner
- **Accounts** — cash containers (debit, credit, e-wallet, cash, investment)
- **Categories** — user-owned tags for classifying transactions

All other specs (Ledger, Automation, Insights) depend on Foundation being in place.

---

## 2. Entities

### 2.1 `providers`

Seeded reference data. No user-facing CRUD in MVP. Future: import integrations.

| Column       | Type            | Notes                                                                     |
| ------------ | --------------- | ------------------------------------------------------------------------- |
| `id`         | bigint PK       |                                                                           |
| `name`       | string          | e.g. "BCA", "GoPay"                                                       |
| `slug`       | string unique   | e.g. "bca", "gopay"                                                       |
| `logo_url`   | string nullable |                                                                           |
| `type`       | string          | PHP enum: `bank`, `digital_bank`, `e_wallet`, `credit_loan`, `investment` |
| `status`     | string          | PHP enum: `active`, `inactive`                                            |
| `created_at` | timestamp       |                                                                           |
| `updated_at` | timestamp       |                                                                           |

### 2.2 `households`

One household per couple or family group. Created on first registration or explicitly by the user.

| Column       | Type              | Notes                  |
| ------------ | ----------------- | ---------------------- |
| `id`         | bigint PK         |                        |
| `name`       | string            | e.g. "Kevin & Partner" |
| `created_by` | bigint FK → users |                        |
| `created_at` | timestamp         |                        |
| `updated_at` | timestamp         |                        |

### 2.3 `household_members`

Who belongs to the household. Roles are kept simple for MVP (owner / member).

| Column         | Type                   | Notes                          |
| -------------- | ---------------------- | ------------------------------ |
| `id`           | bigint PK              |                                |
| `household_id` | bigint FK → households |                                |
| `user_id`      | bigint FK → users      |                                |
| `role`         | string                 | PHP enum: `owner`, `member`    |
| `joined_at`    | timestamp nullable     | null until invitation accepted |
| `created_at`   | timestamp              |                                |

### 2.4 `household_invitations`

Pending invitations with signed token and 48-hour expiry.

| Column         | Type                   | Notes               |
| -------------- | ---------------------- | ------------------- |
| `id`           | bigint PK              |                     |
| `household_id` | bigint FK → households |                     |
| `invited_by`   | bigint FK → users      |                     |
| `email`        | string                 | invitee email       |
| `token`        | string unique          | signed random token |
| `accepted_at`  | timestamp nullable     |                     |
| `expires_at`   | timestamp              | 48h from creation   |
| `created_at`   | timestamp              |                     |

### 2.5 `accounts`

Core cash containers. Visibility controlled by `access_type` and household membership.

| Column              | Type                           | Notes                                                                             |
| ------------------- | ------------------------------ | --------------------------------------------------------------------------------- |
| `id`                | bigint PK                      |                                                                                   |
| `household_id`      | bigint FK → households         |                                                                                   |
| `owner_id`          | bigint FK → users              |                                                                                   |
| `name`              | string                         |                                                                                   |
| `type`              | string                         | PHP enum: `debit_account`, `credit_card`, `cash_wallet`, `e_wallet`, `investment` |
| `access_type`       | string                         | PHP enum: `personal`, `joint`                                                     |
| `provider_id`       | bigint FK → providers nullable |                                                                                   |
| `initial_balance`   | decimal(15,2) default 0        | starting balance before any transactions                                          |
| `credit_card_limit` | decimal(15,2) nullable         | credit cards only                                                                 |
| `currency`          | char(3)                        | ISO 4217 e.g. "IDR"                                                               |
| `archived_at`       | timestamp nullable             | soft-archive; archived accounts are hidden from active views                      |
| `deleted_at`        | timestamp nullable             | soft delete                                                                       |
| `created_at`        | timestamp                      |                                                                                   |
| `updated_at`        | timestamp                      |                                                                                   |

### 2.6 `categories`

User-owned classification tags. `parent_id` is schema-ready for hierarchy but the hierarchy UI is post-MVP.

| Column          | Type                            | Notes                                                       |
| --------------- | ------------------------------- | ----------------------------------------------------------- |
| `id`            | bigint PK                       |                                                             |
| `user_id`       | bigint FK → users               |                                                             |
| `name`          | string                          |                                                             |
| `icon`          | string                          | icon identifier                                             |
| `color`         | string                          | hex color code                                              |
| `is_fixed_cost` | boolean default false           | fixed vs variable for reports                               |
| `parent_id`     | bigint FK → categories nullable | null = top-level group; set = sub-category under that group |
| `deleted_at`    | timestamp nullable              |                                                             |
| `created_at`    | timestamp                       |                                                             |
| `updated_at`    | timestamp                       |                                                             |

---

## 3. Access Rules

- **personal** accounts: visible and writable only to `owner_id`
- **joint** accounts: visible and writable to all active `household_members` of the same household
- A user with no household sees only their own personal accounts
- Household `owner` can remove members and manage invitations; `member` cannot

---

## 4. Architecture Patterns

- **Service pattern**: all business logic in services; controllers are thin dispatchers
- **Event-listener**: side-effects (e.g. welcome setup after household creation) are handled via events, not inline in service methods
- **SQL aggregates only**: no PHP iteration over collections for computed values

### Services

| Service            | Responsibilities                                                     |
| ------------------ | -------------------------------------------------------------------- |
| `HouseholdService` | `create()`, `invite()`, `acceptInvitation()`, `removeMember()`       |
| `AccountService`   | `create()`, `update()`, `archive()`, `restore()`, `softDelete()`     |
| `CategoryService`  | `create()`, `update()`, `softDelete()`                               |
| `UserThemeService` | `update(User, string $theme)` — persists to `users.theme_preference` |

---

## 5. Routes & Controllers

```
GET    /accounts                     AccountsController@index
GET    /accounts/create              AccountsController@create
POST   /accounts                     AccountsController@store
GET    /accounts/{account}/edit      AccountsController@edit
PUT    /accounts/{account}           AccountsController@update
DELETE /accounts/{account}           AccountsController@destroy
POST   /accounts/{account}/archive   AccountsController@archive
POST   /accounts/{account}/restore   AccountsController@restore

GET    /categories                   CategoriesController@index
POST   /categories                   CategoriesController@store
PUT    /categories/{category}        CategoriesController@update
DELETE /categories/{category}        CategoriesController@destroy

GET    /household/settings           HouseholdsController@show
POST   /household                    HouseholdsController@store
POST   /household/invite             HouseholdsController@invite
DELETE /household/members/{member}   HouseholdsController@removeMember

GET    /household/invitations/{token}  HouseholdInvitationsController@show
POST   /household/invitations/{token}/accept   HouseholdInvitationsController@accept
POST   /household/invitations/{token}/decline  HouseholdInvitationsController@decline

PUT    /settings/theme                 UserThemeController@update
```

---

## 6. Inertia Pages

All files under `resources/js/pages/`:

| File                        | Purpose                                                       |
| --------------------------- | ------------------------------------------------------------- |
| `accounts/index.svelte`     | Account list with balance summary cards                       |
| `accounts/create.svelte`    | Create account form                                           |
| `accounts/edit.svelte`      | Edit account details                                          |
| `accounts/show.svelte`      | Account dashboard (balance, recent transactions, budget bars) |
| `categories/index.svelte`   | Manage categories, assign to accounts                         |
| `household/settings.svelte` | Household info, member list, invite partner                   |
| `settings/theme.svelte`     | Theme picker — persists selection to `users.theme_preference` |

---

## 7. Data Objects (Spatie Laravel Data)

DTOs are only created for **complex or combined shapes**. Simple model data is passed directly to `Inertia::render()` as an Eloquent model or collection — Wayfinder's `App.Models.*` types cover the TypeScript side.

**Foundation DTOs (combined shapes only):**

- `HouseholdData` — combines `households` + `household_members` + joined `users.name`; not a direct model shape
- `HouseholdMemberData` — member row + resolved user name from the `users` table

**Not DTOs (passed as models directly):**

- Accounts, categories, providers — pass `$account->load('provider')`, `$accounts`, `$providers` directly

Run `composer generate:ts` after any Data class change to sync TypeScript types.

---

## 8. Indexes

```sql
accounts:             (household_id), (owner_id), (archived_at), (deleted_at)
household_members:    UNIQUE (household_id, user_id), (user_id)
household_invitations: UNIQUE (token), (email, household_id)
categories:           (user_id), (parent_id), (deleted_at)
```

---

## 9. Migrations & Seeding

Add `theme_preference string nullable` to the `users` table via a dedicated migration. This column stores the user's chosen DaisyUI theme name. `null` means the app default theme is used. No PHP enum constraint — available themes are defined by the DaisyUI config, not the database.

The theme is applied on every page load by reading `auth()->user()->theme_preference` and setting it as the `data-theme` attribute on `<html>` via the root Svelte layout.

- `ProviderSeeder` — seeds common Indonesian banks and e-wallets (BCA, Mandiri, GoPay, OVO, Dana, etc.)
- `CategorySeeder` — seeds a 2-level default category hierarchy owned by the system user (or applied per user on first login). Structure:

    **Income** (top-level, `parent_id = null`)
    - Salary
    - Freelance / Side Income
    - Business Revenue
    - Investment Returns
    - Other Income

    **Expense** (top-level, `parent_id = null`)
    - Food & Drinks → Groceries, Dining Out, Coffee & Snacks
    - Transport → Fuel, Ride-hailing, Parking
    - Utilities → Electricity, Internet, Water, Phone
    - Housing → Rent, Home Maintenance
    - Health → Doctor, Medicine, Gym
    - Entertainment → Streaming, Games, Hobbies
    - Shopping → Clothing, Electronics, Household Items
    - Education → Courses, Books, School Fees
    - Other Expense

---

## 10. Out of Scope (MVP)

- Provider CRUD (admin only, seeded)
- Category hierarchy deeper than 2 levels
- Household with more than 2 members (schema supports it; UI targets couple use case)
- Per-account role granularity beyond personal/joint distinction
