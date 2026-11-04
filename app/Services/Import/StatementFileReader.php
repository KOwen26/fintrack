<?php

namespace App\Services\Import;

use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The single file-reading helper for statement imports — CSV and XLSX
 * alike: every file mechanic is delegated to laravel-excel (format
 * detection, delimiter, quotes/enclosures, BOM, input encoding).
 * Parsers hand in their pinned CSV settings and receive normalized
 * string cell rows; they never touch the file themselves.
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
        $sheets = Excel::toArray(new readonly class($delimiter, $encoding) implements ToArray, WithCustomCsvSettings
        {
            public function __construct(
                private string $delimiter,
                private string $encoding,
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
