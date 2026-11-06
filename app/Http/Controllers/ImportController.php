<?php

namespace App\Http\Controllers;

use App\Data\Import\ImportRowData;
use App\Enums\ImportBatchStatus;
use App\Http\Requests\ImportUploadRequest;
use App\Http\Requests\UpdateImportRowRequest;
use App\Models\Account;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\User;
use App\Services\AccountService;
use App\Services\Import\ImportSuggestion;
use App\Services\Import\ParserRegistry;
use App\Services\ImportService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ImportController extends Controller
{
    public function __construct(
        private readonly ImportService $importService,
        private readonly ParserRegistry $registry,
        private readonly AccountService $accountService,
        private readonly ImportSuggestion $importSuggestion,
        #[CurrentUser] private readonly ?User $user,
    ) {}

    public function index(): Response
    {
        $accounts = $this->accountService->getAccountsByUser($this->user)
            ->filter(fn ($account): bool => in_array($account->provider?->slug, ParserRegistry::supportedProviders(), true));

        return Inertia::render('app/settings/imports/index', [
            'accounts' => $accounts->values(),
            'batches' => $this->importService->getBatchesByUser($this->user),
        ]);
    }

    public function store(ImportUploadRequest $request, Account $account): RedirectResponse
    {
        $batch = $this->importService->upload($request->user(), $account, $request->file('statement'));

        return to_route('imports.show', $batch)->flash('Statement staged for review.');
    }

    public function show(Request $request, ImportBatch $batch): Response
    {
        $this->authorize('view', $batch);

        $this->importSuggestion->failIfStale($batch);

        return Inertia::render('app/settings/imports/review', [
            'batch' => $batch->load(['account.provider', 'provider']),
            'rows' => ImportRowData::collect($batch->rows()->orderBy('row_number')->get()),
            'accounts' => $this->accountService->getAccountsByUser($this->user),
        ]);
    }

    public function updateRow(UpdateImportRowRequest $request, ImportBatch $batch, ImportRow $row): RedirectResponse
    {
        abort_unless($row->batch_id === $batch->id, 404);

        $this->importService->updateRow($row, $request->validated());

        return to_route('imports.show', $batch)->flash('Row updated.');
    }

    public function approve(Request $request, ImportBatch $batch): RedirectResponse
    {
        $this->authorize('approve', $batch);

        $count = $this->importService->approve($batch);

        return to_route('accounts.show', $batch->account_id)
            ->flash("Imported {$count} transactions.");
    }

    public function discard(Request $request, ImportBatch $batch): RedirectResponse
    {
        $this->authorize('discard', $batch);

        $this->importService->discard($batch);

        return to_route('accounts.show', $batch->account_id)->flash('Import discarded.');
    }

    public function retrySuggestions(Request $request, ImportBatch $batch): RedirectResponse
    {
        $this->authorize('retry', $batch);

        abort_unless($batch->status === ImportBatchStatus::Staging, 422, 'Only staging batches can retry suggestions.');
        abort_unless(ImportSuggestion::isEnabled(), 422, 'Suggestions are not configured.');

        $this->importSuggestion->retry($batch);

        return to_route('imports.show', $batch)->flash('Retrying suggestions.');
    }
}
