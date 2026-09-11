<?php

namespace App\Http\Controllers;

use App\Data\Transaction\TransferData;
use App\Http\Requests\SaveTransferRequest;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\AccountService;
use App\Services\TransferService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function __construct(
        private readonly TransferService $transferService,
        private readonly AccountService $accountService,
        #[CurrentUser] private readonly ?User $user
    ) {}

    public function edit(Request $request, Transfer $transfer): Response
    {
        $this->authorize('update', $transfer);

        return Inertia::render('transactions/edit-transfer', [
            'transfer' => $transfer->load('transactions.account'),
            'accounts' => $this->accountService->getAccountsByUser($this->user),
        ]);
    }

    public function store(SaveTransferRequest $request): RedirectResponse
    {
        $this->authorize('create', Transaction::class);

        $this->transferService->create($this->user, TransferData::from($request->validated()));

        return to_route('transactions.index')->flash('Transfer saved.');
    }

    public function update(SaveTransferRequest $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('update', $transfer);

        $this->transferService->update($transfer, TransferData::from($request->validated()));

        return to_route('transactions.index')->flash('Transfer updated.');
    }
}
