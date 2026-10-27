# Transaction Templates — Design

Date: 2026-10-27
Status: Approved design, pending implementation plan

## Problem

Recurring transactions — a Netflix subscription, monthly salary, the usual warteg lunch, a monthly sweep into savings — are retyped field-by-field every time. The app needs reusable transaction templates that a user manages in Settings and applies with one tap to prefill the create form.

## Decision

A transaction template is a **user-private set of saved form defaults**: name, decoration (icon + color), transaction type (income / expense / transfer), category (non-transfers), required fixed amount, optional note, a single **optional** default source account, and for transfers a fixed destination account plus optional fee.

- Applying a template **prefills the create form** — it never saves directly. Every prefilled value stays editable; the account link is a soft default, not a restriction.
- Management lives in Settings: list (index), detail (show), and one shared form page for create/edit. Hard delete only — no soft deletes.
- Quick access: a `template-select` chip row on the transaction create form. Applying is **server-side** — the chip navigates to `?template=` and the form renders server-seeded defaults; there is no client-side apply logic.
- No pivot table — exactly one nullable `account_id` per template.

Naming note: "template" over "preset" deliberately — the entity is a pattern a new transaction is instantiated from (blanks allowed), not a complete configuration that is applied as-is.

## Template lifecycle

A template is reference data — it never touches balances, emits no events, and is never referenced by a transaction row.

```
Settings (manage)                        Quick access (use)
──────────────────────────────           ─────────────────────────────────────
form ──POST/PUT──> transaction_templates getQuickAccessTemplates ──> templates prop
detail ──DELETE──> hard delete                    │
                                                  ▼
                             template-select chip ──visit──> create?template=id (+account_id)
                                                                     │ server folds
                                                                     ▼
                                           TransactionFormData::fromTemplate — prefill only
                                                                     │ user edits, submits
                                                                     ▼
                                           POST /transactions — the existing store path
```

- **Create** — Settings → Transaction Templates → Add Template. Validated (type-conditional fields, owner-scoped accounts, required amount); stored as one row. No side effects.
- **Apply (read-only)** — the only way a template is used. The create form's `template-select` chips navigate to `?template=` (carrying the currently selected account as context), and the server folds the template into `TransactionFormData::fromTemplate` — one implementation, no client-side apply logic. The result is a *prefilled form* — nothing is saved until the user submits through the normal transaction store path, the template row is never mutated, and no usage is recorded.
- **Degrade** — when the default source or destination account becomes unselectable (archived, soft-deleted), the default is pruned from quick-access reads; a hard account delete empties the FK via `nullOnDelete`. The template keeps working as a partial prefill.
- **Delete** — hard delete from the detail page; nothing references a template, so there are no cascades or residue. Stale chips/links after deletion open a blank create form (the id is silently ignored).
- **Extend (future)** — scheduled transactions will add scheduling columns to this same row and instantiate it on a schedule; v1 ships none of that.

## Design

### 1. Schema

```php
Schema::create('transaction_templates', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
    $table->string('name');
    $table->string('type');                     // TransactionType cast
    $table->string('category_id')->nullable();  // Category cast; null for transfers
    $table->decimal('amount', 15, 2);
    $table->foreignId('destination_account_id')->nullable()->constrained('accounts')->nullOnDelete();
    $table->decimal('fee_amount', 15, 2)->nullable();
    $table->string('description')->nullable();
    $table->json('decorations')->nullable();
    $table->timestamps();

    $table->index('user_id');
});
```

- All reads are keyed by `user_id` (a user's own templates), so a single `user_id` index covers the access patterns; the secondary account FKs are never queried directly (same precedent as `accounts.provider_id`).
- Fresh table — no backfill needed.

### 2. Model

`App\Models\TransactionTemplate` — `$guarded = []`, `HasFactory`.

- Casts (`protected function casts(): array`): `type → TransactionType`, `category_id → Category`, `decorations → Decoration`, `amount` / `fee_amount` decimals.
- Relations: `user()`, `account()` (default source), `destinationAccount()`.
- `TransactionTemplateFactory` for tests.

### 3. Policy

`TransactionTemplatePolicy` — `viewAny`: true; `create`: true; `view` / `update` / `delete`: owner only (`$template->user_id === $user->id`). Templates are never exposed through shareable accounts — an account's shareable viewers never see the owner's templates.

### 4. Validation — `SaveTransactionTemplateRequest`

One request for store and update (the `SaveTransactionRequest` precedent).

| Field | Rules |
| --- | --- |
| `name` | `required`, `string`, `max:100` |
| `type` | `required`, `Rule::in` all three `TransactionType` values |
| `category_id` | `Rule::enum(Category)->except(InitialBalance)`; required for income/expense, `prohibited` for transfer |
| `amount` | `required`, `numeric`, `min:0.01` |
| `description` | `nullable`, `string`, `max:500` |
| `account_id` | `nullable`; `Rule::exists('accounts', 'id')->where('owner_id', $user->id)` |
| `destination_account_id` | required for transfer, `prohibited` otherwise; same exists-and-owned rule |
| `fee_amount` | `nullable`, `numeric`, `min:0.01`; `prohibited` unless transfer |
| `decorations` | mirror the existing account decorations validation rules |

`authorize()`: route-bound template → `can('update', $template)`; otherwise `can('create', TransactionTemplate::class)`.

### 5. Service — `TransactionTemplateService`

Follows the `AccountService` shape: **static read methods, instance write methods, `array $data` input** (no input DTO — same reasoning as §7). Plain Eloquent; no events, no caching (personal, small, low-frequency data).

Reads (static):

- `getTemplatesByUser(User): Collection` — management list, eager `account` + `destinationAccount`.
- `getTemplateById(User, int): ?TransactionTemplate` — owner-scoped lookup for form seeding; null for foreign or missing ids.
- `getQuickAccessTemplates(User): Collection` — the user's templates as in-memory read projections: `account_id` **nulled** on the instance when that account is no longer selectable (soft-deleted or archived), `destination_account_id` nulled likewise, `category_id` guarded via `Category::tryFrom`. Selectable = owned by the user, not archived, not soft-deleted. Projections mutate unsaved instances only; clients never re-implement validity rules.

Writes (instance):

- `create(User $creator, array $data): TransactionTemplate`
- `update(TransactionTemplate $template, array $data): TransactionTemplate`
- `delete(TransactionTemplate $template): void` — hard delete.

### 6. Controller & routes

`App\Http\Controllers\Settings\TransactionTemplateController` (Settings namespace, beside `ProfileController`), registered in `routes/settings.php` inside that file's existing `auth` group:

| Method | Path | Action → redirect |
| --- | --- | --- |
| GET | `settings/transaction-templates` | index |
| GET | `settings/transaction-templates/create` | create — renders the shared form page, empty |
| POST | `settings/transaction-templates` | store → `transaction-templates.index`, flash `Template created.` |
| GET | `settings/transaction-templates/{template}` | show |
| GET | `settings/transaction-templates/{template}/edit` | edit — renders the shared form page, seeded |
| PUT | `settings/transaction-templates/{template}` | update → `transaction-templates.show`, flash `Template updated.` |
| DELETE | `settings/transaction-templates/{template}` | destroy → `transaction-templates.index`, flash `Template deleted.` |

Route names `transaction-templates.*`. Thin dispatchers only — all logic in the service; `$this->authorize()` on every action.

Responses pass **raw Eloquent models** (eager `account` + `destinationAccount`) — no DTO wrapper; the same pattern as the `accounts` prop on the transaction pages.

### 7. Serialization & types

No DTO. `TransactionTemplate` is a 1:1 model shape, so it ships raw and Wayfinder derives `App.Models.TransactionTemplate` from the model — enum casts (`type`, `category_id`), the `Decoration` cast, and optional relation types (`account?`, `destinationAccount?`) all come generated. A DTO becomes warranted only if a read shape turns combined/computed later (e.g. scheduled-transaction projections).

### 8. Applying templates — create-form seeding

`GET /transactions/create?template={id}`:

- `TransactionController::create` resolves via `getTemplateById($this->user, $request->integer('template'))`; a null result is **silently ignored** — a stale id just opens a blank form through the existing `defaultFromRequest`.
- The `template-select` chips are the trigger: each chip visits this URL, passing the currently selected `account_id` as context when one is set.
- With a template, the controller builds the payload via a new static constructor **`TransactionFormData::fromTemplate(TransactionTemplate $template, ?int $contextAccountId): self`** — the same pattern as `fromTransaction` / `fromTransfer`. Folding rules:
  - `type`, `category_id`, `amount`, `description`, `fee_amount` copied as-is;
  - `account_id`: the context account (explicit `account_id` query param) if present, else the template's account if selectable, else null;
  - `destination_account_id`: the template's destination if selectable **and** different from the resolved source, else null;
  - `transaction_date` stays `now()`.
- The DTO stays service-free: the controller resolves, the DTO folds.

### 9. Frontend — Settings management

- `settingsMenu` (`resources/js/data/menu.ts`): new entry "Transaction Templates" (url via the generated named route; icon `solar--repeat-line-duotone`, final choice at implementation).
- Pages under `resources/js/pages/app/settings/transaction-templates/` — three components only:
  - **`index.svelte`** — list, newest first; rows show decoration badge, name, and a summary line (type · category or destination · amount); "Add Template" button → create. Rows render via `components/module/template/template-list.svelte`.
  - **`show.svelte`** — detail: `DataComposer` → `DataList` display of all fields (via `components/module/template/template-detail.svelte`), Edit button → edit page, Delete button + `ConfirmationModal` (error-colored confirm).
  - **`form.svelte`** — the create and edit controller actions both `Inertia::render('app/settings/transaction-templates/form', ...)`; the create action passes default values + `accounts`, the edit action the persisted template model + `accounts`. Exactly three page files exist — no separate `create.svelte` / `edit.svelte`.
- Shared form component `components/module/template/template-form.svelte`: name, decoration icon + color selectors (existing components), Income/Expense/Transfer type tabs switching conditional fields (category select vs destination + fee), required amount (`CurrencyInput`), note (`Textarea`), optional source account (`AccountSelect`, clearable to "no default account"), destination (`AccountSelect`) + fee for transfers. Submits via `useForm` to the controller URLs from Wayfinder.
- `resources/js/schema/template.schema.ts` — `DataSchema` powering the show page display.
- Layout: the existing `settings` prefix in `app.ts`'s layout switch already covers nested settings pages — verify, don't re-declare.

### 10. Frontend — Quick access

- **`template-select.svelte`** — `components/module/template/template-select.svelte`: a horizontal chip row (decoration icon + name, amount secondary) rendered on the create page only, directly under the type tabs. Chips are **pure navigation**: each visits `TransactionController.create.url({ query: { template: id } })`, appending the currently selected `account_id` as context when one is set — the server does the folding (§8), so the client implements no apply logic. Chip markup lives inline in the component. Ordering: templates whose `account_id` equals the selected account first, then the rest.
- `TransactionController::create` adds a `templates` prop (`getQuickAccessTemplates`); `TransactionForm` renders `TemplateSelect` under the type tabs. No dashboard surface — `DashboardController` is untouched.

### 11. Edge cases & rules

- Archived or soft-deleted default/destination account: `getQuickAccessTemplates` nulls it — the chip still applies, just without that default. Restoring the account revives it (the FK only empties on hard delete).
- Hard account delete: `nullOnDelete` empties `account_id` / `destination_account_id`; a transfer template degrades to a partial template.
- Resolved source equals destination → destination left empty for manual pick.
- Category enum drift (a stored value removed from the enum in future): `Category::tryFrom` guard in `getQuickAccessTemplates` → null; the template surfaces with an empty category and the form asks for one.
- No cap on template count; no usage counters, no manual ordering (YAGNI — revisit with the scheduled-transaction work).
- Amounts are whole-rupiah carried as `decimal(15,2)`, consistent with transactions.

### 12. Forward compatibility — scheduled transactions

The roadmap's "Scheduled Transaction" item (currently worded in the README as a preset improvement; reword to templates) extends this table with `frequency` / `next_run_at` / `requires_confirmation`-style columns on top of the same template row — the typed-columns model supports that without restructuring. v1 ships none of it.

### 13. Codegen & docs

- `php artisan wayfinder:generate` after backend changes; frontend imports from `@wayfinder` only — no hardcoded URLs. This is the only codegen step — no `composer generate:ts` since no DTO is added.
- README: reword the roadmap item to template vocabulary and move the feature from "Current focus" to the implemented narrative once shipped.

### 14. Tests (Pest)

- `TransactionTemplateService`: CRUD; `getQuickAccessTemplates` pruning matrix (archived source, archived destination, hard-deleted accounts).
- `TransactionFormData::fromTemplate`: folding rules (context account precedence, source == destination, unselectable account).
- Controller feature tests: full route matrix owner-only (another user's template → rejected); validation failures (prohibited-by-type fields, non-owned `account_id` / `destination_account_id`, missing category on income); redirect + flash targets.
- Create-form seeding: `?template=` prefills `TransactionFormData` (type, category, amount, account precedence over the template default); foreign template ids ignored.
- Props: `templates` present on the create response.
- Factories for models; follow the `testing-best-practices` skill.

## Out of scope

- One-tap instant save and scheduled execution (later roadmap).
- Template reordering UI, usage counts, smart sorting.
- Tags on templates (the tagging feature ships separately).
- Sharing templates with shareable-account viewers.
- Templates on the transaction edit form (create-only surface).
