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
function mandiriMainRow(string $sequence, string $date, string $description, ?string $credit = null, string | float | null $debit = null): array
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
        ->and($statement->rows[0]->errors)->toBeEmpty();
});

it('carries every row-level failure mode as field errors, never drops rows', function (): void {
    $statement = (new MandiriStatementParser)->parse(mandiriUpload('mandiri-dirty.xlsx'));

    expect($statement->rows)->toHaveCount(5)
        ->and($statement->rows[0]->errors)->toBeEmpty()
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
        ->and($statement->rows[0]->errors)->toBeEmpty();
});

it('keeps the date at start of day when the time companion is missing', function (): void {
    $statement = (new MandiriStatementParser)->parse(mandiriXlsxFromRows([
        mandiriMainRow('1,00', '31 Oct 2026', 'TRSF E-BANKING CR BCA', credit: '2.500.000,00'),
    ]));

    expect($statement->rows)->toHaveCount(1)
        ->and($statement->rows[0]->date?->toDateTimeString())->toBe('2026-10-31 00:00:00')
        ->and($statement->rows[0]->errors)->toBeEmpty();
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
        ->and($statement->rows[0]->errors)->toBeEmpty();
});

it('re-renders typed XLSX number cells without losing decimals', function (): void {
    $statement = (new MandiriStatementParser)->parse(mandiriXlsxFromRows([
        mandiriMainRow('1,00', '30 Oct 2026', 'PEMBAYARAN LISTRIK PLN', debit: 350000.5),
        mandiriTimeRow('14:28:28 WIB'),
    ]));

    expect($statement->rows)->toHaveCount(1)
        ->and($statement->rows[0]->amount)->toBe(350000.5)
        ->and($statement->rows[0]->flow)->toBe(Cashflow::Outflow)
        ->and($statement->rows[0]->errors)->toBeEmpty();
});
