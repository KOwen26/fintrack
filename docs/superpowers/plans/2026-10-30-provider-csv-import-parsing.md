# Provider CSV Import — Parsing Package Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use better-executing-plans to implement this plan task-by-task — or better-parallel-subagents-executions if this plan splits into independent slices, or superpowers:subagent-driven-development if workflow mode is `remote`. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The pure, provider-scoped statement-parsing layer: a parser contract, value objects, the pinned-layout Mandiri CSV parser, and the slug-keyed registry that maps providers to parsers.

**Architecture:** Everything in this plan lives under `app/Services/Import/` and touches no database, no HTTP, and no Laravel app bootstrapping — parsers are pure translators from `string $contents` to `ParsedStatement`, unit-tested against fixture files. The wrong-file guard is positional: zero rows parsing against the pinned layout is a file-level exception; row-level problems stage as field errors. Spec: `docs/superpowers/specs/2026-10-30-provider-csv-import-design.md`. Companion plans (both depend on this one): `2026-10-30-provider-csv-import-staging.md`, `2026-10-30-provider-csv-import-frontend.md`.

**Tech Stack:** PHP 8.4, Carbon/CarbonImmutable, Pest (Unit tests — no Laravel app).

**Workflow Mode:** `direct` — tests are written as part of each task, but no task runs/verifies/commits. The human reviewer handles test runs, formatting, and commits as they choose. (Project rules still apply while implementing: run `vendor/bin/pint --dirty --format agent` after PHP edits.)

**Complexity:** Medium (all steps concrete; no exploratory work)

## Global Constraints

- PHP 8.4 style: `final` classes, `readonly` where possible, explicit return types, curly braces on all control structures, PHPDoc over inline comments.
- **This package is pure**: no Eloquent, no `app()` calls, no facades. The single sanctioned exception is `ParserRegistry::resolve()`'s `abort_if` guard (422), which the companion staging plan relies on.
- Parsers declare their provider's **pinned layout** as private constants — delimiter, skipped preamble rows (`HEADER_ROWS`), and column indexes. No header matching, no delimiter sniffing.
- Two failure tiers, always: file-level unreadable layout → throw `ImportFileException`; row-level problems → `ParsedRow::$errors`, never dropped rows.
- Unit tests live in `tests/Unit/` (no Laravel TestCase binding there — keep it that way), fixtures in `tests/Fixtures/Imports/`.

## File Structure

- Create: `app/Services/Import/Contracts/ProviderCsvParser.php` — the per-provider parser interface.
- Create: `app/Services/Import/ParsedRow.php`, `app/Services/Import/ParsedStatement.php` — parse result value objects.
- Create: `app/Services/Import/ImportFileException.php` — file-level failure.
- Create: `app/Services/Import/Parsers/MandiriCsvParser.php` — the reference pinned parser.
- Create: `app/Services/Import/ParserRegistry.php` — provider slug → parser map.
- Create: `tests/Fixtures/Imports/mandiri-basic.csv`, `mandiri-dirty.csv`, `mandiri-unrecognized.csv`
- Create: `tests/Unit/Services/Import/MandiriCsvParserTest.php`

**Optional alternative (Task 3):** `app/Services/Import/Concerns/ParsesStatementCells.php` (generic cell-parsing trait — id-ID amounts, debit/kredit assembly, overridable date formats — extracted from the native parser), `app/Services/Import/Parsers/MandiriExcelParser.php` (laravel-excel-backed), plus `tests/Unit/Services/Import/MandiriExcelParserTest.php` and the `maatwebsite/excel` dependency.

---

### Task 1: Parser contract, value objects, Mandiri parser, fixtures

**Complexity:** Medium

**Files:**
- Create: `app/Services/Import/Contracts/ProviderCsvParser.php`
- Create: `app/Services/Import/ParsedRow.php`
- Create: `app/Services/Import/ParsedStatement.php`
- Create: `app/Services/Import/ImportFileException.php`
- Create: `app/Services/Import/Parsers/MandiriCsvParser.php`
- Create: `tests/Fixtures/Imports/mandiri-basic.csv`
- Create: `tests/Fixtures/Imports/mandiri-dirty.csv`
- Create: `tests/Fixtures/Imports/mandiri-unrecognized.csv`
- Test: `tests/Unit/Services/Import/MandiriCsvParserTest.php`

**Interfaces:**
- Consumes: `App\Enums\Cashflow` (existing).
- Produces (consumed by the staging plan):
  - `App\Services\Import\Contracts\ProviderCsvParser::parse(string $contents): ParsedStatement`
  - `App\Services\Import\ParsedStatement` — `public readonly array $rows` (`list<ParsedRow>`).
  - `App\Services\Import\ParsedRow` — `public readonly ?CarbonImmutable $date`, `public readonly ?string $description`, `public readonly ?float $amount`, `public readonly ?Cashflow $flow`, `public readonly array $raw` (`array<int, string|null>`), `public readonly array $errors` (`array<string, string>`).
  - `App\Services\Import\ImportFileException extends RuntimeException`.

- [ ] **Step 1: Write the failing parser tests + fixtures**

Create `tests/Fixtures/Imports/mandiri-basic.csv` (the pinned layout: semicolon-delimited, 1 header row, id-ID amounts):

```csv
Tanggal;Keterangan;Cabang;Tipe;Debit;Kredit;Saldo
31/10/2026;TRSF E-BANKING CR BCA;JAKARTA;CR;;2.500.000,00;5.000.000,00
30/10/2026;PEMBAYARAN LISTRIK PLN;JAKARTA;DB;350.000,00;;2.500.000,00
29/10/2026;BUNGA TABUNGAN;JAKARTA;CR;;25.000,-;2.850.000,00
```

Create `tests/Fixtures/Imports/mandiri-dirty.csv` (same pinned layout, with a bad date and an empty-amount row):

```csv
Tanggal;Keterangan;Cabang;Tipe;Debit;Kredit;Saldo
31/10/2026;TRSF E-BANKING DB GOPAY;JAKARTA;DB;50.000,00;;50.000,00
31/13/2026;BAD DATE ROW;JAKARTA;DB;10.000,00;;60.000,00
30/10/2026;NO AMOUNT ROW;JAKARTA;DB;;;70.000,00
```

Create `tests/Fixtures/Imports/mandiri-unrecognized.csv`:

```csv
foo;bar;baz
1;2;3
```

Create `tests/Unit/Services/Import/MandiriCsvParserTest.php` (Unit — no Laravel app needed):

```php
<?php

use App\Enums\Cashflow;
use App\Services\Import\ImportFileException;
use App\Services\Import\Parsers\MandiriCsvParser;

function fixture(string $name): string
{
    return file_get_contents(dirname(__DIR__, 3) . '/Fixtures/Imports/' . $name);
}

test('parses a well-formed statement by pinned columns', function (): void {
    $statement = (new MandiriCsvParser())->parse(fixture('mandiri-basic.csv'));

    expect($statement->rows)->toHaveCount(3);

    [$first, $second, $third] = $statement->rows;

    expect($first->date?->toDateString())->toBe('2026-10-31')
        ->and($first->description)->toBe('TRSF E-BANKING CR BCA')
        ->and($first->amount)->toBe(2500000.0)
        ->and($first->flow)->toBe(Cashflow::Inflow)
        ->and($first->errors)->toBe([])
        ->and($second->flow)->toBe(Cashflow::Outflow)
        ->and($second->amount)->toBe(350000.0)
        ->and($third->amount)->toBe(25000.0); // "25.000,-" tail handled
});

test('stages rows with field errors instead of dropping them', function (): void {
    $statement = (new MandiriCsvParser())->parse(fixture('mandiri-dirty.csv'));

    expect($statement->rows)->toHaveCount(3)
        ->and($statement->rows[0]->errors)->toBe([])
        ->and($statement->rows[1]->date)->toBeNull()
        ->and($statement->rows[1]->errors)->toHaveKey('transaction_date')
        ->and($statement->rows[2]->amount)->toBeNull()
        ->and($statement->rows[2]->errors)->toHaveKey('amount');
});

test('ignores a UTF-8 BOM ahead of the header row', function (): void {
    $statement = (new MandiriCsvParser())->parse("\u{FEFF}" . fixture('mandiri-basic.csv'));

    expect($statement->rows)->toHaveCount(3)
        ->and($statement->rows[0]->errors)->toBe([]);
});

test('throws when no row matches the pinned layout', function (): void {
    (new MandiriCsvParser())->parse(fixture('mandiri-unrecognized.csv'));
})->throws(ImportFileException::class, 'The statement does not match the expected Mandiri layout.');

test('throws on a header-only statement', function (): void {
    (new MandiriCsvParser())->parse("Tanggal;Keterangan;Cabang;Tipe;Debit;Kredit;Saldo\n");
})->throws(ImportFileException::class, 'The statement file is empty or has no data rows.');
```

- [ ] **Step 2: Create the contract, value objects, and exception**

`app/Services/Import/Contracts/ProviderCsvParser.php`:

```php
<?php

namespace App\Services\Import\Contracts;

use App\Services\Import\ParsedStatement;

/**
 * One provider's statement CSV layout. Implementations pin the layout:
 * delimiter, skipped preamble rows, and column indexes, plus cell-level
 * locale parsing. File-level structure failures throw ImportFileException;
 * row-level problems surface as ParsedRow::errors.
 */
interface ProviderCsvParser
{
    public function parse(string $contents): ParsedStatement;
}
```

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
     * @param  array<int, string|null>  $raw     original CSV columns
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

`app/Services/Import/ImportFileException.php`:

```php
<?php

namespace App\Services\Import;

use RuntimeException;

/** File-level parse failure — the layout itself is unreadable, no batch is created. */
final class ImportFileException extends RuntimeException {}
```

- [ ] **Step 3: Implement the Mandiri parser**

`app/Services/Import/Parsers/MandiriCsvParser.php` — pinned layout (placeholder until a real export is verified): both dimensions are fixed per provider — which rows carry data (`HEADER_ROWS` preamble lines are skipped) and which column index maps to which field. No header matching, no delimiter sniffing. A file where **no** row parses against the pinned layout is rejected at file level. Cell-level locale parsing (`d/m/Y` dates, id-ID amounts) still applies.

```php
<?php

namespace App\Services\Import\Parsers;

use App\Enums\Cashflow;
use App\Services\Import\Contracts\ProviderCsvParser;
use App\Services\Import\ImportFileException;
use App\Services\Import\ParsedRow;
use App\Services\Import\ParsedStatement;
use Carbon\CarbonImmutable;

/**
 * Bank Mandiri Mutasi Rekening CSV — pinned layout (placeholder until a
 * real export is verified). Data begins after HEADER_ROWS preamble
 * lines; field positions are fixed by column index. Kredit = money in,
 * Debit = money out. A file where no row matches the pinned layout is
 * rejected at file level; row-level problems surface as ParsedRow errors.
 */
final class MandiriCsvParser implements ProviderCsvParser
{
    private const string DELIMITER = ';';

    /** Pinned rows: data begins after this many non-empty lines. */
    private const int HEADER_ROWS = 1;

    /** Pinned columns, 0-based. */
    private const int COLUMN_DATE = 0;
    private const int COLUMN_DESCRIPTION = 1;
    private const int COLUMN_DEBIT = 4;
    private const int COLUMN_CREDIT = 5;

    private const array DATE_FORMATS = ['d/m/Y', 'd-m-Y'];

    public function parse(string $contents): ParsedStatement
    {
        // Excel CSV exports often open with a UTF-8 BOM.
        $contents = str_replace("\u{FEFF}", '', $contents);

        $lines = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', trim($contents)) ?: [],
            fn (string $line): bool => trim($line) !== ''
        ));

        if (count($lines) <= self::HEADER_ROWS) {
            throw new ImportFileException('The statement file is empty or has no data rows.');
        }

        $rows = [];
        foreach (array_slice($lines, self::HEADER_ROWS) as $line) {
            $rows[] = $this->parseRow(str_getcsv($line, self::DELIMITER) ?: []);
        }

        $usable = array_filter($rows, fn (ParsedRow $row): bool => $row->errors === []);

        if ($usable === []) {
            throw new ImportFileException('The statement does not match the expected Mandiri layout.');
        }

        return new ParsedStatement($rows);
    }

    /** @param array<int, string|null> $columns */
    private function parseRow(array $columns): ParsedRow
    {
        $date = $this->parseDate($columns[self::COLUMN_DATE] ?? null);
        $description = isset($columns[self::COLUMN_DESCRIPTION]) && $columns[self::COLUMN_DESCRIPTION] !== null
            ? trim((string) $columns[self::COLUMN_DESCRIPTION])
            : null;
        $debit = $this->parseAmount((string) ($columns[self::COLUMN_DEBIT] ?? ''));
        $credit = $this->parseAmount((string) ($columns[self::COLUMN_CREDIT] ?? ''));

        $errors = [];
        if ($date === null) {
            $errors['transaction_date'] = 'Unrecognized date format.';
        }

        if ($debit === null && $credit === null) {
            $errors['amount'] = 'No amount column has a readable value.';
        } elseif ($debit !== null && $debit > 0 && $credit !== null && $credit > 0) {
            $errors['amount'] = 'Both debit and credit are set.';
        }

        $amount = $credit ?? $debit;
        $flow = match (true) {
            $credit !== null && $credit > 0 => Cashflow::Inflow,
            $debit !== null && $debit > 0 => Cashflow::Outflow,
            default => null,
        };

        return new ParsedRow($date, $description, $amount, $flow, $columns, $errors);
    }

    private function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        foreach (self::DATE_FORMATS as $format) {
            if (CarbonImmutable::rawCreateFromFormat($format, trim($value)) !== null) {
                return CarbonImmutable::rawCreateFromFormat($format, trim($value))->startOfDay();
            }
        }

        return null;
    }

    /** id-ID amounts: "Rp1.000.000,00", "350.000,-", "-25.000". */
    private function parseAmount(string $value): ?float
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

(If this Carbon version's `createFromFormat` throws on out-of-range values like `31/13/2026` instead of returning false — Carbon 3 changed some failure modes — wrap the attempts in a `try/catch (\Throwable)` inside `parseDate` and return `null`; the dirty-row test pins this behavior either way. `rawCreateFromFormat` never throws, which is why it is used above; if it is unavailable in the installed Carbon, fall back to `createFromFormat` inside the try/catch.)

---

### Task 2: Parser registry

**Complexity:** Low

**Files:**
- Create: `app/Services/Import/ParserRegistry.php`

No test — trivial glue (a slug→class map with two delegating methods). It is exercised end-to-end by the staging plan's `ImportServiceTest` (via `resolve()`) and `ImportTest` (via `supports()`).

**Interfaces:**
- Consumes: `App\Models\Provider` (existing), `MandiriCsvParser` (Task 1).
- Produces (consumed by the staging plan):
  - `App\Services\Import\ParserRegistry::supports(?Provider $provider): bool`
  - `App\Services\Import\ParserRegistry::resolve(Provider $provider): ProviderCsvParser` (aborts 422 when unsupported)

- [ ] **Step 1: Create the registry**

```php
<?php

namespace App\Services\Import;

use App\Models\Provider;
use App\Services\Import\Contracts\ProviderCsvParser;

/** Provider slug → built-in parser. Adding a provider = one class + one entry. */
final readonly class ParserRegistry
{
    /** @var array<string, class-string<ProviderCsvParser>> */
    private const array PARSERS = [
        'mandiri' => Parsers\MandiriCsvParser::class,
    ];

    public function supports(?Provider $provider): bool
    {
        return $provider !== null && isset(self::PARSERS[$provider->slug]);
    }

    public function resolve(Provider $provider): ProviderCsvParser
    {
        $parser = self::PARSERS[$provider->slug] ?? null;

        abort_if($parser === null, 422, 'Statement import is not available for this provider yet.');

        return new $parser();
    }
}
```

---

### Task 3 (OPTIONAL alternative): laravel-excel-backed Mandiri parser

> **Take this task only when adopting `maatwebsite/excel`** (explicitly approved by the project owner). Until then the native pinned parser (Task 1) is the default and this task is skipped. The variant satisfies the same `ProviderCsvParser` contract — the registry entry for `mandiri` is a **swap**, never a second entry for the same slug. Bonus capability: because PhpSpreadsheet reads both formats, this parser accepts CSV **and XLSX** bytes (a zip `PK` header is sniffed to pick the temp-file extension).

**Complexity:** Medium

**Version gate (do this first):** run `composer show maatwebsite/excel` after install and check the installed major. The code below uses the 3.1 concern API (`ToArray`, `WithCustomCsvSettings`, `Excel::toArray`). If the installed major differs, look up the current reading API (laravel-boost `search-docs` scoped to the package, or Context7) and adjust the import concern names — the structure (temp file → sheets → skip preamble → assemble rows → zero-usable guard) stays identical.

**Files:**
- Modify: `composer.json` (+ `maatwebsite/excel`)
- Create: `app/Services/Import/Concerns/ParsesStatementCells.php`
- Modify: `app/Services/Import/Parsers/MandiriCsvParser.php` (extract cell parsing into the trait; behavior unchanged — Task 1's tests must stay green)
- Create: `app/Services/Import/Parsers/MandiriExcelParser.php`
- Modify: `app/Services/Import/ParserRegistry.php` (swap the `mandiri` entry)
- Test: `tests/Unit/Services/Import/MandiriExcelParserTest.php`

**Interfaces:**
- Consumes: Task 1's contract, value objects, and fixtures.
- Produces: `App\Services\Import\Parsers\MandiriExcelParser implements ProviderCsvParser` — same `parse(string $contents): ParsedStatement` signature, same pinned layout (1 header row; date = col 0, description = col 1, debit = col 4, kredit = col 5), same failure tiers. Downstream plans notice nothing.

- [ ] **Step 1: Install the package**

```bash
composer require maatwebsite/excel --no-interaction
```

(No config publish needed — CSV settings are declared per-import via `WithCustomCsvSettings`, not globally.)

- [ ] **Step 2: Write the failing tests**

Create `tests/Unit/Services/Import/MandiriExcelParserTest.php` — the same fixture suite as Task 1, run against the Excel-backed parser. Note: these tests boot no Laravel app either; `Excel::toArray` on a local path needs no facades beyond the package's own autoloading. If the facade requires the container in your version, add `uses(Illuminate\Foundation\Testing\TestCase::class)` or move the file to `tests/Integration/Services/Import/` — the assertions are identical either way.

```php
<?php

use App\Enums\Cashflow;
use App\Services\Import\ImportFileException;
use App\Services\Import\Parsers\MandiriExcelParser;

function fixture(string $name): string
{
    return file_get_contents(dirname(__DIR__, 3) . '/Fixtures/Imports/' . $name);
}

test('parses a well-formed statement by pinned columns', function (): void {
    $statement = (new MandiriExcelParser())->parse(fixture('mandiri-basic.csv'));

    expect($statement->rows)->toHaveCount(3);

    [$first, $second, $third] = $statement->rows;

    expect($first->date?->toDateString())->toBe('2026-10-31')
        ->and($first->description)->toBe('TRSF E-BANKING CR BCA')
        ->and($first->amount)->toBe(2500000.0)
        ->and($first->flow)->toBe(Cashflow::Inflow)
        ->and($first->errors)->toBe([])
        ->and($second->flow)->toBe(Cashflow::Outflow)
        ->and($second->amount)->toBe(350000.0)
        ->and($third->amount)->toBe(25000.0);
});

test('stages rows with field errors instead of dropping them', function (): void {
    $statement = (new MandiriExcelParser())->parse(fixture('mandiri-dirty.csv'));

    expect($statement->rows)->toHaveCount(3)
        ->and($statement->rows[0]->errors)->toBe([])
        ->and($statement->rows[1]->date)->toBeNull()
        ->and($statement->rows[1]->errors)->toHaveKey('transaction_date')
        ->and($statement->rows[2]->amount)->toBeNull()
        ->and($statement->rows[2]->errors)->toHaveKey('amount');
});

test('throws when no row matches the pinned layout', function (): void {
    (new MandiriExcelParser())->parse(fixture('mandiri-unrecognized.csv'));
})->throws(ImportFileException::class, 'The statement does not match the expected Mandiri layout.');

test('throws on a header-only statement', function (): void {
    (new MandiriExcelParser())->parse("Tanggal;Keterangan;Cabang;Tipe;Debit;Kredit;Saldo\n");
})->throws(ImportFileException::class, 'The statement file is empty or has no data rows.');
```

(PhpSpreadsheet's CSV reader returns raw cell values — an invalid date like `31/13/2026` comes back as the literal string and fails `parseDate` in the trait, so the dirty-row expectations carry over. If a reader quirk shifts a value (e.g. trailing null cells), adjust the affected expectation, not the pinned layout.)

- [ ] **Step 3: Extract the shared generic cell-parsing trait**

Create `app/Services/Import/Concerns/ParsesStatementCells.php` — generic, cross-provider cell parsing: id-ID amount normalization (valid app-wide — the app is single-currency IDR), debit/kredit two-column row assembly (the common Indonesian statement convention), and date parsing against a **per-provider format list** (`statementDateFormats()` — override it in a parser whose export uses different formats; the default covers the common Indonesian ones). Pinned column indexes stay on each parser and are passed in. Providers with a different convention (e.g. a single signed amount column) simply don't call `assembleRow()`.

```php
<?php

namespace App\Services\Import\Concerns;

use App\Enums\Cashflow;
use App\Services\Import\ParsedRow;
use Carbon\CarbonImmutable;

/**
 * Generic cell-level parsing for statement imports, shared across
 * providers: id-ID amount normalization, the debit/kredit two-column
 * row assembly, and date parsing against a per-provider format list.
 * Each parser keeps its own pinned layout constants and passes the
 * column indexes in.
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
     * Assemble one staged row from the debit/kredit column pair.
     *
     * @param  array<int, string|null>  $columns
     */
    private function assembleRow(array $columns, int $dateColumn, int $descriptionColumn, int $debitColumn, int $creditColumn): ParsedRow
    {
        $date = $this->parseDate($columns[$dateColumn] ?? null);
        $description = isset($columns[$descriptionColumn]) && $columns[$descriptionColumn] !== null
            ? trim((string) $columns[$descriptionColumn])
            : null;
        $debit = $this->parseAmount((string) ($columns[$debitColumn] ?? ''));
        $credit = $this->parseAmount((string) ($columns[$creditColumn] ?? ''));

        $errors = [];
        if ($date === null) {
            $errors['transaction_date'] = 'Unrecognized date format.';
        }

        if ($debit === null && $credit === null) {
            $errors['amount'] = 'No amount column has a readable value.';
        } elseif ($debit !== null && $debit > 0 && $credit !== null && $credit > 0) {
            $errors['amount'] = 'Both debit and credit are set.';
        }

        $amount = $credit ?? $debit;
        $flow = match (true) {
            $credit !== null && $credit > 0 => Cashflow::Inflow,
            $debit !== null && $debit > 0 => Cashflow::Outflow,
            default => null,
        };

        return new ParsedRow($date, $description, $amount, $flow, $columns, $errors);
    }

    private function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        foreach ($this->statementDateFormats() as $format) {
            if (CarbonImmutable::rawCreateFromFormat($format, trim($value)) !== null) {
                return CarbonImmutable::rawCreateFromFormat($format, trim($value))->startOfDay();
            }
        }

        return null;
    }

    /** id-ID amounts: "Rp1.000.000,00", "350.000,-", "-25.000". */
    private function parseAmount(string $value): ?float
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

Then modify `MandiriCsvParser` (Task 1's file): add `use App\Services\Import\Concerns\ParsesStatementCells;` to the imports and `use ParsesStatementCells;` inside the class, delete its private `parseRow`, `parseDate`, `parseAmount` methods and the `DATE_FORMATS` constant (Mandiri's formats equal the trait default, so no override is needed), and change the `parse()` loop body to:

```php
        $rows = [];
        foreach (array_slice($lines, self::HEADER_ROWS) as $line) {
            $rows[] = $this->assembleRow(
                str_getcsv($line, self::DELIMITER) ?: [],
                self::COLUMN_DATE,
                self::COLUMN_DESCRIPTION,
                self::COLUMN_DEBIT,
                self::COLUMN_CREDIT
            );
        }
```

Task 1's tests must pass unchanged after this extraction — if they don't, the extraction drifted.

- [ ] **Step 4: Implement the Excel-backed parser**

Create `app/Services/Import/Parsers/MandiriExcelParser.php`. The contract takes a string, so the bytes are written to a temp file whose extension encodes the sniffed format (zip header → `.xlsx`, else `.csv`) — laravel-excel picks its reader from the extension:

```php
<?php

namespace App\Services\Import\Parsers;

use App\Services\Import\Concerns\ParsesStatementCells;
use App\Services\Import\Contracts\ProviderCsvParser;
use App\Services\Import\ImportFileException;
use App\Services\Import\ParsedRow;
use App\Services\Import\ParsedStatement;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Bank Mandiri Mutasi Rekening via maatwebsite/excel (PhpSpreadsheet) —
 * same pinned layout as MandiriCsvParser, but reads CSV and XLSX bytes.
 * The parse(string) contract is preserved by writing the payload to a
 * temp file; the zip magic header decides the extension, which decides
 * the reader.
 */
final class MandiriExcelParser implements ProviderCsvParser
{
    use ParsesStatementCells;

    /** Pinned rows: data begins after this many sheet rows. */
    private const int HEADER_ROWS = 1;

    /** Pinned columns, 0-based. */
    private const int COLUMN_DATE = 0;
    private const int COLUMN_DESCRIPTION = 1;
    private const int COLUMN_DEBIT = 4;
    private const int COLUMN_CREDIT = 5;

    public function parse(string $contents): ParsedStatement
    {
        $path = $this->writeTempFile($contents);

        try {
            $sheets = Excel::toArray(new class implements ToArray, WithCustomCsvSettings
            {
                /** @param array<int, array<int, string|null>> $array */
                public function array(array $array): array
                {
                    return $array;
                }

                /** @return array{delimiter: string, input_encoding: string} */
                public function getCsvSettings(): array
                {
                    return ['delimiter' => ';', 'input_encoding' => 'UTF-8'];
                }
            }, $path);
        } finally {
            @unlink($path);
        }

        $sheet = $sheets[0] ?? [];

        // Normalize every cell to string|null so the shared cell parsing
        // sees the same shapes the native parser does.
        $lines = array_values(array_filter(
            array_map(
                /** @param array<int, mixed> $row */
                fn (array $row): array => array_map(
                    fn (mixed $cell): ?string => $cell === null ? null : (string) $cell,
                    $row
                ),
                $sheet
            ),
            fn (array $row): bool => $row !== [] && array_filter($row, fn (mixed $cell): bool => trim((string) $cell) !== '') !== []
        ));

        if (count($lines) <= self::HEADER_ROWS) {
            throw new ImportFileException('The statement file is empty or has no data rows.');
        }

        $rows = [];
        foreach (array_slice($lines, self::HEADER_ROWS) as $columns) {
            $rows[] = $this->assembleRow($columns, self::COLUMN_DATE, self::COLUMN_DESCRIPTION, self::COLUMN_DEBIT, self::COLUMN_CREDIT);
        }

        $usable = array_filter($rows, fn (ParsedRow $row): bool => $row->errors === []);

        if ($usable === []) {
            throw new ImportFileException('The statement does not match the expected Mandiri layout.');
        }

        return new ParsedStatement($rows);
    }

    private function writeTempFile(string $contents): string
    {
        $isZip = str_starts_with($contents, "PK\x03\x04");

        $temporary = (string) tempnam(sys_get_temp_dir(), 'fintrack-import');
        $path = $temporary . ($isZip ? '.xlsx' : '.csv');
        rename($temporary, $path);
        file_put_contents($path, $contents);

        return $path;
    }
}
```

- [ ] **Step 5: Swap the registry entry**

In `app/Services/Import/ParserRegistry.php`, change the map — one entry per slug, the native parser kept as a comment for easy revert:

```php
    /** @var array<string, class-string<ProviderCsvParser>> */
    private const array PARSERS = [
        'mandiri' => Parsers\MandiriExcelParser::class,
        // 'mandiri' => Parsers\MandiriCsvParser::class, // native fallback
    ];
```

- [ ] **Step 6: Cross-plan follow-ups (notes, not code here)**

- The staging plan's `ImportUploadRequest` still validates `'mimes:csv,txt'` — with the Excel variant adopted, extend to `'mimes:csv,txt,xlsx'` and widen the upload-page `accept` attribute in the frontend plan's `import-upload-form.svelte` to `.csv,.xlsx`.
- The frontend upload-page hint copy stays accurate (columns are identical across both parsers).

---

## Post-Plan Notes (not tasks)

- **Mandiri layout verification**: when a real export sample arrives, adjust the pinned constants (`DELIMITER`, `HEADER_ROWS`, `COLUMN_*`, and — if the formats differ — an override of `statementDateFormats()` from the `ParsesStatementCells` trait, shared in Task 3) and the fixtures — nothing else should need to change. If the real file is the comma/quoted Excel variant, that is a different pinned layout: either swap the constants or add a second parser class and keep both in the registry under distinct provider slugs.
