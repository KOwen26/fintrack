<?php

use App\Http\Controllers\DevController;
use App\Http\Middleware\OnlyDevelopment;
use App\Models\DecorationColor;
use App\Services\Import\ImportFileException;
use App\Services\Import\ParsedRow;
use App\Services\Import\Parsers\MandiriStatementParser;
use App\Services\Import\StatementFileReader;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;

Route::group([
    'prefix' => 'dev',
    'as' => 'dev.',
    'middleware' => [OnlyDevelopment::class],
], function (): void {
    Route::get('test/transaction', function () {
        $transactions = TransactionService::getTransactions(auth()->user());

        return compact('transactions');
    });

    Route::get('test', function () {
        $colors = DecorationColor::query()
            ->whereLike('slug', '%-50')
            ->orWhereLike('slug', '%-100')
            ->orWhereLike('slug', '%-900')
            ->orWhereLike('slug', '%-950')
            // ->update(['status' => 'Inactive'])
            ->get();

        return response()->json($colors);
    });
    Route::get('', fn () => Inertia::render('dev/test'))->name('test');
    Route::get('color', fn () => Inertia::render('dev/color'))->name('color');
    Route::get('design', fn () => Inertia::render('dev/design'))->name('design');

    Route::prefix('design')->name('design.')->group(function (): void {
        Route::get('accounts', fn () => Inertia::render('dev/design/accounts'))->name('accounts');
        Route::get('card', fn () => Inertia::render('dev/design/card'))->name('card');
        Route::get('color', fn () => Inertia::render('dev/design/color'))->name('color');
        Route::get('layouts', fn () => Inertia::render('dev/design/layouts'))->name('layouts');
    });

    Route::get('examples/dashboard', fn () => Inertia::render('dev/examples/dashboard'))->name('examples.dashboard');
    Route::get('form', fn () => Inertia::render('dev/form'))->name('form');
    Route::get('table', [DevController::class, 'table'])->name('table');

    Route::get('table/server', [DevController::class, 'tableServer'])->name('table.server');

    Route::get('categories', [DevController::class, 'categories'])->name('categories');

    Route::get('import', function (Request $request) {
        $path = storage_path('app/private/statement-dummy.xlsx');
        $file = new UploadedFile($path, basename($path));
        $raw = StatementFileReader::rows($file);

        $response = [
            'file' => ['name' => basename($path), 'rows_read' => count($raw)],
            'raw_first' => array_slice($raw, 0, (int) $request->query('raw_rows', 10)),
        ];

        try {
            $statement = (new MandiriStatementParser)->parse($file);
        } catch (ImportFileException $importFileException) {
            return response()->json([...$response, 'error' => $importFileException->getMessage()]);
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
});
