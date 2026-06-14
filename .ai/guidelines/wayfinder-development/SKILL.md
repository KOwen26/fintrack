---
name: wayfinder-development
description: 'Use this skill for Laravel Wayfinder which auto-generates typed functions for Laravel controllers and routes. ALWAYS use this skill when frontend code needs to call backend routes or controller actions. Trigger when: connecting any Svelte/Inertia frontend to Laravel controllers, routes, building end-to-end features with both frontend and backend, wiring up forms or links to backend endpoints, fixing route-related TypeScript errors, importing from @wayfinder, or running wayfinder:generate. Use Wayfinder route functions instead of hardcoded URLs. Covers: controller action imports, named route imports, enum constants, model types, form request types, .url()/.form()/.get()/.post(), query params, route model binding. Do not use for backend-only tasks.'
license: MIT
metadata:
    author: laravel
---

# Wayfinder Development

> This project uses Wayfinder **next** branch. All generated files live under `resources/js/wayfinder/` (alias `@wayfinder`). The old `@/actions/` and `@/routes/` paths no longer apply.

## Documentation

Use `search-docs` for detailed Wayfinder patterns and documentation.

## Generate

Run after any backend change that affects routes, enums, models, or form requests:

```bash
php artisan wayfinder:generate --no-interaction
```

The `.form()` variant is always generated — no flags needed.

## Import Patterns

```typescript
// Controller actions — follow the PHP namespace
import { AccountsController } from '@wayfinder/App/Http/Controllers/AccountsController';

// Named routes
import accounts from '@wayfinder/routes/accounts';

// Enum constants (runtime use)
import AccountType from '@wayfinder/App/Enums/AccountType';
import AccountAccessType from '@wayfinder/App/Enums/AccountAccessType';

// All types (models, enums, shared data, page props, form requests)
import type { App } from '@wayfinder/types';
```

## Common Methods

```typescript
// Full route object { url, method }
AccountsController.index()
AccountsController.show({ account: 1 })  // { url: '/accounts/1', method: 'get' }

// URL string only — use this with Inertia's useForm / router
AccountsController.index.url()
AccountsController.show.url({ account: 1 })  // '/accounts/1'

// Explicit HTTP method variants
AccountsController.index.get()
AccountsController.store.post()
AccountsController.update.put({ account: 1 })
AccountsController.update.patch({ account: 1 })
AccountsController.destroy.delete({ account: 1 })

// Form spread — native HTML forms only (NOT Inertia useForm)
// Produces { action: '/accounts/1?_method=PUT', method: 'post' }
AccountsController.update.form({ account: 1 })

// Query parameters
AccountsController.index.url({ query: { page: 2, sort: 'created_at' } })
AccountsController.index.url({ mergeQuery: { page: 3 } })
```

## Wayfinder + Inertia

Use `.url()` with Inertia's `useForm` and `router`. Never use `.form()` with Inertia — `.form()` is only for native HTML forms.

```typescript
// useForm typed with the generated Form Request type
const form = useForm<App.Http.Controllers.AccountsController.Store.Request>({
    name: '',
    type: AccountType.DebitAccount,
});

// Submit via useForm
form.post(AccountsController.store.url());
form.put(AccountsController.update.url({ account: id }));
form.delete(AccountsController.destroy.url({ account: id }));

// router for non-form actions (archive, toggle, etc.)
router.post(AccountsController.archive.url({ account: id }));
```

```svelte
<form onsubmit={(e) => { e.preventDefault(); form.post(AccountsController.store.url()); }}>
    <!-- fields -->
</form>
```

## Enum Constants

Use Wayfinder-generated constants instead of magic strings. Constants live at `@wayfinder/App/Enums/EnumName`.

```typescript
import AccountType from '@wayfinder/App/Enums/AccountType';
import type { App } from '@wayfinder/types';

// ✅ correct — type-safe constant
if (account.type === AccountType.CreditCard) { ... }

// ❌ wrong — magic string
if (account.type === 'credit_card') { ... }

// Use as default form values
const form = useForm({ type: AccountType.DebitAccount });
```

Generated enum file shape:

```typescript
// @wayfinder/App/Enums/AccountType.ts
export const DebitAccount = 'debit_account';
export const CreditCard = 'credit_card';
export const CashWallet = 'cash_wallet';
export const EWallet = 'e_wallet';
export const Investment = 'investment';

export const AccountType = { DebitAccount, CreditCard, CashWallet, EWallet, Investment } as const;
export default AccountType;
```

## Enum Badge Components

Every PHP-backed enum must have a badge component in `resources/js/components/ui/badges/`. Badge config maps use Wayfinder constants as keys so any enum rename propagates automatically.

```svelte
<!-- account-type-badge.svelte -->
<script lang="ts">
    import AccountType from '@wayfinder/App/Enums/AccountType';
    import type { App } from '@wayfinder/types';

    let { type }: { type: App.Enums.AccountType } = $props();

    const config: Record<App.Enums.AccountType, { label: string; class: string }> = {
        [AccountType.DebitAccount]: { label: 'Debit',       class: 'badge-primary' },
        [AccountType.CreditCard]:   { label: 'Credit Card', class: 'badge-warning' },
        [AccountType.CashWallet]:   { label: 'Cash',        class: 'badge-success' },
        [AccountType.EWallet]:      { label: 'E-Wallet',    class: 'badge-info'    },
        [AccountType.Investment]:   { label: 'Investment',  class: 'badge-secondary' },
    };

    const badge = $derived(config[type]);
</script>

<span class="badge badge-sm {badge.class}">{badge.label}</span>
```

## Model and Shared Data Types

```typescript
import type { App } from '@wayfinder/types';

// Eloquent model types
let account: App.Models.Account;
let user: App.Models.User;

// Enum value types (for prop typing)
let type: App.Enums.AccountType;

// Inertia shared data is automatically typed via SharedData
// page.props.auth.user resolves to App.Models.User | null
```

## Verification

1. Run `php artisan wayfinder:generate` after any backend change
2. Confirm `resources/js/wayfinder/` has files for all new controllers and enums
3. Run `pnpm run sv:check` to confirm no type errors

## Common Pitfalls

- Importing from old `@/actions/` path — use `@wayfinder/App/Http/Controllers/` instead
- Using magic strings instead of Wayfinder enum constants
- Using `.form()` with Inertia — `.form()` is for native HTML forms only; use `.url()` with `useForm`/`router`
- Forgetting to regenerate after adding a controller, route, or enum
