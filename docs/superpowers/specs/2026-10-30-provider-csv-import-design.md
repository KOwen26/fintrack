# Provider CSV Transaction Import — Design

**Date:** 2026-10-30
**Status:** Approved design (pending implementation plan)
**Scope:** CSV statement import per provider — Mandiri first, as the reference implementation for future providers.

## Summary

Users import bank/e-wallet statement CSVs into a specific account. Each provider's export format is handled by a built-in, dev-maintained parser. Parsed rows land in a **staging review area** where the user edits them (full row editing, category assignment, transfer marking, include/exclude) before a **bulk approve** materializes real transactions. Duplicate rows are fingerprinted, flagged, and skipped by default.

This is the first slice of the roadmap's "deeper Provider integration" (providers are metadata-only today).

## Decisions (from brainstorming)

| Topic | Decision | Why |
| --- | --- | --- |
| Format ownership | Built-in parsers keyed by provider slug | Accuracy and zero user setup; adding a provider is a deliberate code change |
| First provider | Mandiri only | One clean reference implementation; others follow the pattern |
| Flow | Upload → parse → staging review → bulk approve | Most control; bad files never touch real data |
| Categories | Default `OtherIncome` / `OtherExpenses` by flow, editable in review | Keeps the no-uncategorized invariant; category stats stay meaningful |
| Duplicates | Fingerprint detect & flag; excluded by default, overridable | Re-uploads and overlapping periods must not double-count |
| Account scoping | Per-account action; account's provider picks the parser | Transactions land on exactly one account; guardrailed |
| Review editing | Full row editing | Parser quirks fixable inline; error rows salvageable |
| Approval | One bulk "Import N rows"; excluded rows stay until batch discarded | Simple and flexible |
| Transfer rows | No auto-detection; user marks rows as transfers with a destination | Honors the transfer aggregate model |
| Processing | Synchronous, capped file size | Statement files are small; queueing is YAGNI for v1 |

## Architecture

```
Upload → ImportService.upload()
           ├─ resolve account.provider → ParserRegistry → MandiriCsvParser
           ├─ parse file → ParsedRow[] (each carries the raw CSV row)
           ├─ default categories by flow (OtherIncome / OtherExpenses)
           ├─ fingerprint rows + flag duplicates
           └─ create ImportBatch + ImportRows (staging)
Review  → row edits (inline), transfer marking, include/exclude toggles
Approve → ImportService.approve(batch)
           └─ DB::transaction: chunked creation per included row
              (Transaction::create so observers fire; TransferService for
              transfer-marked rows), batch → approved
Discard → ImportService.discard(batch) → batch → discarded (rows kept as history)
```

### Components

All under `app/Services/Import/`:

- **`Contracts\ProviderCsvParser`** — interface: `parse(string $contents): ParsedStatement`.
  Each parser declares its provider's **pinned layout** — delimiter, skipped
  preamble rows, and column indexes — plus cell-level locale parsing (date
  formats, id-ID amounts, debit/kredit → `Cashflow` mapping). No header
  matching: a file where no row parses against the pinned layout is rejected
  at file level.
- **`Parsers\MandiriCsvParser`** — reference implementation pinning the common
  Mutasi Rekening layout (1 header row; `Tanggal;Keterangan;Cabang;Tipe;Debit;Kredit;Saldo`
  semicolon-delimited; date = column 0, description = column 1, debit = column 4,
  kredit = column 5; `d/m/Y` dates, dot-thousand amounts, UTF-8 BOM stripped).
  **Placeholder until a real sample export is verified against it.**
- **`ParserRegistry`** — provider slug → parser class. Mandiri registered.
  Unregistered provider → friendly "not supported yet" error. Adding a provider
  = one parser class + one registry entry.
- **Optional laravel-excel variant** — the parsing plan documents an
  alternative `MandiriExcelParser` backed by `maatwebsite/excel` (same
  contract, same pinned layout, CSV + XLSX capable; package adoption is
  owner-approved but pending). The native pinned parser remains the default;
  the registry entry for a slug is a swap, never a duplicate.
- **Value objects** — `ParsedRow` (date, description, amount, flow, raw array),
  `ParsedStatement` (rows + file-level meta).
- **`ImportService`** — lifecycle: `upload()`, `updateRow()`, `approve()`,
  `discard()`. Thin controllers per app convention.

## Data Model

### `import_batches`

| Column | Notes |
| --- | --- |
| `id` | |
| `created_by` | FK `users`, cascade |
| `account_id` | FK `accounts`, cascade |
| `provider_id` | FK `providers` |
| `source_filename` | string |
| `status` | `ImportBatchStatus` enum: `staging`, `approved`, `discarded` |
| `total_rows` | int |
| `imported_rows` | int, filled at approve |
| `approved_at` / `discarded_at` | nullable timestamps |
| `timestamps` | |

Indexes: `account_id`, `created_by`, `status`.

### `import_rows`

| Column | Notes |
| --- | --- |
| `id` | |
| `batch_id` | FK `import_batches`, cascade |
| `row_number` | original file order |
| `transaction_date` | nullable datetime (parse may fail) |
| `description` | nullable |
| `amount` | `decimal(15,2)` — matches `transactions` |
| `flow` | `Cashflow` enum, nullable |
| `category_id` | `Category` enum (string), defaults by flow |
| `is_included` | bool |
| `is_duplicate` | bool |
| `transfer_destination_account_id` | nullable FK `accounts` — when set, the row is transfer-marked |
| `fingerprint` | string, indexed |
| `parse_errors` | JSON nullable — field → message list |
| `raw` | JSON — original CSV row, audit trail |
| `timestamps` | |

Row defaults at parse: valid row → included; duplicate → flagged **and** excluded
(overridable); parse-error row → excluded with field-level errors.

New enum: `App\Enums\ImportBatchStatus` (backed string: `staging`, `approved`,
`discarded`).

## Duplicate Detection

No fingerprint column on `transactions` (core table untouched). At parse time:

1. Load the account's existing transactions overlapping **only the file's
   min–max date span** (statement period — bounded).
2. Compute fingerprints in PHP: `account + date + amount + normalized
   description` (whitespace/case-normalized). Also check within-file
   duplicates.
3. Flag matching staged rows.

## Transfer Marking

- Row editor offers "Mark as transfer" with a **destination account picker**
  (user's other accounts only).
- Direction follows flow:
  - Outflow → this account is the **source**; mirrored *(transfer, inflow)*
    member created on the destination at approve.
  - Inflow → this account is the **destination**; *(transfer, outflow)* member
    created on the picked source account.
- Approve routes transfer-marked rows through the **existing `TransferService`**
  path — aggregates, member rows, and balance observers behave identically to
  hand-entered transfers. Category is irrelevant for transfer members (the
  documented exception). No fee in v1; user edits the transfer later.
- Approve-time validation: destination required, must differ from batch account.
- Known limitation (shared with manually-entered transfers): the synthetic
  mirrored row may collide with that account's own later statement import;
  fingerprints are description-based so it usually won't auto-match — the user
  excludes the statement row manually.

## Approval Semantics

- Server re-validates every included row (amount > 0, date present, category
  present, no outstanding parse errors, transfer destination valid). Client
  state is not trusted.
- Plain rows: `Transaction::create()` in chunks — observers fire, so
  `current_balance` stays correct. `created_by` = batch creator, `tags` = null,
  `transfer_id` = null, type derived from flow (`income`/`expense`).
- Transfer-marked rows: via `TransferService`.
- One `DB::transaction`; on success batch → `approved`, `imported_rows` filled.
- Approving with zero included rows is rejected with a validation error (the UI
  disables the button, but the server enforces it).
- Discard flips status only — staged rows remain as audit history.

## Authorization

Owner-only on the account, including for shareable accounts — matches the
domain rule that mutating is owner-only. The account must have a provider with
a registered parser.

## UI / Flow

1. **Account page** — "Import statement" button, shown only when the account's
   provider has a registered parser; tooltip names the expected format.
2. **Upload page** `accounts/{account}/import` — dropzone, format hint, caps
   (~5 MB / ~10k rows, CSV mime). Synchronous processing.
3. **Review page** `imports/{batch}` — staging area:
   - TanStack table via DataComposer + Wayfinder-generated types.
   - Columns: include toggle, date, description, amount + flow badge, category
     select, status badges (duplicate / error / transfer).
   - Inline full-row editing; "Mark as transfer" → destination picker.
   - Sticky summary bar: *N included · D duplicates excluded · E errors* +
     **Import N rows** + **Discard batch** (ConfirmationModal).
4. **Post-approve** — redirect to the account with a flash summary
   ("Imported 128 transactions · 3 skipped as duplicates").

Routes: `accounts/{account}/import` (GET/POST), `imports/{batch}` (GET),
`imports/{batch}/approve`, `imports/{batch}/discard`, `imports/{batch}/rows/{row}`
(PATCH). Controllers stay thin; `ImportService` holds the logic. Layout switch
in `app.ts` gains the `imports` prefix.

## Edge Cases

- **File-level failures** (empty, wrong structure, unrecognized layout) →
  validation error on the upload form; no batch created. Batch requires ≥ 1
  parseable row.
- **Row-level failures** → staged with `parse_errors`, excluded by default;
  fixable inline.
- **Amount quirks** (parser-owned): `Rp` prefixes, dot thousands / comma
  decimals, `,-` suffixes, negative signs, debit/kredit column pairs.
- **Encoding**: parser-declared; Mandiri placeholder assumes UTF-8 with a BOM
  stripped and a documented hook for UTF-16/CP1252 exports.
- **Concurrent batches** per account allowed; duplicate checks cover live
  transactions + same batch only (cross-batch overlap is a documented v1
  limitation).
- **Imported transactions are ordinary rows** — normal edit/delete applies
  afterward. Staged rows are never edited post-approval.

## Out of Scope (v1)

- Parsers for providers other than Mandiri (follow-up per provider).
- Automatic transfer detection; transfer fees on import-created transfers.
- Queued/async parsing; cross-batch duplicate detection; auto-categorization
  rules; PDF/XLSX statement formats.

## Testing (Pest)

- **Parser unit tests** — fixtures in `tests/Fixtures/Imports/mandiri/`: clean
  file, dirty rows, delimiter variants, amount-locale variants.
- **Feature tests**: upload creates batch + defaulted rows; duplicate flagging
  (existing-transaction + within-file); row-edit endpoints; approve
  materializes transactions **and balances update** (observer integration);
  transfer-marked rows create proper aggregates with mirrored members; approve
  re-validation rejects error rows; discard; authorization (non-owner and
  shareable-viewer → 403).
- Run `php artisan wayfinder:generate` after backend changes; frontend consumes
  generated types only.
