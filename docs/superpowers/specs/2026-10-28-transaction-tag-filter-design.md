# Transaction Tag Filter — Design

Date: 2026-10-28
Status: Approved design · plan: [`docs/superpowers/plans/2026-10-28-transaction-tag-filter.md`](../plans/2026-10-28-transaction-tag-filter.md) (ships after the tagging plan)
Depends on: [`2026-10-28-transaction-tagging-design.md`](2026-10-28-transaction-tagging-design.md) — storage, `App\Objects\Tags`, `TagService`

## Problem

Tags now ride on transactions (input, storage, display — tagging spec). The transaction list needs to filter by them: pick one or more tags, see the matching transactions. This was split from the tagging spec to keep its scope at input + storage + display.

## Decision

- **OR semantics** — a row matches when it carries *any* selected tag, consistent with how the account / category / type filters behave. No AND mode.
- **Server contract** — the TanStack-shaped filter payload gains filter id `tags` (`value: list<string>` of normalized slugs), validated and translated like the existing ids; malformed values fail loudly with 422, never silently ignored.
- **SQL** — one parameterized `whereRaw('JSON_OVERLAPS(tags, ?)', [json_encode($tags)])` predicate (verified working on this app's MySQL 8.4). No index: JSON columns can't use one, and the query is already bounded by `created_by` + period.
- **Transfer units surface once** — tags live on the source (outflow) row, which is the list's unit representative; inflow rows are already excluded by the base query, so a matching unit appears exactly once and never double-counts. Fee rows are untagged by design.
- **Global search also matches tags** — the search's OR group gains a `LIKE` on the tags column (stored as JSON text, so substring matching works; multi-word terms won't match underscored slugs — accepted limitation).
- **Client engine filters locally** in the table brain (current serving mode `full`); the server filter path ships in the same phase so the paged/hybrid engines keep the shared wire vocabulary — same lockstep the existing filters maintain.
- **Suggestions for the filter sheet** come from the tagging spec's `TagService::getUniqueTags`, newly exposed as the index page's `tags` prop.

## Filter flow

```
filter sheet (Tags) ── apply ──> list handler
       │
       ├─ client/hybrid engines: table brain column filter id 'tags' (set-intersection)
       └─ server engine: filters: [{id: 'tags', value: ['food','bills']}]
                                    │ TransactionIndexRequest — id 'tags' allowed
                                    ▼
                      TransactionListFilters::$tags (slug-shape guarded)
                                    ▼
                      applyListFilters → whereRaw('JSON_OVERLAPS(tags, ?)')
```

## Design

### 1. Backend validation & filters DTO

- `TransactionIndexRequest`: `filters.*.id` `Rule::in` gains `'tags'`.
- `TransactionListFilters`: new `public readonly array $tags = []` (`@param list<string>`); `fromRequest()` maps the `'tags'` arm through a `tagValues()` shape guard — every value must match `^[a-z0-9_]+$` and be ≤ `TagService::MAX_TAG_LENGTH`, else throw `InvalidArgumentException` → 422, exactly mirroring the enum guard and unknown-column behavior.

### 2. Query

- `TransactionListService::applyListFilters`: non-empty `$filters->tags` adds the `JSON_OVERLAPS` whereRaw (empty stays unfiltered — the existing `whereIn([])` discipline).
- The search block's OR group gains `->orWhereRaw('tags like ?', [$like])`.
- `getSummary()` unchanged — it is period-scoped on the base query only, and filters don't apply to it today.

### 3. Frontend engine & handler

- `resources/js/services/transaction-list/contract.svelte.ts`: `FILTER_COLUMNS` gains `tags`; the column set gains a `tags` ColumnDef with a new `filterFn: 'arrIntersects'` (selected tags ∩ row tags ≠ ∅ — deliberately distinct from `arrayHas`, which tests membership of the row value in the selected list); the brain exposes the `tags` filter read + setter and counts it in `hasLocalFilters`.
- All three engines (`client`, `hybrid`, `server`) and `transactionListHandler` surface `tagFilter: string[]` / `setTagFilter(tags: string[])`, wired like the account/category setters.

### 4. Filter UI

- `transaction-list-filter.svelte`: a third filter chip ("All Tags" / the single tag name / "N Tags") opening a `tags` sheet, mirroring the Account/Category sheets — an "All Tags" reset row plus one toggleable row per suggestion from the `tags` prop; temp state `tTags` applied through `applyFilter()`.
- Active-filter chips: one per selected tag, individually clearable, icon `solar--tag-bold-duotone`.
- `TransactionController::index` passes `'tags' => TagService::getUniqueTags($this->user)` in both serving branches.

### 5. Testing (Pest)

- Feature: single-tag filter (plain rows); multi-tag OR; a tagged transfer unit surfaces its outflow representative exactly once (fee stays untagged); no match → empty list; unknown filter id still 422s; malformed slug value 422s; global search matches a tag substring.
- The filter UI itself has no JS unit runner in the stack — verified through the server contract tests plus the existing list behavior patterns (svelte-check for types).

## Out of scope / future

- AND semantics (all selected tags must match), per-tag usage counts in the sheet, editing tags from the list, tag management UI (see tagging spec's Out of scope).
