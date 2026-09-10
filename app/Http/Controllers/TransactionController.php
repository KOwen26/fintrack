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
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService,
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

    public function edit(Request $request, Transaction $transaction): Response
    {
        $this->authorize('update', $transaction);

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

        $data = TransactionData::from($request->validated());

        if ($data->isTransfer()) {
            $this->transactionService->createTransfer($this->user, $data);
        } else {
            $this->transactionService->create($this->user, $data);
        }

        return to_route('transactions.index')->flash('Transaction saved.');
    }

    public function update(SaveTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $this->transactionService->update($transaction, TransactionData::from($request->validated()));

        return to_route('transactions.index')->flash('Transaction updated.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $this->transactionService->softDelete($transaction);

        return to_route('transactions.index')->flash('Transaction deleted.');
    }
}
