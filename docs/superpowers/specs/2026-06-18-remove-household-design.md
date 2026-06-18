# Remove Household System

**Date:** 2026-06-18
**Status:** Approved

## Context

Fintrack originally grouped users into households so that joint accounts could be shared across members. This design is being removed. All users will instead see all accounts and transactions globally, while account ownership (`owner_id`) is retained for edit/delete authorization. Each transaction already tracks its creator via `created_by`.

## Decisions

| Decision | Choice | Reason |
|---|---|---|
| Migration strategy | Rewrite (clean slate) | Dev-only — no production data; clean history is better than create-then-drop |
| `access_type` column on accounts | Keep as label | May be used later; no access control effect going forward |
| Budgets & categories | No change | Already globally scoped — no household dependency |
| Account visibility | All users see all accounts | Transactions are accessed through accounts so this is a prerequisite |
| Edit/delete authorization | Owner only (`owner_id === user->id`) | Accounts still have a clear owner even without households |

## Section 1: Database / Migrations

**Delete:**
- `database/migrations/2026_06_15_142537_create_households_table.php`
- `database/migrations/2026_06_15_142538_create_household_invitations_table.php`
- `database/migrations/2026_06_15_142538_create_household_members_table.php`

**Rewrite** `database/migrations/2026_06_15_142539_create_accounts_table.php`:
- Remove the `household_id` column and its index
- Keep `access_type` column (personal / joint) as a descriptive string with no FK dependency

**After all changes:** run `php artisan migrate:fresh` to reset to the clean schema.

## Section 2: Backend Code

### Delete

| Type | Files |
|---|---|
| Models | `Household.php`, `HouseholdMember.php`, `HouseholdInvitation.php` |
| Factories | `HouseholdFactory.php`, `HouseholdMemberFactory.php`, `HouseholdInvitationFactory.php` |
| Enum | `HouseholdMemberRole.php` |
| DTOs | `HouseholdData.php`, `HouseholdMemberData.php` |
| Service | `HouseholdService.php` |
| Policy | `HouseholdPolicy.php` |
| Controllers | `HouseholdsController.php`, `HouseholdInvitationsController.php` |

### Update

| File | Change |
|---|---|
| `app/Policies/AccountPolicy.php` | `view()` returns `true`; remove `canAccess()` private helper |
| `app/Services/AccountService.php` | `getVisibleAccounts` and `getTransferEligibleAccounts` — remove `visibleTo($user)` scope; query all non-archived accounts directly |
| `app/Models/Account.php` | Remove `household()` relationship and `visibleTo` scope |
| `app/Models/User.php` | Remove `householdMemberships()` relationship |
| `app/Http/Controllers/AccountsController.php` | Remove `HouseholdService` constructor dependency; remove `household_id` from `create()` response props |
| `app/Http/Requests/StoreAccountRequest.php` | Remove `household_id` validation rule |
| `routes/web.php` | Remove all 5 household + invitation routes and their controller imports |
| `database/factories/AccountFactory.php` | Remove `household_id` from `definition()`; remove `joint()` state method |

## Section 3: Frontend

### Delete

- `resources/js/pages/household/settings.svelte`
- `resources/js/pages/household/invitation.svelte`
- `resources/js/pages/household/` directory

### Update

| File | Change |
|---|---|
| `resources/js/components/navigation/bottom-nav.svelte` | Remove Household nav item and `HouseholdsController` import |
| `resources/js/components/module/account/account-form.svelte` | Remove `household_id` from props interface, `$props()` destructure, and `formSchema` data |
| `resources/js/pages/accounts/create.svelte` | Remove `household_id` from props interface and from `<AccountForm>` |
| `resources/js/schema/account.schema.ts` | No change — `access_type` field stays (still shown in create form as a label-only field) |

### Wayfinder regeneration

After removing controllers and enum, run:

```bash
php artisan wayfinder:generate
```

This removes `HouseholdsController`, `HouseholdInvitationsController`, and `HouseholdMemberRole` from `resources/js/wayfinder/`.

## Section 4: Tests

### Delete

- `tests/Feature/HouseholdTest.php`
- `tests/Feature/HouseholdInvitationTest.php`

### Rewrite `tests/Feature/AccountTest.php`

| Old | New |
|---|---|
| `createUserWithHousehold()` helper | Remove; use plain `User::factory()->create()` |
| `'lists only visible accounts'` | Assert index returns all accounts (not just owner's) |
| `'stores a new personal account'` | Remove `household_id` from POST payload |
| `'prevents viewing another user personal account'` | Flip assertion to `assertOk()` — all accounts now visible to all users |
| `'allows household member to view joint account'` | Delete — household concept is gone |
| `'archives an account'`, `'soft-deletes an account'` | Remove household setup; create account directly with `owner_id` |

## Out of Scope

- `AccountAccessType` enum (`personal` / `joint`) — kept as-is, no logic change
- `Budget`, `Category` models — no household dependency, no changes
- `TransactionPolicy` — delegates to `AccountPolicy` which is already updated
- Auth, settings, reports — no household references
