# Household Membership — Design

**Status:** Approved in brainstorming, pending implementation plan
**Date:** 2026-11-04

## Summary

A household groups 2–5 users who share their financial life. Accounts remain **owned by a user**; sharing stays an access attribute on the account (`access_type: shareable`), now scoped to *members of the owner's household* instead of every app user. Members see shared accounts and record transactions on them; account management stays owner-only. Joining is by invite link protected by an OTP.

This replaces the current placeholder where "shareable" means "visible to all users".

## Why user-owned shared accounts (not household-owned)

Considered and rejected: `households` owning accounts directly (`accounts.household_id`). Reasons the user-ownership model wins here:

1. **Lifecycle** — household-owned accounts need re-homing when the owner leaves or the household dissolves. User-owned accounts never move: sharing simply stops, the account stays with its person. Dissolution has zero custody logic.
2. **Migration** — no account rows change. Only the visibility rule tightens. Household-ownership would have to answer "which household do existing shareable accounts convert to?" before any household exists.
3. **Domain accuracy** — Indonesian bank/e-wallet accounts are single-owner in practice. A "household account" is a social fiction: one person holds the rekening, the partner sees it. The schema should model what exists on the ground.
4. **Evolvability** — keeping ownership anchored on `owner_id` leaves the door open for a future per-account sharing pivot (see [Future evolution](#future-evolution)). Household-ownership would contradict per-member granularity.

Accepted trade-off: the account follows the person. If the owner leaves a household, members lose visibility of all history they recorded on that account — the pot's ledger walks away with the owner. Mitigation if ever needed: an explicit account-ownership-transfer action (out of scope for now).

## Domain model

### Concepts

- **Household** — a named group ("Keluarga Budi") with one owner and up to a handful of members. Each user belongs to at most one household.
- **Owner** — the creator (or successor). Manages members and invites; only the owner can rename, transfer ownership, remove members, or dissolve.
- **Member** — sees the owner's and fellow members' shared accounts, records transactions on them.
- **Shared account** — an account with `access_type = shareable` whose owner is in a household. Sharing means visibility into the household, not transfer of ownership.

### Schema (3 new tables, 0 changed columns)

```
households
    id, name, owner_id (FK users), timestamps
    -- no soft deletes: dissolution is a hard delete of the grouping;
    -- accounts and history are unaffected, so there is nothing to restore

household_members
    id, household_id (FK, cascade), user_id (FK, cascade), joined_at
    UNIQUE(user_id)              -- one household per user, enforced at DB level
    -- no role column: the owner is households.owner_id, the single source of
    -- truth. Member role is derived (user_id === household.owner_id).
    -- Transfer ownership = update households.owner_id atomically.

household_invites
    id, household_id (FK, cascade), token (ULID, unique), otp_code (6 digits, plaintext —
    the owner's invite card redisplays it, so hashing is off the table; the throttle +
    failed-attempt lockout are the brute-force defense),
    failed_attempts (tinyint, default 0), expires_at, revoked_at (nullable), timestamps
```

No FK from accounts, no pivot between accounts and users. `accounts.access_type` and `accounts.owner_id` are untouched.

### Policy changes

The shareable placeholder currently lives as a single boolean in three policies. Each changes only its visibility condition:

| Policy | Today | After |
| --- | --- | --- |
| `AccountPolicy::view` | owner OR `shareable` (everyone) | owner OR (`shareable` AND owner is in my household) |
| `TransactionPolicy::view` | creator OR account `shareable` (everyone) | creator OR (account `shareable` AND account owner in my household) |
| `TransferPolicy::view` | creator only | same rule as `TransactionPolicy::view`, applied to both legs: creator OR (either leg's account `shareable` AND that account's owner in my household) |

- A `shareable` account whose owner is in **no** household is visible to the owner only — this is the placeholder dying.
- The Account `shareable()` query scope becomes: my accounts ∪ shareable accounts owned by my household-mates.
- All write paths unchanged: account update/delete/archive and transaction/transfer writes stay owner-only / creator-only respectively (transfer cascade safety preserved).
- Transfer `view` mirrors the transaction rule so a member who can see a transfer's member rows can also see the aggregate unit — otherwise shared-account history would be inconsistent (rows without their unit). Writes stay creator-only, keeping the cascade creator-initiated. Known nuance: viewing a unit through a shared leg also reveals the other leg's account, e.g. a personal source feeding the pot; acceptable for a household trust context, noted here deliberately.

## Invite flow (link + OTP)

The link is a bearer credential; the OTP upgrades it to possession + knowledge. The link travels in the family group chat, the OTP travels privately (owner DMs it).

1. Owner opens **Settings → Household** and generates an invite. The UI shows the link and the 6-digit OTP side by side, with copy nudges ("send the code separately").
2. Invitee opens `/household/join/{token}`. If unauthenticated → redirected to login with intended URL, then back.
3. Join page asks for the OTP. Verification checks: token exists, not expired (7 days), not revoked, `failed_attempts < 5`. Each wrong OTP increments `failed_attempts`; reaching 5 sets `revoked_at` (auto-lockout).
4. Success requires the user to be in no household (the DB unique constraint backs the check). Membership row created with `joined_at`.
5. Invites are **reusable** until expired/revoked — one link can onboard the whole family. Regenerating rotates token + OTP together and resets attempts. The owner can revoke at any time.

The join/verify endpoints are rate-limited (throttle middleware) on top of the attempt counter. `otp_code` is stored as plaintext — the owner's invite card redisplays it, so hashing is off the table; throttling and lockout are the brute-force defense.

## Lifecycle flows

- **Create** — any user without a household creates one (name required); creator becomes owner-member.
- **Join** — via invite flow above.
- **Member leaves** — membership row deleted. Transactions they created on shared accounts remain (`created_by` preserved — the pot's history is fact, not membership); they lose visibility.
- **Owner removes a member** — same effect as the member leaving. Owner-only, with `ConfirmationModal`.
- **Owner leaves** — not directly possible. The owner must either **transfer ownership** to a member (updates `households.owner_id`; the ex-owner becomes a plain member who can then leave) or **dissolve**. No silent auto-transfer.
- **Dissolve** — owner-only, `ConfirmationModal`. In one `DB::transaction`: delete invites, delete memberships, delete household. Accounts are untouched — every member's shareable accounts become private again because nobody has a household.
- **Rename** — owner-only.

All lifecycle logic lives in an `app/Services/HouseholdService.php` per the service pattern; controllers stay thin dispatchers.

## UI surface

- **Settings → Household** — three states. No household: create form plus a hint that joining happens via an invite link. Member: members list with role badges and joined date, invite card (link + OTP display, copy buttons, expiry countdown, revoke/regenerate), leave button. Owner: adds rename, remove member, transfer ownership, dissolve.
- **Account form** — `access_type` picker copy becomes "Personal / Shared with household". The Shared option is disabled with a hint when the user has no household.
- **Account list** — shared accounts you don't own get an owner chip ("Budi's BCA") so the pot doesn't read as yours.
- **Transactions/templates/reports** — unchanged; they already flow through visibility and `created_by`.

Run `php artisan wayfinder:generate` after backend work; frontend types follow.

## Deliberately unchanged

Money engine (denormalized balances, observers, transfer aggregate), global categories, user-private templates, tags, all report SQL and caching (contribution split already scopes by visibility + `created_by`). No seeders. No new Spatie roles — authorization is model-based through policies.

## Future evolution

A per-account sharing pivot (`account_user`) remains reachable as an **exception layer**: `access_type = shareable` keeps meaning "shared into my household sphere"; pivot rows would store deviations (exclude one member from a specific account, or grant extra visibility). The policy becomes: owner OR (shareable AND same-household AND not excluded) OR pivot-grant. Membership changes never touch the pivot because default visibility still derives from the household. Known seam: exclusion-aware passes would be needed in contribution split and UI chips.

Out of scope now: per-account pivot, account-ownership transfer, templates shared with household, email-based invites, multiple households per user, budgeting.

## Testing strategy (Pest feature tests)

- **Policy matrix** — owner / household member / stranger × personal / shared / shared-owner-has-no-household, across account view, transaction view, transfer view; account writes remain owner-only; transaction/transfer writes remain creator-only.
- **Invite lifecycle** — happy join, expired, revoked, 5-attempt lockout auto-revokes, wrong-then-right OTP, reuse of a valid invite by a second member, join attempt while already in a household (unique constraint surfaces as validation error).
- **Leave / remove** — membership gone, created transactions preserved with `created_by`, visibility lost for the departed member, retained for remaining members.
- **Transfer ownership** — roles swap; ex-owner can leave afterwards.
- **Dissolve** — household/members/invites gone, shareable accounts become private (regression for the placeholder removal), money engine untouched.
- Balance/integrity behavior is covered by existing suites — this feature adds no money-engine paths.
