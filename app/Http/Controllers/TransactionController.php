<?php

namespace App\Http\Controllers;

use App\Data\Transaction\TransactionData;
use App\Data\Transaction\TransactionDetailData;
use App\Data\Transaction\TransactionListData;
use App\Http\Requests\SaveTransactionRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountService;
use App\Services\CategoryService;
use App\Services\TransactionService;
use App\Services\TransferService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly TransferService $transferService,
        private readonly AccountService $accountService,
        #[CurrentUser] private readonly ?User $user
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Transaction::class);

        $transactions = $this->transactionService->getTransactions($this->user)
            ->map(fn (Transaction $transaction): TransactionListData => TransactionListData::fromTransaction($transaction));

        return Inertia::render('transactions/index', [
            'transactions' => $transactions,
            'summary' => [],
        ]);
    }

    public function show(Request $request, Transaction $transaction): Response
    {
        $this->authorize('view', $transaction);

        $transaction->load(['account', 'category.parent', 'creator']);

        return Inertia::render('transactions/show', [
            'transaction' => TransactionDetailData::from($transaction),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Transaction::class);

        $accounts = $this->accountService->getAccountsByUser($this->user);

        return Inertia::render('transactions/create', [
            'categories' => CategoryService::getCategories(),
            'accounts' => $accounts,
        ]);
    }

    public function edit(Request $request, Transaction $transaction): Response | RedirectResponse
    {
        $this->authorize('update', $transaction);

        if ($transaction->transfer_id !== null) {
            return to_route('transfers.edit', $transaction->transfer_id);
        }

        $accounts = $this->accountService->getAccountsByUser($this->user);

        return Inertia::render('transactions/edit', [
            'accounts' => $accounts,
            'transaction' => $transaction->load('category'),
            'categories' => CategoryService::getCategories(),
        ]);
    }

    public function store(SaveTransactionRequest $request): RedirectResponse
    {
        $this->authorize('create', Transaction::class);

        $this->transactionService->create($this->user, TransactionData::from($request->validated()));

        return to_route('transactions.index')->flash('Transaction saved.');
    }

    public function update(SaveTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        abort_unless($transaction->transfer_id === null, 422, 'Transfer unit members must be edited via their transfer.');

        $this->transactionService->update($transaction, TransactionData::from($request->validated()));

        return to_route('transactions.index')->flash('Transaction updated.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        if ($transaction->transfer_id !== null) {
            $this->transferService->deleteUnit($transaction);
        } else {
            $this->transactionService->softDelete($transaction);
        }

        return to_route('transactions.index')->flash('Transaction deleted.');
    }
}
