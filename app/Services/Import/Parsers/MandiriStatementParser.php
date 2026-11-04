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
     * @param  array<int, string|null>  $row
     * @param  array<int, string|null>|null  $companion
     */
    private function assembleRow(array $row, ?array $companion): ParsedRow
    {
        $date = $this->parseDate($row[self::COLUMN_DATETIME] ?? null);
        $time = $companion === null ? null : $this->parseTime($companion[self::COLUMN_DATETIME] ?? null);

        if ($date !== null && $time !== null) {
            $date = $date->setTimeFromTimeString($time->toTimeString());
        }

        $description = isset($row[self::COLUMN_DESCRIPTION]) && $row[self::COLUMN_DESCRIPTION] !== null
            ? trim((string) preg_replace('/\s+/u', ' ', $row[self::COLUMN_DESCRIPTION]))
            : null;
        $debit = $this->parseAmount((string) ($row[self::COLUMN_DEBIT] ?? ''));
        $credit = $this->parseAmount((string) ($row[self::COLUMN_CREDIT] ?? ''));

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

        return new ParsedRow($date, $description, $amount, $flow, $row, $errors);
    }
}
