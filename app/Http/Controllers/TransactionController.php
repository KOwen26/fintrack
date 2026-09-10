<?php

namespace App\Http\Controllers;

use App\Data\Transaction\TransactionDetailData;
use App\Data\Transaction\TransactionListData;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountService;
use App\Services\BalanceService;
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
        private readonly BalanceService $balanceService,
        private readonly AccountService $accountService,
        #[CurrentUser] private readonly ?User $user
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Transaction::class);

        $transactions = TransactionListData::collect($this->transactionService->getTransactions($this->user));

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

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $this->authorize('create', Transaction::class);

        $data = $request->validated();

        if ($data['type'] === 'transfer') {
            $destinationAccount = Account::findOrFail($data['destination_account_id']);

            $this->transactionService->createTransfer(
                sourceAccount: $account,
                destinationAccount: $destinationAccount,
                creator: $request->user(),
                amount: (float) $data['amount'],
                transactionDate: $data['transaction_date'],
                feeAmount: isset($data['fee_amount']) ? (float) $data['fee_amount'] : null,
                description: $data['description'] ?? null,
            );
        } else {
            $this->transactionService->create($account, $request->user(), $data);
        }

        return to_route('transactions.index')->flash('Transaction saved.');
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $this->transactionService->update($transaction, $request->validated());

        return to_route('transactions.index')->flash('Transaction updated.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $this->transactionService->softDelete($transaction);

        return to_route('transactions.index')->flash('Transaction deleted.');
    }
}
