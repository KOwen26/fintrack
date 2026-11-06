# Import Suggestion Agent — Design

Date: 2026-11-05
Status: Approved design, pending implementation plan
Builds on: `2026-10-30-provider-csv-import-design.md` (staging lifecycle this feature augments)

## Problem

Statement import stages rows with dumb defaults: every parsed row lands in **Other Income** or **Other Expenses** (whichever matches flow), and the review pass is where a user re-categorizes everything by hand. For a 200-row monthly statement that is 200 manual category decisions, most of which are obvious from the description (`GOPAY QRIS GOFOOD JAKARTA` → Dining Out, `GAJI OKT` → Salary).

The parse itself stays deterministic and provider-gated (only Mandiri has a parser today). What the review pass lacks is a first pass of its own: something that reads each row's description and proposes a sensible category — and flags rows that look like transfers between the user's own accounts.

## Decision

Ratified decisions from the design session:

| Question | Decision |
| --- | --- |
| Job | Smart categorization of staged rows — category + transaction type (incl. transfer flag). Universal AI parsing for unsupported providers is deferred |
| Agent shape | Structured-output agent (`Agent` + `HasStructuredOutput`, no tools, no conversation). Classification API rejected (Ollama unsupported); tool-loop agent rejected (call volume, fragile on weak models) |
| Provider | **Ollama Cloud** via the SDK's `openai-compatible` driver → `https://ollama.com/v1` + `OLLAMA_API_KEY` (the documented direct-cloud surface). Native `ollama` driver spike is optional later — the agent only names its provider, so swapping is config-only |
| Execution | **`defer()`** after the upload response — no queue worker dependency. Logic lives on the `ImportSuggestion` service so moving to a queue later is a one-line call-site change |
| Apply model | **Auto-apply**: suggestions write straight into staged rows; the review pass is the correction pass. Batch-level status chip on the review page; no per-row badge |
| Terminology | `suggestion_*` throughout — never `ai_*` |
| Row-type storage | New `import_rows.transaction_type` (`income`/`expense`/`transfer`) — replaces the proposed `transfer_hint` and the smuggled "destination ≠ null means transfer" convention |
| Provenance | No `category_source` column. The future description→category cache can capture corrections at approve time without it |
| Prompt context | Statement rows + the global category tree only — **no user history, no merchant cache in v1** |
| Degrade gracefully | Suggestions are an enhancement: every failure mode leaves the import fully reviewable with dumb defaults |

Key semantic: **the agent never writes a transfer destination** — it may set `transaction_type = transfer`, but which of the user's accounts sits on the other end is a human decision, made in review.

## How it runs

```
Upload (sync, unchanged parse/stage)
  ImportService.upload() → batch created, suggestion_status = pending
  response flushed
  ── defer() ──────────────────────────────────────────────────────
  ImportSuggestion::suggestCategories(batch)
    status = processing
    rows (no parse errors, not edited since start) chunked 50/call
      → ImportSuggestions agent → Ollama Cloud
      ← [{ row_number, category_id, transaction_type }]
    validate each row (category exists, direction-legal) → apply
    status = completed | failed (+ suggestion_error, suggested_at)
  ─────────────────────────────────────────────────────────────────
Review page
  polls while pending/processing (Inertia partial reload, ~3s)
  chip: "Suggestions: processing… / applied / unavailable"
  transfer rows without destination = needs-attention before approve
  Retry suggestions button when failed (or stale processing)
```

## Design

### 1. Package & configuration

- `composer require laravel/ai` (Windows quirk: add the constraint to `composer.json` directly, then `composer update laravel/ai`).
- `php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"` + migrate. The SDK's `agent_conversations` tables are created but unused — this feature is stateless (no `RemembersConversations`, no `Conversational`).
- `config/ai.php` gains the provider entry:

```php
'ollama-cloud' => [
    'driver'  => 'openai-compatible',
    'url'     => env('OLLAMA_CLOUD_URL', 'https://ollama.com/v1'),
    'key'     => env('OLLAMA_CLOUD_API_KEY'),
    'models'  => ['text' => ['default' => env('OLLAMA_CLOUD_MODEL', 'gpt-oss:120b')]],
],
```

- Feature gates live in a **`config/feature.php`** — the app's centralized feature-flag file, introduced by this feature (keyed per feature, each entry holding its `enabled` flag plus feature-specific tuning). First entry:
```php
return [
    'import-suggestions' => [
        'enabled'    => env('IMPORT_SUGGESTIONS_ENABLED', true),
        'chunk_size' => 50,
        'timeout'    => 120, // seconds per agent call
    ],
];
```

  **Effective gate = `feature.import-suggestions.enabled` AND a non-empty `OLLAMA_CLOUD_API_KEY`** — when the gate is closed, upload behaves exactly as today (`suggestion_status` stays `null`, nothing is deferred). The gate check is the static `ImportSuggestion::isEnabled()` on the suggestion service (§3), so both `upload()` and tests read one definition (singular class name keeps it distinct from the `ImportSuggestions` agent).

- **Logging**: a dedicated daily channel `import-suggestions` (`config/logging.php` → `storage/logs/import-suggestions.log`, 14-day rotation, level env `LOG_IMPORT_SUGGESTIONS_LEVEL`, default `debug`). The run logs: start and finish (candidate rows, applied/dropped counts, duration, errors), per-chunk validation drops at `debug`, chunk failures after retry and stale flips at `warning` (with the exception object), user-triggered retries at `info` — all with `batch_id` context. At `debug`, the sent prompt (row lines + model name) and the received structured response are also logged — that is the tuning lens; set the level to `warning` in steady state to silence it. Raw prompts/responses are never logged at `info`+ levels.

- **Ollama Cloud has no structured outputs** (per Ollama docs, cloud only): the SDK's `response_format` schema is ignored, so the response shape is unguaranteed. Two defenses: the agent instructions carry the exact JSON envelope (`{"rows": [...]}`) as prompt grounding, and extraction tolerates both the envelope and a bare array — `structured['rows'] ?? structured`, skipping non-array items. Per-item validation makes any unusable response degrade to "no suggestions", never a crash. Switching to a schema-enforcing provider later removes the need for the tolerance.

### 2. The agent — `App\Ai\Agents\ImportSuggestions`

`php artisan make:agent ImportSuggestions --structured`. Implements `Agent` + `HasStructuredOutput`, uses `Promptable`. No tools, no conversation storage, no streaming. Constructor takes the chunk's rows and renders them into the prompt. The name matches the `suggestion_*` family — it suggests categories **and** transaction types, so it is not a "categorizer".

**`instructions()`** — built from the `App\Enums\Category` catalog (never hardcoded prose): every bookable case rendered as `slug — Label: description` via the enum's own `label()` and `description()` methods, split into two sections — **Income categories (for inflow rows)** and **Spending categories (for outflow rows)** — so direction is signaled by section, not per-line group labels. Rules the prompt must carry:

- Inflow rows take a category whose group flows in (`CategoryGroup::Income`); outflow rows take one from every other group.
- Prefer the most specific **child** category; fall back to its parent only when no child fits.
- Only use category slugs from the provided list — never invent one.
- Indonesian statement conventions: merchant strings (`GOPAY`, `SHOPEEPAY`, `GOFOOD`, `GRAB`), payroll wording (`GAJI`, `SALARY`), billers (`PLN`, `TOKEN LISTRIK`, `INDIHOME`), transfer wording (`TRF`, `TRANSFER`, `BI FAST`, `SETOR`, e-wallet top-ups, account-number movements).
- A row that looks like money moving between the user's own accounts (rather than income or spending) gets `transaction_type = "transfer"` — do not guess a destination.
- "income"/"expense" must match the row's flow; "transfer" is allowed on either flow.

**`schema()`**:

```php
return [
    'rows' => $schema->array()->items(
        $schema->object(fn ($s) => [
            'row_number'       => $s->integer()->required(),
            'category'         => $s->string()->required(),
            'transaction_type' => $s->string()->enum(['income', 'expense', 'transfer'])->required(),
        ])
    )->required(),
];
```

**Prompt payload** — one compact line per row: `#12 | 2026-10-03 | GOPAY QRIS GOFOOD JAKARTA [masked] | outflow`. Amounts stay out — the description carries the categorization signal.

**PII & data flow** — the only data leaving the app is the redacted prompt: descriptions with digit runs ≥5 masked as `[masked]` before assembly (account, phone, and QR references — the identifier vector — never transmitted; merchant text is kept because it *is* the categorization signal), plus row dates and flow direction, to Ollama Cloud. No identity, balances, or history beyond the statement itself; the provider's retention/training posture is unverified and structured outputs are unsupported on cloud. Debug logs inherit the redacted prompt and rotate in 14 days. This framing is personal-use; if the app ever serves other users, a settings-level consent gate for AI suggestions becomes a requirement (UU PDP 27/2022 classifies financial data as specific personal data), and local Ollama remains the zero-egress fallback.

Every call passes `provider: 'ollama-cloud'` and the configured timeout.

### 3. The service — `App\Services\Import\ImportSuggestion::suggestCategories()`

One service class carries both the gate (`isEnabled()`, §1) and the runner: `suggestCategories(ImportBatch $batch)` — `defer()` runs in the same process moments after upload, so the model instance is safe to pass; the run re-checks lifecycle state between chunks anyway. Dispatched from `upload()` via `defer()` — `defer()` chosen over a queued job to drop the worker dependency; a plain method keeps a later queue swap trivial. `suggestChunk()` returns per-chunk `{applied, dropped_slug, dropped_direction, dropped_type}` counts that feed the aggregate logs (§1 Logging).

1. Guard: batch is still `staging` and `suggestion_status ∈ {pending, processing}`; otherwise no-op.
2. Snapshot `$startedAt`; set `suggestion_status = processing`.
3. Select candidate rows: no `parse_errors`, `flow` and `amount` present, and **parser-default fields only** — `category_id` still `other_income`/`other_expenses`, `transaction_type` still `income`/`expense`, `transfer_destination_account_id` still null. Anything else was touched by the user in review and is theirs — suggestions never overwrite human choices. (Field-level guard chosen over an `updated_at` comparison: precise about the fields actually written, and immune to NULL timestamps from `insert()` staging.) Duplicates are included; the user may re-include them.
4. Chunk candidates by `chunk_size`; per chunk, prompt the agent.
5. **Validate every returned row** before applying:
   - `row_number` exists in the chunk → else ignore that entry;
   - `category` resolves via `Category::tryFrom()` and the group's flow matches the row's flow (`$category->group()->flow() === $row->flow`) → else keep the row's current default category;
   - `transaction_type` matches the row's flow direction (`income` on inflow, `expense` on outflow) or is `transfer` → else keep the parsed type;
   - unknown/missing rows in the response simply keep their defaults.
6. Apply per chunk with a guarded `whereKey(...)->update(...)` that re-checks the same parser-default conditions at write time (rows re-categorized mid-run match nothing and are skipped): writes `category_id` (slug string) and `transaction_type` (enum value) — `flow` never changes, since income/expense already match the parsed flow by validation and `transfer` keeps it.
7. A chunk that throws (connection, timeout, invalid structured output) is retried **once**; a second failure tolerates that chunk (its rows keep defaults) and records the error.
8. Finalize: any chunk applied → `completed` (+ `suggested_at`; partial coverage noted in `suggestion_error`); none applied → `failed` (+ error message).
9. Re-check staging before each apply: if the batch was approved/discarded mid-run, stop and mark `failed` with a "batch left staging" note.

### 4. Schema

Modified **in place** in the existing create-table migrations (dev-stage app, explicit decision) — no new migration files, no backfill. Existing dev data is rebuilt with `migrate:fresh`.

**`..._create_import_batches_table.php`** gains:

| Column | Type | Notes |
| --- | --- | --- |
| `suggestion_status` | string, nullable | `App\Enums\ImportSuggestionStatus`: `Pending`/`Processing`/`Completed`/`Failed`; `null` = suggestions never requested |
| `suggestion_error` | text, nullable | last chunk error or partial-coverage note |
| `suggested_at` | timestamp, nullable | completion time |

**`..._create_import_rows_table.php`** gains:

| Column | Type | Notes |
| --- | --- | --- |
| `transaction_type` | string, nullable | `App\Enums\TransactionType` values; set from parsed `flow` at staging time (`inflow→income`, `outflow→expense`, unknown→`null`) |
| `created_at` / `updated_at` | timestamps | `upload()` stages rows via `insert()`, which bypasses Eloquent timestamps — both are set explicitly from `now()` at staging time (needed for the batch staleness check and general hygiene; the suggestion guard is field-based, not timestamp-based) |

**Model changes**: `ImportBatch` casts `suggestion_status` to the new enum; `ImportRow` casts `transaction_type` to `TransactionType`.

**No change** to `ImportBatchStatus` — suggestion state is orthogonal to lifecycle (a batch is `staging` while suggestions run).

### 5. Upload wiring

In `ImportService::upload()`, after the staging transaction commits (end of method):

```php
if (ImportSuggestion::isEnabled()) {
    $batch->update(['suggestion_status' => ImportSuggestionStatus::Pending]);
    defer(fn () => app(ImportSuggestion::class)->suggestCategories($batch));
}
```

The deferred closure takes the batch **id** and re-fetches — the model instance outlives the response by then. Parse failures throw before this point, so nothing is deferred for rejected files.

### 6. Review-flow adjustments

- The staging insert in `upload()` now also sets `transaction_type` from the parsed flow (`inflow → income`, `outflow → expense`, unknown flow → `null`), alongside the existing default-category logic.
- `updateRow()` now persists `transaction_type` directly; `flow` derives via `toCashflow()` for income/expense, while **transfer rows keep the parsed flow** — the statement's direction decides source/destination booking in `importTransferRow()`. The existing `clearsTransfer` logic keys off `type !== Transfer` exactly as today.
- `assertRowImportable()`: a row is a transfer iff `transaction_type === Transfer`; that requires a destination (valid, owned, not the batch's own account). A destination without `transaction_type === Transfer` is defensively rejected — `updateRow` already prevents it.
- Approve, discard, and materialization are otherwise untouched — suggestions only ever mutate staged fields.

### 7. Frontend — review page (`app/settings/imports/review`)

- **Status chip** in the batch header, driven by `suggestion_status`: `pending`/`processing` → spinner "Suggestions: processing…"; `completed` → "Suggestions applied — review before approving"; `failed` → muted "Suggestions unavailable"; `null` → nothing.
- **Polling**: while `pending`/`processing`, `router.reload({ only: [...] })` every ~3s; stop on terminal state.
- **Transfer needs-attention**: rows with `transaction_type = transfer` and no destination get the existing highlight treatment and a destination picker prompt — approve is blocked on them anyway.
- **Retry suggestions**: button when `failed`, or when `processing` is stale (batch `updated_at` older than 10 minutes — treat as dead). `POST` to a new `ImportController@retrySuggestions` (owner policy, staging-only guard) which resets to `pending` and re-defers. A stale `processing` batch is lazily flipped to `failed` on review page render.
- Wayfinder regeneration covers the new route; no other frontend surface changes.

### 8. Failure modes

| Failure | Behavior |
| --- | --- |
| Gate closed (no key / disabled) | Upload as today; `suggestion_status = null`; no chip |
| Cloud unreachable / timeout / 5xx on a chunk | Chunk retried once; rows keep defaults; noted in `suggestion_error` |
| All chunks fail | `failed` + message; retry button appears |
| Structured output invalid / hallucinated ids | Row-level validation drops bad entries silently |
| Process dies mid-defer | Stuck `processing`; stale >10 min → lazily failed on next review render; retry button |
| Batch approved/discarded mid-run | Apply steps re-check staging; stop + `failed` ("batch left staging") |
| User edits a row mid-run | Apply re-checks parser-default fields at write time — re-categorized/retyped rows match nothing (counted as `skipped_edited`) |

Every path ends in a fully functional staging batch with dumb defaults — suggestions can only improve the starting point, never block the import.

### 9. Testing (Pest, no network)

- **Integration — `tests/Integration/Services/Import/ImportSuggestionTest.php`** (house suite: booted app + DB, direct class invocation, `it()` style; register the file in the `module:import` group map in `tests/Pest.php`) with `ImportSuggestions::fake([...structured rows...])`: happy path applies category + type; invalid category slug ignored; direction-illegal category ignored; transfer sets type but not destination; partial chunk failure → `completed` + note; total failure → `failed`; user-edited row skipped; non-staging batch no-ops.
- **Feature — upload**: gate on → `pending` + rows categorized after the request cycle (deferred callbacks flush on termination in tests); gate off → `null`, nothing applied; parse failure → nothing deferred.
- **Feature — retry route**: owner-only (policy), staging-only, resets to `pending`.
- **Feature — approve guard**: transfer row without destination rejected; with destination imports via `TransferService` (existing behavior).
- **Migration**: `migrate:fresh` yields the new columns with nullable defaults; staging insert populates `transaction_type` from flow.
- Real Ollama Cloud is never touched in CI; the default model id is config, not code.

## File inventory

### New files

| File | Purpose |
| --- | --- |
| `app/Ai/Agents/ImportSuggestions.php` | The agent — instructions + structured-output schema (`make:agent ImportSuggestions --structured`) |
| `app/Services/Import/ImportSuggestion.php` | The suggestion service — static `isEnabled()` gate + `suggestCategories()` runner (chunks rows, prompts the agent, validates, applies, manages status) |
| `app/Enums/ImportSuggestionStatus.php` | `Pending`/`Processing`/`Completed`/`Failed` backed enum |
| `config/ai.php` | Published SDK config + the `ollama-cloud` provider entry (`vendor:publish`) |
| `config/feature.php` | New centralized feature-flag file — first entry: `import-suggestions` (`enabled`, `chunk_size`, `timeout`) |
| `resources/js/components/module/import/import-suggestion-status.svelte` | Status chip (processing / applied / unavailable) with polling |
| `tests/Feature/App/Import/ImportSuggestionsTest.php` | Upload wiring, gate on/off, retry route, approve guards |
| `tests/Integration/Services/Import/ImportSuggestionTest.php` | Service logic with `ImportSuggestions::fake()` |
| `tests/Unit/ImportSuggestionsAgentTest.php` | Agent catalog instructions (pure enum logic — Unit suite) |
| `.env.example` additions | `OLLAMA_CLOUD_URL`, `OLLAMA_CLOUD_API_KEY`, `OLLAMA_CLOUD_MODEL`, `IMPORT_SUGGESTIONS_ENABLED` |
| SDK vendor migrations | `agent_conversations*` tables from `vendor:publish` (unused by this feature, harmless) |

### Touched files

| File | Change |
| --- | --- |
| `composer.json` / `composer.lock` | `laravel/ai` dependency (edit json directly on Windows, then `composer update laravel/ai`) |
| `database/migrations/2026_10_30_000001_create_import_batches_table.php` | Add `suggestion_status`, `suggestion_error`, `suggested_at` |
| `database/migrations/2026_10_30_000002_create_import_rows_table.php` | Add `transaction_type` |
| `app/Models/ImportBatch.php` | Cast `suggestion_status` → `ImportSuggestionStatus` |
| `app/Models/ImportRow.php` | Cast `transaction_type` → `TransactionType` |
| `app/Services/ImportService.php` | `upload()`: staging insert sets `transaction_type`, gate + defer dispatch; `updateRow()`: persists `transaction_type` directly; `assertRowImportable()`: transfer keys off `transaction_type` |
| `app/Http/Controllers/ImportController.php` | `retrySuggestions()` action + stale-processing lazy flip on `show()` |
| `routes/web.php` | `POST imports/{batch}/suggestions/retry` in the existing import group |
| `resources/js/pages/app/settings/imports/review.svelte` | Mount status chip; transfer-without-destination attention state; retry button |
| `resources/js/components/module/import/import-review-table.svelte` | Render `transaction_type` transfer marker / destination prompt |
| `resources/js/components/module/import/import-row-editor-form.svelte` | Type field already exists — align with stored `transaction_type` semantics |
| `resources/js/wayfinder/**` | Regenerated (`php artisan wayfinder:generate`) — never hand-edited |

## Out of scope (future hooks, not promises)

- Universal AI statement parsing for providers without a parser — the `ParserRegistry` gate stays.
- Description→category cache (write at approve time from final categories — no provenance column needed).
- User-history prompt context or tools on the agent.
- Per-row suggestion badges / provenance, confidence scores.
- Streaming or chat-style review UI.
