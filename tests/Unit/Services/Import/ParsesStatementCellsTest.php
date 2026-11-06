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
    return new readonly class($formats)
    {
        use ParsesStatementCells;

        public function __construct(private array $formats) {}

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
    return new readonly class($formats)
    {
        use ParsesStatementCells;

        public function __construct(private array $formats) {}

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
    'dash-decimal tail' => ['25.000,-', 25000.0],
    'rp-prefixed' => ['Rp1.000.000', 1000000.0],
    'plain integer' => ['350000', 350000.0],
    'negative' => ['-25.000', -25000.0],
    'lone dash is empty' => ['-', null],
    'garbage is unreadable' => ['N/A', null],
]);

it('parses dates against the default Indonesian formats', function (string $cell, ?string $expected): void {
    $date = statementCells()->parseDate($cell);

    expect($date?->toDateString())->toBe($expected);
})->with([
    'slash-separated' => ['31/10/2026', '2026-10-31'],
    'dash-separated' => ['30-10-2026', '2026-10-30'],
    'month overflow' => ['31/13/2026', null],
    'not a date' => ['TOTAL', null],
    'empty' => ['', null],
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
    'hms' => ['12:49:41', '12:49:41'],
    'hms with wib' => ['12:49:41 WIB', '12:49:41'],
    'hm' => ['14:28', '14:28:00'],
    'not a time' => ['Sep', null],
    'empty' => ['', null],
]);

it('honors a per-provider time format override', function (): void {
    $time = statementCellsWithTimeFormats(['H.i.s'])->parseTime('12.49.41');

    expect($time?->toTimeString())->toBe('12:49:41');
});
