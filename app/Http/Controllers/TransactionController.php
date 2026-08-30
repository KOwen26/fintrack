<?php

namespace App\Http\Controllers;

use App\Data\Transaction\TransactionDetailData;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\AccountService;
use App\Services\BalanceService;
use App\Services\CategoryService;
use App\Services\TransactionService;
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
    ) {}

    public function index(Request $request, Account $account): Response
    {
        $this->authorize('viewAny', [Transaction::class, $account]);

        return Inertia::render('transactions/index', [
            'transactions' => $this->transactionService->getTransactions(),
            'summary' => [],
        ]);
    }

    public function create(Request $request, Account $account): Response
    {
        $this->authorize('create', [Transaction::class, $account]);

        return Inertia::render('transactions/create', [
            'account' => $account,
            'categories' => CategoryService::getCategories(),
            'accounts' => $this->accountService->getTransferEligibleAccounts($account),
        ]);
    }

    public function store(StoreTransactionRequest $request, Account $account): RedirectResponse
    {
        $this->authorize('create', [Transaction::class, $account]);

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

        return to_route('transactions.index', $account)->flash('Transaction saved.');
    }

    public function show(Request $request, Transaction $transaction): Response
    {
        $this->authorize('view', $transaction);

        $transaction->load(['account', 'category.parent', 'creator']);

        return Inertia::render('transactions/show', [
            'transaction' => TransactionDetailData::from($transaction),
        ]);
    }

    public function edit(Request $request, Account $account, Transaction $transaction): Response
    {
        $this->authorize('update', $transaction);

        return Inertia::render('transactions/edit', [
            'account' => $account,
            'transaction' => $transaction->load('category'),
            'categories' => $this->transactionService->getCategories(),
        ]);
    }

    public function update(UpdateTransactionRequest $request, Account $account, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $this->transactionService->update($transaction, $request->validated());

        return to_route('transactions.index', $account)->flash('Transaction updated.');
    }

    public function destroy(Account $account, Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $this->transactionService->softDelete($transaction);

        return to_route('transactions.index', $account)->flash('Transaction deleted.');
    }
}
