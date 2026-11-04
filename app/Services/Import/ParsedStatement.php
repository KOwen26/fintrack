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
