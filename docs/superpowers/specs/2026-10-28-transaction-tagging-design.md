# Transaction Tagging — Design

Date: 2026-10-28
Status: Approved design · plan: [`docs/superpowers/plans/2026-10-28-transaction-tagging.md`](../plans/2026-10-28-transaction-tagging.md)

## Problem

Transactions need freeform, user-created labels beyond the global category tree — `reimburse_pending`, `lebaran_2027`, `shared_with_roommate`. Categories are global and two-level by design; tags are personal, unlimited in vocabulary, and created at entry time rather than managed in a CRUD screen first. v1 ships **input + storage + display**; the list filter is a separate follow-up spec (see Out of scope).

## Decision

- Tags are a **denormalized list on the transaction row**: `tags json nullable`, storing normalized snake_case slugs, `null` when empty. No tags table, no pivot — deliberate, since per-user tag management is distant roadmap; if it lands, the column migrates to a pivot by backfill.
- **All transaction types can be tagged, transfers included.** A transfer unit's tags live **only on the source (outflow) row** — the row the transaction list already shows as the unit representative. Destination and fee member rows stay `null`, so a future tag filter surfaces the unit once, through its representative row, and fee rows remain untagged rows.
- **Templates carry tags too.** `transaction_templates` gains the same `tags json nullable` column and `Tags` cast; applying a template prefills the create form's tags through `TransactionFormData::fromTemplate`, and the template management form gets the same `tags-input` field with suggestions.
- Tags are **created inline at input time**: a multi-select combobox (`tags-input`) where free text commits as a new chip ("create new value"). There is no tag management surface in v1.
- Every tag is **normalized to a snake_case slug** on both sides — the client normalizes for live preview, the server re-normalizes authoritatively on every write. The client is never trusted.
- Tags persist through a **value object, not a bare cast class**: `App\Objects\Tags` — immutable, `Castable`, the same pattern as `Decoration`. The model attribute is `?Tags`, and every write normalizes through `TagService` before hitting the column.
- Caps are **named constants**, one definition point per language: `App\Services\TagService::MAX_TAGS` / `MAX_TAG_LENGTH` / `MAX_RAW_LENGTH` (PHP, referenced by validation rules), mirrored by `resources/js/schema/tags.schema.ts` (TS, enforced by the input component).
- Suggestions come from **existing data**: the distinct stored tag sets for the user, flattened and sorted. No tag registry, no cache in v1.
- Storage is **designed for filtering** (follow-up spec): the `JSON_OVERLAPS(tags, ?)` predicate is verified working on this app's MySQL 8.4; that spec will cover `TransactionIndexRequest` / `TransactionListFilters` / list-engine plumbing and the filter UI.

## Tag lifecycle

```
tags-input (form)                                server
──────────────────────────────────               ─────────────────────────────────────────────
type "Bills & Rent!" ── normalize (TS) ──> chip "bills_rent"
suggestions ◄──── tags prop ◄──── TagService::getUniqueTags (distinct stored sets)
submit  tags: ["bills_rent"] ── POST/PUT ──> FormRequest  (count ≤ MAX_TAGS, raw ≤ MAX_RAW_LENGTH)
                                                    │ TransactionData / TransferData
                                                    ▼
                                        Tags object — re-normalizes authoritatively
                                                    ▼
                                        transactions.tags = ["bills_rent"]   (null when empty)
                                                    │
                                                    ▼
                                        list / detail / form DTOs expose tags: string[] | null
```

- **Create (plain)** — validated array in, cast-normalized on write, stored as JSON list; empty array or absent → `null`.
- **Create (transfer)** — `TransferData.tags` is written to the **source (outflow) member row only**; destination and fee rows get `null`.
- **Edit (plain)** — the tags array is **replaced wholesale** (no merge with prior tags).
- **Edit (transfer)** — the unit edit soft-deletes and recreates all member rows from the updated `TransferData`; the recreated source row carries the tags again.
- **Apply via template** — a template's tags prefill the create form through `TransactionFormData::fromTemplate`; they stay editable like every prefilled value.
- **Display** — global transaction list shows up to 2 chip slugs + `+n`; the transaction detail shows the full chip row; template detail shows the template's chips.
- **Degrade** — nothing to degrade: tags have no references, no decorations, no side effects. A stored slug can only become *unused*, never broken.
- **Extend (future)** — list filter (own spec), per-user tag management (rename/merge/delete, or pivot migration).

## Design

### 1. Normalization contract

Identical rules, implemented twice (PHP authoritative, TS for preview):

| Step | Rule |
| --- | --- |
| 1 | lowercase (`mb_strtolower` / `toLowerCase`) |
| 2 | any run of non letter/number characters (`[^\p{L}\p{N}]+` with `/u`) → a single `_` — spaces, hyphens and symbols collapse; unicode letters/digits survive |
| 3 | trim leading/trailing `_` |
| 4 | truncate each tag to `MAX_TAG_LENGTH` (32) characters |
| 5 | drop empties, dedupe, preserve first-seen order |
| 6 | cap the list at `MAX_TAGS` (10) |
| 7 | empty set → `null` |

Examples: `"Bills & Rent!"` → `bills_rent` · `"--FOOD--"` → `food` · `"!!!"` → dropped · `["Food", "food"]` → `["food"]`.

- **PHP** — `App\Services\TagService` is the normalization authority: `normalize(list<string> $raw): list<string>` and `normalizeToNullable(...): ?list<string>`, plus the constants `MAX_TAGS = 10`, `MAX_TAG_LENGTH = 32`, `MAX_RAW_LENGTH = 64` (the sanity ceiling for un-normalized client input). No separate normalizer class.
- **TS** — the generic string primitive joins the existing `StringHelper` (`resources/js/utilities/string-helper.ts`) as `toSnakeCase(value)` — lowercase, collapse non letter/number runs to a single `_`, trim. Tag-domain logic — the mirrored `MAX_TAGS` / `MAX_TAG_LENGTH` constants and the truncate/dedupe/cap wrappers `normalizeTag(raw)` / `normalizeTags(raw)` — lives in `resources/js/schema/tags.schema.ts`, built on that primitive. `tags-input` renders the normalized slug as the chip label, so what the user sees is exactly what will be stored and later filtered. This side is a **best-effort preview** — exactness is not guaranteed; the backend re-normalizes on every write regardless.

### 2. Schema

```php
// add_tags_to_transactions_table
Schema::table('transactions', function (Blueprint $table): void {
    $table->json('tags')->nullable()->after('description');
});
```

- Nullable JSON column, `null` when empty — matches the storage decision exactly.
- **No index**: JSON columns can't use one without generated columns, and every list read is already bounded by `created_by` + period. A tag filter (future spec) scans the same bounded window; acceptable at personal-finance scale.
- JSON column precedent: `decorations` on accounts, categories, providers and templates.

### 3. Model

`Transaction` gains one cast:

```php
protected function casts(): array
{
    return [
        // ...existing casts...
        'tags' => Tags::class,
    ];
}
```

`App\Objects\Tags` is an immutable value object implementing `Castable` — the same pattern as `Decoration` (inline anonymous cast in `castUsing()`, no separate cast class, no new directory):

- `fromArray(?array $raw): ?self` — runs `TagService::normalizeToNullable()`; an empty surviving set yields `null`
- `toArray(): list<string>` — the slug list (the wire/JSON form)
- cast `set()` accepts `null` | `array` | `Tags` and always re-normalizes — service writes, factories and tinker all normalize identically; cast `get()` returns `?Tags`

So `$transaction->tags` is a `?Tags` object and the cast stays the **single write choke point**. No query scope ships in this spec — the filter spec owns `JSON_OVERLAPS` querying.

### 4. Write path

**Validation** — `SaveTransactionRequest` and `SaveTransferRequest` share the same rules, referencing the constants:

```php
'tags'   => ['nullable', 'array', 'max:' . TagService::MAX_TAGS],
'tags.*' => ['string', 'max:' . TagService::MAX_RAW_LENGTH],
```

Count over the cap is a 422 (silent truncation there would be hostile); per-tag length is a raw sanity ceiling — normalization truncates to `MAX_TAG_LENGTH` and the combobox preview makes that visible before submit.

**DTOs** — `TransactionData` and `TransferData` each gain `public readonly ?array $tags = null` (`@param list<string>|null`), typed to `string[] | null` for the frontend by the TypeScript transformer.

**Services** — `TransactionService::create/update` pass `'tags' => $data->tags` through like any other column (the cast normalizes). `TransferService` writes `$data->tags` **to the source (outflow) member row only**; destination and fee rows are created with `tags: null`.

**Templates** — `SaveTransactionTemplateRequest` gains the same two rules; `TransactionTemplateService` spreads validated data unchanged (the model cast normalizes), and `transaction_templates` gains the column + cast per the Decision.

**Untouched rows** — initial-balance rows and any system-created rows simply keep `tags: null`; no backfill of existing data.

### 5. Read path

- `TransactionListData` — `public readonly ?array $tags` in the constructor and all three row mappers (`fromTransaction`, `collectFromTransactions` batches, `row()`); wire shape `tags: string[] | null`. Mappers read the object: `$transaction->tags?->toArray()`.
- `TransactionDetailData` — same field, folded the same way.
- `TransactionFormData` — `?array $tags` seeding the edit form: `fromTransaction` and `fromTransfer` read the **source row's** tags (a transfer edit is seeded from its source row, which is where tags live); `defaultFromRequest` produces `null`; `fromTemplate` seeds the **template's** tags as the create-form prefill.
- **List cache version bump** — `TransactionListService::cacheKey()` holds full-window rows; adding `tags` changes the row shape, so the key gains/bumps its shape-version segment (e.g. `transaction-list:v2:user:{id}`), letting pre-tags entries orphan and expire per the documented pattern.
- **No new events/listeners** — tag writes ride the existing `TransactionSaved` / `TransactionDeleted` flush path.

### 6. Tag suggestions & normalization service

`App\Services\TagService` — static-method style matching the other services. Owns the §1 normalization authority (`normalize` / `normalizeToNullable` + the cap constants) and the suggestion read:

```php
/** Distinct tags across the user's live transactions, sorted. @return list<string> */
public static function getUniqueTags(User $user): array
```

- Query: one raw SQL statement — `JSON_TABLE` explodes the stored arrays in the database, `DISTINCT` + `ORDER BY` return the sorted union directly (ownership and `deleted_at IS NULL` are explicit, because raw SQL bypasses Eloquent's `SoftDeletes` scope). No PHP-side flattening.
- No cache in v1 (cheap page-load query); noted as a future optimization if tag universes grow.
- Served as a `tags` prop by `TransactionController::create/edit` and `Settings\TransactionTemplateController::create/edit` (combobox suggestions).

### 7. `tags-input` component

`resources/js/components/ui/forms/tags-input.svelte` — a domain-agnostic form primitive (like `search-input`, `masked-input`), built on the existing `combobox.svelte` bits-ui wrapper. Imports `MAX_TAGS` / `normalizeTags` from `@schema/tags.schema` (which builds on `StringHelper.toSnakeCase`):

- `type="multiple"`; a custom trigger snippet (the `category-select` pattern) rendering selected tags as chips plus the text input.
- **Free-text commit** — Enter or comma commits the typed text as a new chip after normalization; chips display the normalized slug.
- Suggestions list from the `suggestions` prop (server-provided distinct tags), filtered by input; selecting toggles membership.
- Dedupes against already-selected chips; backspace removes the last chip; input disabled past `MAX_TAGS` chips.
- Optional props for placeholder and the bound `value: string[]`.

**Integration** — `transaction-form.svelte` places one `TagsInput` for **all types**, after the Category/Destination block and before Date & Time (optional field, label "Tags"). `buildInitialData()` seeds `tags: transaction.tags ?? []`; the field stays in the payload for every type (unlike `category_id`, `form.transform` does **not** strip it on the transfer branch). The **template management form** (`template-form.svelte`, Settings) gets the same field — same placement conventions, seeded from the template row on edit, with the `tags` suggestions prop.

### 8. Display

- `transaction-list-item.svelte` — soft mini-chips under/next to the description, **max 2 slugs + `+n`** overflow, muted styling for list density.
- Transaction detail (`transaction-detail.svelte`) — the full chip row from the detail DTO.
- Template detail (`template-detail.svelte`) — the template's chip row.
- Other transaction surfaces (account/category scoped lists) reuse the same chips opportunistically; not a scope requirement.

### 9. Testing (Pest)

- **Unit — `TagService` normalization**: the rule table end-to-end (casing, symbol runs, trim, truncate at 32, dedupe, order preservation, cap at 10, all-empty → null); `App\Objects\Tags` cast set/get round-trip — `null` passthrough, array and instance inputs both normalizing. (The TS mirror has no JS test runner in the stack — ESLint only — so the PHP authority carries the contract; the TS side is preview-only.)
- **Feature — writes**: store income/expense with raw tags → normalized slugs persisted; absent/empty → `null`; more than `MAX_TAGS` → 422; raw tag over `MAX_RAW_LENGTH` → 422; update replaces the array wholesale; transfer create puts tags on the source row only (destination + fee rows `null`); transfer edit recreates the unit and the source row keeps carrying tags; template store/update normalize through the same rules.
- **Feature — reads**: list rows and the detail DTO expose `tags`; the edit form (plain + transfer) is seeded from the source row's tags; `fromTemplate` prefills the template's tags (`TransactionTemplateSeedingTest`); `TagService::getUniqueTags` returns the distinct sorted union across plain and transfer-source rows; the list cache serves the new row shape after the version bump; template form pages carry the `tags` prop.

## Out of scope / future

- **List filter by tags** — separate spec, approved alongside this one: [`2026-10-28-transaction-tag-filter-design.md`](2026-10-28-transaction-tag-filter-design.md); implemented after this spec ships. Storage here is chosen and verified for it: `JSON_OVERLAPS(tags, ?)` OR-matching on MySQL 8.4, `TransactionIndexRequest` filter-id plumbing, table-brain `tags` column filter, filter chip + sheet UI, global search matching the tags column.
- **Per-user tag management** (rename / merge / delete, usage counts) — distant roadmap; a pivot migration by backfill is the escape hatch if it lands.
- **Per-tag decorations** (icon/color per tag) — not in v1.
