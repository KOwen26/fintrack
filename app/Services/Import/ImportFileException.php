<?php

namespace App\Services\Import;

use RuntimeException;

/** File-level parse failure — the layout itself is unreadable, no batch is created. */
final class ImportFileException extends RuntimeException {}
