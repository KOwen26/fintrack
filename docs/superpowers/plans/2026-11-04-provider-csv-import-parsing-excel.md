# Provider Statement Import — Parsing Layer (laravel-excel) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use better-executing-plans to implement this plan task-by-task — or better-parallel-subagents-executions if this plan splits into independent slices, or superpowers:subagent-driven-development if workflow mode is `remote`. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The provider-scoped statement-parsing layer with **reading fully delegated to laravel-excel**: a parser contract over `UploadedFile`, parse-result value objects, a shared format-flexible file-reading helper, a provider-agnostic cell-value concern (id-ID amounts, per-provider date/time formats), the pinned-layout XLSX-only Mandiri parser (verified against the real export), and the slug-keyed registry mapping providers to parsers.

**Architecture:** Four pieces with clean boundaries. `StatementFileReader` is the single file-reading helper — deliberately **format-flexible** (CSV settings remain, so a future CSV provider reuses it unchanged), though the feature surface is XLSX-only: `Excel::toArray()` owns every file mechanic (format detection, delimiter, quotes/enclosures, BOM, input encoding) and cells are normalized, with typed XLSX numbers re-rendered in id-ID form, so parsers receive plain `string|null` rows and app code never splits lines or sniffs formats. `ParsesStatementCells` (concern/trait) owns only generic **cell-value** semantics — id-ID amount normalization and date/time parsing against per-provider format lists — bounded by no provider and unit-tested against plain values. Each parser owns its **layout**: the pinned data-row start, column indexes, and the row pairing and assembly for its column convention (Mandiri: XLSX only, two rows per transaction — a main row plus a time-only companion row — and the debit/kredit column pair) composed from the concern's primitives. `parse(UploadedFile)` is a parser's only public method. Wrong-file detection is positional: zero usable rows against the pinned layout is a file-level exception; row-level problems stage as field errors. Spec: `docs/superpowers/specs/2026-10-30-provider-csv-import-design.md`. **Supersedes `2026-10-30-provider-csv-import-parsing.md`** (native `str_getcsv` parser — kept for reference only, do not execute). Companion plans (both depend on this one, both need the small deltas in Post-Plan Notes): `2026-10-30-provider-csv-import-staging.md`, `2026-10-30-provider-csv-import-frontend.md`.

**Tech Stack:** PHP 8.5 (8.4+ idioms), maatwebsite/excel **4.0.6** (already in composer.json — do not touch dependencies), Carbon/CarbonImmutable, Pest (Unit for the cell concern — no app; Integration for the parser — booted app for the `Excel` facade). **The actual Mandiri export is XLSX and the feature surface is XLSX-only** (`mimes:xlsx` upstream); the pinned layout below is verified against the real export.

**Workflow Mode:** `direct` — tests are written as part of each task, but no task runs/verifies/commits. The human reviewer handles test runs, formatting, and commits as they choose. (Project AGENTS.md rules still apply to the executor while implementing: run `vendor/bin/pint --dirty --format agent` after PHP edits.)

**Complexity:** Medium (Task 4 Medium; Tasks 1–3, 5, 6 Low)

## Global Constraints

- PHP 8.4+ style: `final` classes, `readonly` where possible, constructor property promotion, explicit return types, curly braces on all control structures, first-class callables, PHPDoc over inline comments.
- **Reading is fully delegated to laravel-excel** via `StatementFileReader`. No `str_getcsv`, no manual line splitting, no BOM stripping, no delimiter/extension sniffing in app code. App-owned logic is semantics only: pinned layout, date/amount locale parsing, failure tiers.
- **Version-pinned API facts (verified against installed vendor 4.0.6 — do not copy 3.1 examples from elsewhere):**
  - `Maatwebsite\Excel\Facades\Excel::toArray(Import $import, string|UploadedFile $filePath, ?string $disk = null, ?string $readerType = null): array` — accepts the `UploadedFile` directly; `FileTypeDetector` picks the reader from the file.
  - `Maatwebsite\Excel\Concerns\ToArray::array(array $array): void` — **void**, not array-returning. The facade's return value is the sheet data.
  - `Maatwebsite\Excel\Concerns\WithCustomCsvSettings::getCsvSettings(): array` — keys include `delimiter` and `input_encoding`. (Kept in the reader for future CSV providers; XLSX ignores it.)
- Two failure tiers, always: file-level unreadable layout → throw `ImportFileException`; row-level problems → `ParsedRow::$errors`, never dropped rows.
- `StatementFileReader::rows()` normalizes every cell to `string|null`, **re-rendering typed number cells (native XLSX floats) in id-ID form** (`350000.5` → `"350000,50"`). Without this, `(string)` on a typed float yields `"350000.5"` and `parseAmount` strips the dot as a thousands separator — a 10× corruption.
- The cell concern is **value parsing only** (dates, times, amounts) — pure, provider-agnostic, no facades, no container, no I/O, no Eloquent. **Row pairing, assembly and column layout belong to each parser**: providers differ in row and column conventions (debit/kredit pairs, single signed columns, direction markers, split date/time rows), so parsers compose the concern's primitives into their own assembly. The only container touch in this package is the `Excel` facade inside `StatementFileReader` and `ParserRegistry::resolve()`'s `abort_if` guard (422), which the staging plan relies on. No DB, no HTTP anywhere.
- Layout constants pin the **data**, not any header — headers/preamble blocks are never read or matched. `DATA_START_ROW` is the 0-based index of the first transaction row among non-blank rows (Mandiri: `10`, an account-info preamble block).
- Test style per `.ai/rules/tests.md`: Unit and Integration suites use `it('...', ...)` declarations (`test()` is Feature-only); input enumerations use `->with([...])` datasets with named cases; per-file setup helpers are plain functions with globally unique names (redeclaration across files is a fatal error); shared helpers live only in `tests/Support/helpers.php`.
- Fixtures are part of this plan's contract: the staging plan's tests depend on `mandiri-basic.xlsx` containing exactly 3 transactions (2 inflows + 1 outflow: 2,500,000 + 25,000 − 350,000), on `PEMBAYARAN LISTRIK PLN` being the 350,000 outflow, and on the shared `import_fixture()` helper. `mandiri-dirty.xlsx` must always keep at least one clean row first plus ≥1 error row (staging reads it loosely: "first excluded row"). Fixtures are **committed XLSX binaries** generated by `tests/Support/make-import-fixtures.php` — regenerate rather than hand-edit; every fixture mirrors the real export (10 preamble rows, then main+companion row pairs).
- Negative amounts are a row-level error in Mandiri's debit/kredit convention; descriptions are whitespace-collapsed to single-spaced text (so `Pembayaran QR\nke …` and its flat spelling hash the same fingerprint downstream).

## File Structure

- Create: `app/Services/Import/Contracts/ProviderStatementParser.php` — the per-provider parser interface (renamed from the superseded plan's `ProviderCsvParser`; the feature surface is XLSX-only).
- Create: `app/Services/Import/ParsedRow.php`, `app/Services/Import/ParsedStatement.php` — parse result value objects.
- Create: `app/Services/Import/ImportFileException.php` — file-level failure.
- Create: `app/Services/Import/StatementFileReader.php` — the shared, format-flexible file-reading helper (CSV + XLSX → normalized string cell rows).
- Create: `app/Services/Import/Concerns/ParsesStatementCells.php` — the provider-agnostic cell-value concern (dates + times + amounts only).
- Create: `app/Services/Import/Parsers/MandiriStatementParser.php` — pinned layout, main/companion row pairing, debit/kredit assembly, XLSX-only; `parse()` is its only public method.
- Create: `app/Services/Import/ParserRegistry.php` — provider slug → parser map.
- Modify: `tests/Support/helpers.php` (add the shared `import_fixture()` helper), `tests/Pest.php` (register the `module:import` group for the integration test file).
- Create: `tests/Support/make-import-fixtures.php` (the fixture generator) and `tests/Fixtures/Imports/mandiri-basic.xlsx`, `mandiri-dirty.xlsx`, `mandiri-unrecognized.xlsx` (its output).
- Modify: `routes/dev.php` (Task 6 — dev-only parser debug endpoint).
- Tests: `tests/Unit/Services/Import/ParsesStatementCellsTest.php`, `tests/Integration/Services/Import/MandiriStatementParserTest.php`

---

### Task 1: Parser contract, value objects, exception

**Complexity:** Low

**Files:**
- Create: `app/Services/Import/Contracts/ProviderStatementParser.php`
- Create: `app/Services/Import/ParsedRow.php`
- Create: `app/Services/Import/ParsedStatement.php`
- Create: `app/Services/Import/ImportFileException.php`

No test — inert data shapes; they are pinned behaviorally by the Task 3–4 tests and the staging plan's tests (project convention: value objects are tested through behavior, not shape).

**Interfaces:**
- Consumes: `App\Enums\Cashflow` (existing).
- Produces (consumed by Tasks 3–5 and the staging plan):
  - `App\Services\Import\Contracts\ProviderStatementParser::parse(UploadedFile $file): ParsedStatement`
  - `App\Services\Import\ParsedStatement` — `public readonly array $rows` (`list<ParsedRow>`).
  - `App\Services\Import\ParsedRow` — `public readonly ?CarbonImmutable $date`, `public readonly ?string $description`, `public readonly ?float $amount`, `public readonly ?Cashflow $flow`, `public readonly array $raw` (`array<int, string|null>`), `public readonly array $errors` (`array<string, string>`).
  - `App\Services\Import\ImportFileException extends RuntimeException`.

- [x] **Step 1: Create the contract**

```php
<?php

namespace App\Services\Import\Contracts;

use App\Services\Import\ParsedStatement;
use Illuminate\Http\UploadedFile;

/**
 * One provider's statement export. Reading is fully delegated to
 * laravel-excel; implementations own only semantics: their pinned
 * layout (data-row start + column indexes), their column convention's
 * row pairing and assembly, and their date/time formats. File-level
 * structure failures throw ImportFileException; row-level problems
 * surface as ParsedRow::errors.
 */
interface ProviderStatementParser
{
    public function parse(UploadedFile $file): ParsedStatement;
}
```

- [x] **Step 2: Create the value objects**

`app/Services/Import/ParsedRow.php`:

```php
<?php

namespace App\Services\Import;

use App\Enums\Cashflow;
use Carbon\CarbonImmutable;

/** One parsed statement line — nulls mark values that failed to parse. */
final readonly class ParsedRow
{
    /**
     * @param  array<int, string|null>  $raw     original main-row columns
     * @param  array<string, string>    $errors  field name => message
     */
    public function __construct(
        public readonly ?CarbonImmutable $date,
        public readonly ?string $description,
        public readonly ?float $amount,
        public readonly ?Cashflow $flow,
        public readonly array $raw,
        public readonly array $errors = [],
    ) {}
}
```

`app/Services/Import/ParsedStatement.php`:

```php
<?php

namespace App\Services\Import;

/** The full parse result of one statement file. */
final readonly class ParsedStatement
{
    /** @param list<ParsedRow> $rows */
    public function __construct(
        public readonly array $rows,
    ) {}
}
```

- [x] **Step 3: Create the file-level exception**

`app/Services/Import/ImportFileException.php`:

```php
<?php

namespace App\Services\Import;

use RuntimeException;

/** File-level parse failure — the layout itself is unreadable, no batch is created. */
final class ImportFileException extends RuntimeException {}
```

---

### Task 2: Fixtures (generated XLSX) and the shared fixture helper

**Complexity:** Low

**Files:**
- Create: `tests/Support/make-import-fixtures.php`
- Create: `tests/Fixtures/Imports/mandiri-basic.xlsx` (generated)
- Create: `tests/Fixtures/Imports/mandiri-dirty.xlsx` (generated)
- Create: `tests/Fixtures/Imports/mandiri-unrecognized.xlsx` (generated)
- Modify: `tests/Support/helpers.php`

No test — these are test data; they are pinned by the Task 3–4 tests and consumed by the staging plan. Fixtures are real XLSX binaries built by the committed generator (regenerate with `php tests/Support/make-import-fixtures.php` — never hand-edit); they exercise the real reader path end-to-end.

**Interfaces:**
- Produces (consumed by Tasks 3–4 and the staging plan's `statementFile()` helper): `import_fixture(string $name): string` returning the raw bytes of `tests/Fixtures/Imports/{name}`.

- [x] **Step 1: Create the generator and run it**

`tests/Support/make-import-fixtures.php` (standalone script — bootstraps only composer autoload, never loaded by Pest):

```php
<?php

/**
 * Regenerates the Mandiri import fixtures as real XLSX binaries.
 *
 * Run: php tests/Support/make-import-fixtures.php
 */

require __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** The 10 preamble rows every fixture carries before the first transaction. */
function preamble_rows(): array
{
    return array_map(
        fn (string $line): array => [$line],
        [
            'Bank Mandiri',
            'Mutasi Rekening',
            'Dummy Preamble 1',
            'Dummy Preamble 2',
            'Dummy Preamble 3',
            'Dummy Preamble 4',
            'Dummy Preamble 5',
            'Dummy Preamble 6',
            'Dummy Preamble 7',
            'Dummy Preamble 8',
        ]
    );
}

/** A main transaction row at the pinned column indexes. */
function main_row(string $sequence, string $date, string $description, ?string $credit = null, ?string $debit = null): array
{
    $row = array_fill(0, 19, null);
    $row[0] = $sequence;
    $row[4] = $date;
    $row[7] = $description;
    $row[15] = $credit;
    $row[18] = $debit;

    return $row;
}

/** A time-companion row carrying only the time in the date column. */
function time_row(string $time): array
{
    $row = array_fill(0, 5, null);
    $row[4] = $time;

    return $row;
}

/** @param list<array<int, string|null>> $rows */
function save_fixture(array $rows, string $name): void
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([...preamble_rows(), ...$rows], null, 'A1');

    IOFactory::createWriter($spreadsheet, 'Xlsx')->save(__DIR__ . '/../Fixtures/Imports/' . $name);

    echo "wrote {$name}\n";
}

save_fixture([
    main_row('1,00', '31 Oct 2026', 'TRSF E-BANKING CR BCA', credit: '2.500.000,00'),
    time_row('12:49:41 WIB'),
    main_row('2,00', '30 Oct 2026', 'PEMBAYARAN LISTRIK PLN', debit: '350.000,00'),
    time_row('14:28:28 WIB'),
    main_row('3,00', '29 Oct 2026', 'BUNGA TABUNGAN', credit: '25.000,00'),
    time_row('08:42:25 WIB'),
], 'mandiri-basic.xlsx');

save_fixture([
    main_row('1,00', '31 Oct 2026', 'TRSF E-BANKING DB GOPAY', debit: '50.000,00'),
    time_row('08:59:56 WIB'),
    main_row('2,00', '31 Foo 2026', 'BAD DATE ROW', debit: '10.000,00'),
    time_row('06:23:59 WIB'),
    main_row('3,00', '30 Oct 2026', 'NO AMOUNT ROW'),
    time_row('12:43:57 WIB'),
    main_row('4,00', '31 Oct 2026', 'BOTH COLUMNS ROW', credit: '10.000,00', debit: '20.000,00'),
    time_row('13:21:08 WIB'),
    main_row('5,00', '30 Oct 2026', 'NEGATIVE AMOUNT ROW', debit: '-25.000'),
    time_row('19:36:49 WIB'),
], 'mandiri-dirty.xlsx');

save_fixture([
    ['A', 'B', 'C', 'D', 'E'],
    ['1', '2', '3', '4', '5'],
], 'mandiri-unrecognized.xlsx');
```

Run: `php tests/Support/make-import-fixtures.php` → the three `.xlsx` files appear in `tests/Fixtures/Imports/`.

- [x] **Step 2: Add the shared fixture loader to `tests/Support/helpers.php`**

Both parser test files — and the staging plan's tests — call it; defining it per file would fatal on redeclaration:

```php
/** Raw contents of a statement import fixture, e.g. `import_fixture('mandiri-basic.xlsx')`. */
function import_fixture(string $name): string
{
    return file_get_contents(__DIR__ . '/../Fixtures/Imports/' . $name);
}
```

---

### Task 3: ParsesStatementCells — the provider-agnostic cell-value concern

**Complexity:** Low

**Files:**
- Create: `app/Services/Import/Concerns/ParsesStatementCells.php`
- Test: `tests/Unit/Services/Import/ParsesStatementCellsTest.php`

**Interfaces:**
- Consumes: none (pure).
- Produces (consumed by Task 4's parser and every future provider parser):
  - `App\Services\Import\Concerns\ParsesStatementCells` (trait) with public `parseDate(?string $value): ?CarbonImmutable`, public `parseTime(?string $value): ?CarbonImmutable`, public `parseAmount(string $value): ?float`, and protected hooks `statementDateFormats(): array` (defaults `['d/m/Y', 'd-m-Y']`) and `statementTimeFormats(): array` (defaults `['H:i:s', 'H:i']`). `parseTime` strips a trailing timezone word (`"12:49:41 WIB"` → `"12:49:41"`).
  - Deliberately **no row pairing or assembly** here: providers differ in row and column conventions, so those belong to each parser, composed from these primitives.

- [x] **Step 1: Write the failing unit tests**

`tests/Unit/Services/Import/ParsesStatementCellsTest.php` — pure value-in tests via anonymous classes, no app bootstrapping, `it()` declarations, named datasets:

```php
<?php

use App\Services\Import\Concerns\ParsesStatementCells;

/** A bare consumer of the concern — no provider, no layout. */
function statementCells(): object
{
    return new class
    {
        use ParsesStatementCells;
    };
}

/** A consumer overriding the per-provider date format hook. */
function statementCellsWithFormats(array $formats): object
{
    return new class($formats)
    {
        use ParsesStatementCells;

        public function __construct(private readonly array $formats) {}

        /** @return list<string> */
        protected function statementDateFormats(): array
        {
            return $this->formats;
        }
    };
}

/** A consumer overriding the per-provider time format hook. */
function statementCellsWithTimeFormats(array $formats): object
{
    return new class($formats)
    {
        use ParsesStatementCells;

        public function __construct(private readonly array $formats) {}

        /** @return list<string> */
        protected function statementTimeFormats(): array
        {
            return $this->formats;
        }
    };
}

it('parses id-ID amount cell variants', function (string $cell, ?float $expected): void {
    expect(statementCells()->parseAmount($cell))->toBe($expected);
})->with([
    'thousands, comma decimals' => ['2.500.000,00', 2500000.0],
    'dash-decimal tail'         => ['25.000,-', 25000.0],
    'rp-prefixed'               => ['Rp1.000.000', 1000000.0],
    'plain integer'             => ['350000', 350000.0],
    'negative'                  => ['-25.000', -25000.0],
    'lone dash is empty'        => ['-', null],
    'garbage is unreadable'     => ['N/A', null],
]);

it('parses dates against the default Indonesian formats', function (string $cell, ?string $expected): void {
    $date = statementCells()->parseDate($cell);

    expect($date?->toDateString())->toBe($expected);
})->with([
    'slash-separated' => ['31/10/2026', '2026-10-31'],
    'dash-separated'  => ['30-10-2026', '2026-10-30'],
    'month overflow'  => ['31/13/2026', null],
    'not a date'      => ['TOTAL', null],
    'empty'           => ['', null],
]);

it('normalizes dates to the start of day', function (): void {
    $date = statementCells()->parseDate('31/10/2026');

    expect($date?->toTimeString())->toBe('00:00:00');
});

it('honors a per-provider date format override', function (): void {
    $date = statementCellsWithFormats(['d M Y'])->parseDate('01 Sep 2026');

    expect($date?->toDateString())->toBe('2026-09-01');
});

it('parses time cells with optional timezone words', function (string $cell, ?string $expected): void {
    $time = statementCells()->parseTime($cell);

    expect($time?->toTimeString())->toBe($expected);
})->with([
    'hms'          => ['12:49:41', '12:49:41'],
    'hms with wib' => ['12:49:41 WIB', '12:49:41'],
    'hm'           => ['14:28', '14:28:00'],
    'not a time'   => ['Sep', null],
    'empty'        => ['', null],
]);

it('honors a per-provider time format override', function (): void {
    $time = statementCellsWithTimeFormats(['H.i.s'])->parseTime('12.49.41');

    expect($time?->toTimeString())->toBe('12:49:41');
});
```

- [x] **Step 2: Implement the concern**

`app/Services/Import/Concerns/ParsesStatementCells.php`:

```php
<?php

namespace App\Services\Import\Concerns;

use Carbon\CarbonImmutable;

/**
 * Generic cell-value parsing for statement imports, shared across
 * providers: id-ID amount normalization (the app is single-currency
 * IDR, so this is valid app-wide) and date/time parsing against
 * per-provider format lists. Value parsing only — row assembly and
 * column layout belong to each provider's parser, because providers
 * differ in column conventions (debit/kredit pairs, single signed
 * columns, direction markers, split date/time rows).
 */
trait ParsesStatementCells
{
    /**
     * Date formats this provider's export uses — override in the parser
     * when the layout differs. Defaults to the common Indonesian
     * statement formats.
     *
     * @return list<string>
     */
    protected function statementDateFormats(): array
    {
        return ['d/m/Y', 'd-m-Y'];
    }

    /**
     * Time formats this provider's export uses — override in the parser
     * when the layout differs. Defaults to the common statement formats.
     *
     * @return list<string>
     */
    protected function statementTimeFormats(): array
    {
        return ['H:i:s', 'H:i'];
    }

    public function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        foreach ($this->statementDateFormats() as $format) {
            // rawCreateFromFormat never throws on out-of-range values like
            // "31/13/2026" — it returns null. If the installed Carbon lacks
            // it, fall back to createFromFormat inside a try/catch (\Throwable)
            // returning null; the month-overflow test pins this behavior
            // either way.
            $date = CarbonImmutable::rawCreateFromFormat($format, trim($value));

            if ($date !== null) {
                return $date->startOfDay();
            }
        }

        return null;
    }

    public function parseTime(?string $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        // Strip a trailing timezone word — "12:49:41 WIB" → "12:49:41".
        $value = (string) preg_replace('/\s+[A-Za-z]+([+-]\d{1,2})?$/u', '', trim($value));

        if ($value === '') {
            return null;
        }

        foreach ($this->statementTimeFormats() as $format) {
            $time = CarbonImmutable::rawCreateFromFormat($format, $value);

            if ($time !== null) {
                return $time;
            }
        }

        return null;
    }

    /** id-ID amounts: "Rp1.000.000,00", "350.000,-", "-25.000". */
    public function parseAmount(string $value): ?float
    {
        $value = str_ireplace(['rp', ' ', "\u{A0}"], '', trim($value));

        if ($value === '' || $value === '-') {
            return null;
        }

        $value = str_replace(',-', ',', $value);
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
```

---

### Task 4: StatementFileReader + MandiriStatementParser — reader helper, pinned layout, row pairing, debit/kredit assembly

**Complexity:** Medium

**Files:**
- Create: `app/Services/Import/StatementFileReader.php`
- Create: `app/Services/Import/Parsers/MandiriStatementParser.php`
- Modify: `tests/Pest.php` (register the `module:import` group for the integration file)
- Test: `tests/Integration/Services/Import/MandiriStatementParserTest.php`

**Interfaces:**
- Consumes: Task 3's `parseDate`/`parseTime`/`parseAmount` (concern primitives), Task 1's contract and value objects, `Excel::toArray` (v4 API facts in Global Constraints), `import_fixture()` (Task 2).
- Produces:
  - `App\Services\Import\StatementFileReader::rows(UploadedFile $file, string $delimiter = ';', string $encoding = 'UTF-8'): array` — first sheet as `list<array<int, string|null>>`, typed numbers re-rendered id-ID. Deliberately format-flexible (CSV settings stay for future CSV providers); consumed by every provider parser.
  - `App\Services\Import\Parsers\MandiriStatementParser implements ProviderStatementParser` — `parse(UploadedFile $file): ParsedStatement`, its **only** public method. XLSX-only. Consumed by the staging plan's `ImportService::upload` (which must call `->parse($file)`, NOT `->parse($file->getContent())` — see Post-Plan Notes).
- **Pinned layout (verified against the real export):** 10 preamble rows (`DATA_START_ROW = 10`), then two rows per transaction — a **main row** carrying the sequence marker (`COLUMN_SEQUENCE = 0`, `"1,00"`), the date (`COLUMN_DATETIME = 4`, `d M Y` — Mandiri overrides `statementDateFormats()`), the description (`COLUMN_DESCRIPTION = 7`, whitespace-collapsed), credit (`COLUMN_CREDIT = 15`) and debit (`COLUMN_DEBIT = 18`); followed by a **companion row** carrying only the time in `COLUMN_DATETIME` (`H:i:s` + timezone word). A row without the sequence marker is only legal as a time companion. Time is optional enrichment: a missing or unreadable companion leaves the date at start of day and is never an error; stray companions are skipped. The staging plan assigns `row_number` as the 1-based position among assembled main rows, not the raw sheet row.

- [x] **Step 1: Write the failing integration tests**

`tests/Integration/Services/Import/MandiriStatementParserTest.php` — booted app (the `Excel` facade needs the container), direct class invocation, `it()` declarations. File-local helpers are plain functions with globally unique names:

```php
<?php

use App\Enums\Cashflow;
use App\Services\Import\ImportFileException;
use App\Services\Import\Parsers\MandiriStatementParser;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** The 10 preamble rows every fixture carries before the first transaction. */
function mandiriPreamble(): array
{
    return array_fill(0, 10, ['Mutasi Rekening Dummy']);
}

/** An uploaded statement built from a fixture's bytes. */
function mandiriUpload(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent('mutasi.xlsx', import_fixture($name));
}

/** A main transaction row at the pinned column indexes. */
function mandiriMainRow(string $sequence, string $date, string $description, ?string $credit = null, string|float|null $debit = null): array
{
    $row = array_fill(0, 19, null);
    $row[0] = $sequence;
    $row[4] = $date;
    $row[7] = $description;
    $row[15] = $credit;
    $row[18] = $debit;

    return $row;
}

/** A time-companion row carrying only the time in the date column. */
function mandiriTimeRow(string $time): array
{
    $row = array_fill(0, 5, null);
    $row[4] = $time;

    return $row;
}

/** An uploaded XLSX built on the fly: preamble + the given rows. */
function mandiriXlsxFromRows(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([...mandiriPreamble(), ...$rows], null, 'A1');

    $path = sys_get_temp_dir() . '/fintrack-mandiri-' . uniqid() . '.xlsx';
    IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

    return new UploadedFile($path, 'mutasi.xlsx', test: true);
}

it('parses a fixture XLSX upload end-to-end', function (): void {
    $statement = (new MandiriStatementParser)->parse(mandiriUpload('mandiri-basic.xlsx'));

    expect($statement->rows)->toHaveCount(3)
        ->and($statement->rows[0]->date?->toDateTimeString())->toBe('2026-10-31 12:49:41')
        ->and($statement->rows[0]->description)->toBe('TRSF E-BANKING CR BCA')
        ->and($statement->rows[0]->amount)->toBe(2500000.0)
        ->and($statement->rows[0]->flow)->toBe(Cashflow::Inflow)
        ->and($statement->rows[0]->errors)->toBe([]);
});

it('carries every row-level failure mode as field errors, never drops rows', function (): void {
    $statement = (new MandiriStatementParser)->parse(mandiriUpload('mandiri-dirty.xlsx'));

    expect($statement->rows)->toHaveCount(5)
        ->and($statement->rows[0]->errors)->toBe([])
        ->and($statement->rows[1]->date)->toBeNull()
        ->and($statement->rows[1]->errors)->toHaveKey('transaction_date')
        ->and($statement->rows[2]->amount)->toBeNull()
        ->and($statement->rows[2]->errors)->toHaveKey('amount')
        ->and($statement->rows[3]->errors)->toHaveKey('amount') // both columns set
        ->and($statement->rows[4]->errors)->toHaveKey('amount') // negative
        ->and($statement->rows[4]->flow)->toBeNull();
});

it('rejects an unrecognized fixture as a file-level failure', function (): void {
    (new MandiriStatementParser)->parse(mandiriUpload('mandiri-unrecognized.xlsx'));
})->throws(ImportFileException::class, 'The statement does not match the expected Mandiri layout.');

it('throws on a preamble-only statement', function (): void {
    (new MandiriStatementParser)->parse(mandiriXlsxFromRows([]));
})->throws(ImportFileException::class, 'The statement file is empty or has no data rows.');

it('skips blank rows without shifting pairing or the data start', function (): void {
    $statement = (new MandiriStatementParser)->parse(mandiriXlsxFromRows([
        array_fill(0, 19, null),
        mandiriMainRow('1,00', '31 Oct 2026', 'TRSF E-BANKING CR BCA', credit: '2.500.000,00'),
        array_fill(0, 19, null),
        mandiriTimeRow('12:49:41 WIB'),
        array_fill(0, 19, null),
    ]));

    expect($statement->rows)->toHaveCount(1)
        ->and($statement->rows[0]->date?->toDateTimeString())->toBe('2026-10-31 12:49:41')
        ->and($statement->rows[0]->errors)->toBe([]);
});

it('keeps the date at start of day when the time companion is missing', function (): void {
    $statement = (new MandiriStatementParser)->parse(mandiriXlsxFromRows([
        mandiriMainRow('1,00', '31 Oct 2026', 'TRSF E-BANKING CR BCA', credit: '2.500.000,00'),
    ]));

    expect($statement->rows)->toHaveCount(1)
        ->and($statement->rows[0]->date?->toDateTimeString())->toBe('2026-10-31 00:00:00')
        ->and($statement->rows[0]->errors)->toBe([]);
});

it('skips stray companion rows', function (): void {
    $statement = (new MandiriStatementParser)->parse(mandiriXlsxFromRows([
        mandiriMainRow('1,00', '31 Oct 2026', 'TRSF E-BANKING CR BCA', credit: '2.500.000,00'),
        mandiriTimeRow('12:49:41 WIB'),
        mandiriTimeRow('23:59:59 WIB'),
        mandiriMainRow('2,00', '30 Oct 2026', 'BUNGA TABUNGAN', credit: '25.000,00'),
        mandiriTimeRow('08:42:25 WIB'),
    ]));

    expect($statement->rows)->toHaveCount(2)
        ->and($statement->rows[0]->date?->toDateTimeString())->toBe('2026-10-31 12:49:41')
        ->and($statement->rows[1]->date?->toDateTimeString())->toBe('2026-10-30 08:42:25');
});

it('collapses multi-line descriptions to single-spaced text', function (): void {
    $statement = (new MandiriStatementParser)->parse(mandiriXlsxFromRows([
        mandiriMainRow('1,00', '31 Oct 2026', "Pembayaran QR\nke Khas Solo\n609015160267", credit: '40.000,00'),
        mandiriTimeRow('12:49:41 WIB'),
    ]));

    expect($statement->rows[0]->description)->toBe('Pembayaran QR ke Khas Solo 609015160267')
        ->and($statement->rows[0]->errors)->toBe([]);
});

it('re-renders typed XLSX number cells without losing decimals', function (): void {
    $statement = (new MandiriStatementParser)->parse(mandiriXlsxFromRows([
        mandiriMainRow('1,00', '30 Oct 2026', 'PEMBAYARAN LISTRIK PLN', debit: 350000.5),
        mandiriTimeRow('14:28:28 WIB'),
    ]));

    expect($statement->rows)->toHaveCount(1)
        ->and($statement->rows[0]->amount)->toBe(350000.5)
        ->and($statement->rows[0]->flow)->toBe(Cashflow::Outflow)
        ->and($statement->rows[0]->errors)->toBe([]);
});
```

Then register the module group in `tests/Pest.php` — integration files map per file, next to the existing per-file entries:

```php
pest()->group('module:import')->in('Integration/Services/Import/MandiriStatementParserTest.php');
```

- [x] **Step 2: Create the file reader helper**

No dedicated test — reading glue, covered end-to-end by Step 1's suite (fixture XLSX files and on-the-fly workbooks all go through it). `app/Services/Import/StatementFileReader.php` — format-flexible by design; the CSV settings exist for future CSV providers (XLSX ignores them), while the app's upload surface is XLSX-only:

```php
<?php

namespace App\Services\Import;

use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The single file-reading helper for statement imports: every file
 * mechanic is delegated to laravel-excel (format detection, delimiter,
 * quotes/enclosures, BOM, input encoding). Parsers hand in their
 * pinned CSV settings and receive normalized string cell rows; they
 * never touch the file themselves.
 */
final class StatementFileReader
{
    /**
     * The upload's first sheet as string cell rows. Typed number cells
     * (native XLSX floats) are re-rendered in the id-ID convention the
     * cell parsers expect ("350000,50") so decimals survive the string
     * round-trip; CSV cells are strings already and pass through.
     *
     * @return list<array<int, string|null>>
     */
    public static function rows(UploadedFile $file, string $delimiter = ';', string $encoding = 'UTF-8'): array
    {
        $sheets = Excel::toArray(new class($delimiter, $encoding) implements ToArray, WithCustomCsvSettings
        {
            public function __construct(
                private readonly string $delimiter,
                private readonly string $encoding,
            ) {}

            /**
             * No-op receiver — Excel::toArray() returns the sheet data itself.
             *
             * @param  array<array-key, mixed>  $array
             */
            public function array(array $array): void {}

            /** @return array{delimiter: string, input_encoding: string} */
            public function getCsvSettings(): array
            {
                return ['delimiter' => $this->delimiter, 'input_encoding' => $this->encoding];
            }
        }, $file);

        /** @var array<int, array<int, mixed>> $sheet */
        $sheet = $sheets[0] ?? [];

        return array_map(
            fn (array $row): array => array_map(self::normalizeCell(...), $row),
            $sheet
        );
    }

    private static function normalizeCell(mixed $cell): ?string
    {
        return match (true) {
            $cell === null => null,
            is_int($cell), is_float($cell) => number_format((float) $cell, 2, ',', ''),
            default => (string) $cell,
        };
    }
}
```

- [x] **Step 3: Implement the parser**

`app/Services/Import/Parsers/MandiriStatementParser.php` — pinned layout verified against the real export, XLSX-only (no CSV delimiter pin; the reader's defaults stand). `statementDateFormats()` overrides to `['d M Y']`; `statementTimeFormats()` default (`H:i:s`, `H:i`) matches the export, so no override.

```php
<?php

namespace App\Services\Import\Parsers;

use App\Enums\Cashflow;
use App\Services\Import\Concerns\ParsesStatementCells;
use App\Services\Import\Contracts\ProviderStatementParser;
use App\Services\Import\ImportFileException;
use App\Services\Import\ParsedRow;
use App\Services\Import\ParsedStatement;
use App\Services\Import\StatementFileReader;
use Illuminate\Http\UploadedFile;

/**
 * Bank Mandiri Mutasi Rekening XLSX — pinned layout, verified against
 * the real export. Each transaction spans TWO rows: a
 * main row (sequence number in COLUMN_SEQUENCE, date, description,
 * debit/credit, resulting saldo) followed by a companion row carrying
 * only the time, in the same column as the date. Column 0's sequence
 * marker ("1,00", "2,00", …) is what distinguishes main rows from
 * companions. Data begins at DATA_START_ROW among non-blank rows — the
 * export opens with an account-info preamble block. Mandiri's money
 * convention is the debit/kredit column pair — Kredit = money in,
 * Debit = money out; descriptions are whitespace-collapsed to
 * single-spaced text. Reading is delegated to the format-flexible
 * StatementFileReader; cell value parsing comes from the
 * ParsesStatementCells concern; row pairing and assembly belong to
 * this parser. A file where no row matches the pinned layout is
 * rejected at file level; row-level problems surface as ParsedRow
 * errors.
 */
final class MandiriStatementParser implements ProviderStatementParser
{
    use ParsesStatementCells;

    /** Pinned rows: 0-based index of the first transaction row among non-blank rows — the export opens with an account-info preamble block. */
    private const int DATA_START_ROW = 10;

    /** Pinned columns, 0-based. */
    private const int COLUMN_SEQUENCE = 0;

    private const int COLUMN_DATETIME = 4;

    private const int COLUMN_DESCRIPTION = 7;

    private const int COLUMN_CREDIT = 15;

    private const int COLUMN_DEBIT = 18;

    /** @return list<string> */
    protected function statementDateFormats(): array
    {
        return ['d M Y'];
    }

    public function parse(UploadedFile $file): ParsedStatement
    {
        return $this->toStatement(StatementFileReader::rows($file));
    }

    /**
     * Layout pipeline over cell rows: drop blank rows, take data from
     * DATA_START_ROW onward, pair each main row with its time
     * companion, assemble at the pinned column indexes, and guard the
     * two failure tiers.
     *
     * @param  list<array<int, string|null>>  $rows
     */
    private function toStatement(array $rows): ParsedStatement
    {
        // Prune empty lines
        $rows = array_values(array_filter(
            $rows,
            fn (array $row): bool => array_filter($row, fn (?string $cell): bool => trim((string) $cell) !== '') !== []
        ));

        if (count($rows) <= self::DATA_START_ROW) {
            throw new ImportFileException('The statement file is empty or has no data rows.');
        }

        $rows = array_slice($rows, self::DATA_START_ROW);
        $count = count($rows);

        $parsed = [];
        for ($i = 0; $i < $count; $i++) {
            $main = $rows[$i];

            if ($this->isCompanionRow($main)) {
                continue; // stray time row — pairing already consumed the legal ones
            }

            $companion = $i + 1 < $count && $this->isCompanionRow($rows[$i + 1])
                ? $rows[++$i]
                : null;

            $parsed[] = $this->assembleRow($main, $companion);
        }

        if (array_filter($parsed, fn (ParsedRow $row): bool => $row->errors === []) === []) {
            throw new ImportFileException('The statement does not match the expected Mandiri layout.');
        }

        return new ParsedStatement($parsed);
    }

    /**
     * A row without the sequence-number marker — legal only as the time
     * companion of the main row above it.
     *
     * @param  array<int, string|null>  $row
     */
    private function isCompanionRow(array $row): bool
    {
        return trim((string) ($row[self::COLUMN_SEQUENCE] ?? '')) === '';
    }

    /**
     * Mandiri's row assembly — the debit/kredit two-column convention at
     * the pinned column indexes, with the companion row's time folded
     * into the main row's date. Time is optional enrichment: a missing
     * or unreadable companion leaves the date at start of day and is
     * never an error. Descriptions collapse to single-spaced text.
     *
     * @param  array<int, string|null>  $main
     * @param  array<int, string|null>|null  $companion
     */
    private function assembleRow(array $main, ?array $companion): ParsedRow
    {
        $date = $this->parseDate($main[self::COLUMN_DATETIME] ?? null);
        $time = $companion === null ? null : $this->parseTime($companion[self::COLUMN_DATETIME] ?? null);

        if ($date !== null && $time !== null) {
            $date = $date->setTimeFromTimeString($time->toTimeString());
        }

        $description = isset($main[self::COLUMN_DESCRIPTION]) && $main[self::COLUMN_DESCRIPTION] !== null
            ? trim((string) preg_replace('/\s+/u', ' ', $main[self::COLUMN_DESCRIPTION]))
            : null;
        $debit = $this->parseAmount((string) ($main[self::COLUMN_DEBIT] ?? ''));
        $credit = $this->parseAmount((string) ($main[self::COLUMN_CREDIT] ?? ''));

        $errors = [];
        if ($date === null) {
            $errors['transaction_date'] = 'Unrecognized date format.';
        }

        if ($debit === null && $credit === null) {
            $errors['amount'] = 'No amount column has a readable value.';
        } elseif ($debit !== null && $debit > 0 && $credit !== null && $credit > 0) {
            $errors['amount'] = 'Both debit and credit are set.';
        } elseif (($debit !== null && $debit < 0) || ($credit !== null && $credit < 0)) {
            $errors['amount'] = 'Amounts cannot be negative.';
        }

        $amount = $credit ?? $debit;
        $flow = match (true) {
            $credit !== null && $credit > 0 => Cashflow::Inflow,
            $debit !== null && $debit > 0 => Cashflow::Outflow,
            default => null,
        };

        return new ParsedRow($date, $description, $amount, $flow, $main, $errors);
    }
}
```

---

### Task 5: Parser registry

**Complexity:** Low

**Files:**
- Create: `app/Services/Import/ParserRegistry.php`

No test — trivial glue (a slug→class map with two delegating methods). It is exercised end-to-end by the staging plan's `ImportServiceTest` (via `resolve()`) and `ImportTest` (via `supports()`).

**Interfaces:**
- Consumes: `App\Models\Provider` (existing), `MandiriStatementParser` (Task 4).
- Produces (consumed by the staging plan):
  - `App\Services\Import\ParserRegistry::supports(?Provider $provider): bool`
  - `App\Services\Import\ParserRegistry::resolve(Provider $provider): ProviderStatementParser` (aborts 422 when unsupported)

- [x] **Step 1: Create the registry**

```php
<?php

namespace App\Services\Import;

use App\Models\Provider;
use App\Services\Import\Contracts\ProviderStatementParser;

/** Provider slug → built-in parser. Adding a provider = one class + one entry. */
final readonly class ParserRegistry
{
    /** @var array<string, class-string<ProviderStatementParser>> */
    private const array PARSERS = [
        'mandiri' => Parsers\MandiriStatementParser::class,
    ];

    public function supports(?Provider $provider): bool
    {
        return $provider !== null && isset(self::PARSERS[$provider->slug]);
    }

    public function resolve(Provider $provider): ProviderStatementParser
    {
        $parser = self::PARSERS[$provider->slug] ?? null;

        abort_if($parser === null, 422, 'Statement import is not available for this provider yet.');

        return new $parser();
    }
}
```

---

### Task 6: Dev-only parser debug endpoint

**Complexity:** Low

**Files:**
- Modify: `routes/dev.php`

No test — dev-sandbox tooling inside the `OnlyDevelopment` group (not registered outside `local`, never hit by CI), verified manually against the dummy statement. Closures with logic are sanctioned here by `.ai/rules/routes.md` ("dev-sandbox pages").

**Interfaces:**
- Consumes: `StatementFileReader::rows()`, `MandiriStatementParser::parse()` (Task 4).
- Produces: `GET /dev/import` — reads the newest `statement-dummy.*` dropped in `storage/app/private` (no params; optional `?file=statement-dummy.xlsx` picks a specific file in that directory, basename-guarded). Returns JSON: the raw normalized first rows (eyeball `DATA_START_ROW`/`COLUMN_*` pins), the parsed rows with per-row errors and full datetimes, and a clean/error summary — the layout-verification loop from Post-Plan Notes, without tinker or the app's import flow.

- [x] **Step 1: Add the route**

Append inside the `dev` group in `routes/dev.php` (imports to add: `App\Services\Import\ImportFileException`, `App\Services\Import\Parsers\MandiriStatementParser`, `App\Services\Import\ParsedRow`, `App\Services\Import\StatementFileReader`, `Illuminate\Http\Request`, `Illuminate\Http\UploadedFile`):

```php
    // Statement parser debug: reads the dummy statement dropped in storage/app/private
    // (newest statement-dummy.* — no params needed; optional ?file=statement-dummy.xlsx
    // picks a specific file in that directory). Dumps the raw normalized rows (to
    // eyeball pinned layout constants) plus the parsed statement, so a real export
    // can be verified without touching the app's import flow.
    Route::get('import', function (Request $request) {
        $directory = storage_path('app/private');
        $requested = basename((string) $request->query('file', ''));

        $candidates = glob($directory . '/' . ($requested !== '' ? $requested : 'statement-dummy.*')) ?: [];

        if ($candidates === []) {
            abort(422, "No statement file found in {$directory} — drop statement-dummy.xlsx there.");
        }

        usort($candidates, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        $path = $candidates[0];

        $file = new UploadedFile($path, basename($path));
        $raw = StatementFileReader::rows($file);

        $response = [
            'file' => ['name' => basename($path), 'rows_read' => count($raw)],
            'raw_first' => array_slice($raw, 0, (int) $request->query('raw_rows', 10)),
        ];

        try {
            $statement = (new MandiriStatementParser)->parse($file);
        } catch (ImportFileException $exception) {
            return response()->json([...$response, 'error' => $exception->getMessage()]);
        }

        $errorCount = count(array_filter($statement->rows, fn (ParsedRow $row): bool => $row->errors !== []));

        return response()->json([...$response,
            'summary' => [
                'rows' => count($statement->rows),
                'clean' => count($statement->rows) - $errorCount,
                'with_errors' => $errorCount,
            ],
            'parsed' => array_map(fn (ParsedRow $row): array => [
                'date' => $row->date?->toDateTimeString(),
                'amount' => $row->amount,
                'flow' => $row->flow?->value,
                'description' => $row->description,
                'errors' => (object) $row->errors,
            ], array_slice($statement->rows, 0, (int) $request->query('rows', 50))),
        ]);
    })->name('import');
```

---

## Post-Plan Notes (not tasks)

### Companion plan deltas (apply to the unexecuted staging + frontend plans before running them)

- **Staging plan Task 3** (`ImportService::upload`): the call site is `$this->registry->resolve($provider)->parse($file->getContent())` — change to `->parse($file)` (the `UploadedFile` itself). Nothing else in the service changes. The `statementFile()` helper builds its upload named `mutasi.xlsx` with default `mandiri-basic.xlsx` (not `mutasi.csv` / `mandiri-basic.csv`).
- **Staging plan Task 6** (`ImportUploadRequest::rules`): `'mimes:csv,txt'` → `'mimes:xlsx'` (XLSX-only surface).
- **Frontend plan Task 1** (`import-upload-form.svelte`): `accept=".csv,text/csv,text/plain"` → `accept=".xlsx"`, and the upload-page hint copy should say "XLSX export".
- Both companion plans' PREREQUISITE blocks reference the superseded `2026-10-30-provider-csv-import-parsing.md` — this plan (`2026-11-04-provider-csv-import-parsing-excel.md`) is the one to execute. Everything the staging plan consumes is produced unchanged here: `ParserRegistry::supports/resolve`, `ParsedStatement`/`ParsedRow`, `ImportFileException`, `import_fixture()`, and the `tests/Fixtures/Imports/` fixtures. Only the contract's input type changed (string → `UploadedFile`), which affects the single call site above.

### Layout verification status & remaining debt

- **Mandiri layout is verified** against the real export (`storage/app/private/statement-dummy.xlsx` + the sliced `statement-dummy.json`): 10 preamble rows, two-rows-per-transaction with the sequence marker at column 0 distinguishing main from companion, date/time at column 4 (`d M Y` / `H:i:s WIB`), description at 7, credit at 15, debit at 18, saldo at 21 (documented, not consumed). Dates and times arrive as **strings** in the real export; typed **numbers** are re-rendered defensively by the reader.
- **XLSX-only surface, flexible reader**: uploads are validated `mimes:xlsx` and Mandiri's parser pins no CSV settings, but `StatementFileReader` deliberately keeps its CSV settings params so a future CSV provider reuses it unchanged.
- **Duplicate-rows edge**: two identical transactions on the same statement parse as two rows — the staging plan's within-file fingerprint flag marks the second as duplicate at upload time. No parser-side dedup, by design.
- **Input encoding**: CSV settings declare `input_encoding: 'UTF-8'` per import. If a real CSV export ever arrives in a different encoding, change that one setting.
- **Verification debt**: none for layout. The staging plan's own deltas (above) remain to be applied before it executes.
- **Supersession**: `2026-10-30-provider-csv-import-parsing.md` (native parser) is superseded by this file. Do not execute it; keep it for reference or delete it once this plan lands.
