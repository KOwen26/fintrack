<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportUploadRequest;
use App\Http\Requests\UpdateImportRowRequest;
use App\Models\Account;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\User;
use App\Services\AccountService;
use App\Services\CategoryService;
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
        #[CurrentUser] private readonly ?User $user,
    ) {}

    public function create(Request $request, Account $account): Response
    {
        $this->authorize('update', $account);

        return Inertia::render('app/account/import', [
            'account' => $account->load('provider'),
            'provider_name' => $account->provider?->name ?? 'Statement',
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

        return Inertia::render('app/import/review', [
            'batch' => $batch->load(['account.provider', 'provider']),
            'rows' => $batch->rows()->orderBy('row_number')->get(),
            'categories' => CategoryService::getCategories(),
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
}
