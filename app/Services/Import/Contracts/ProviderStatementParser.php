<?php

namespace App\Services\Import\Contracts;

use App\Services\Import\ParsedStatement;
use Illuminate\Http\UploadedFile;

/**
 * One provider's statement export (CSV or XLSX). Reading is fully
 * delegated to maatwebsite/excel; implementations own only semantics:
 * their pinned layout (data-row start + column indexes), their column
 * convention's row assembly, and their date formats. File-level
 * structure failures throw ImportFileException; row-level problems
 * surface as ParsedRow::errors.
 */
interface ProviderStatementParser
{
    public function parse(UploadedFile $file): ParsedStatement;
}
