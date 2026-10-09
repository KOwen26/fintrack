<?php

namespace App\Http\Controllers;

use App\Data\Transaction\TransactionData;
use App\Data\Transaction\TransactionDetailData;
use App\Data\Transaction\TransactionFormData;
use App\Data\Transaction\TransactionListData;
use App\Enums\TransactionType;
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

final class TransactionController extends Controller
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

        $transactions = TransactionListData::collect($this->transactionService->getTransactions($this->user));

        return Inertia::render('app/transaction/index', [
            'transactions' => $transactions,
            'summary' => [],
        ]);
    }

    public function show(Request $request, Transaction $transaction): Response
    {
        $this->authorize('view', $transaction);

        return Inertia::render('app/transaction/show', [
            'transaction' => TransactionDetailData::fromTransaction($transaction),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Transaction::class);

        $accounts = $this->accountService->getAccountsByUser($this->user);

        return Inertia::render('app/transaction/create', [
            'initialType' => $request->query('type', TransactionType::Expense),
            'transaction' => TransactionFormData::defaultExpense(),
            'categories' => CategoryService::getBookableCategories(),
            'accounts' => $accounts,
        ]);
    }

    public function edit(Request $request, Transaction $transaction): Response | RedirectResponse
    {
        $this->authorize('update', $transaction);

        if ($transaction->transfer_id !== null) {
            return to_route('transfers.edit', $transaction->transfer_id);
        }

        if ($transaction->category_id === CategoryService::initialBalanceCategoryId()) {
            return to_route('accounts.edit', $transaction->account_id);
        }

        $accounts = $this->accountService->getAccountsByUser($this->user);

        return Inertia::render('app/transaction/edit', [
            'accounts' => $accounts,
            'categories' => CategoryService::getBookableCategories(),
            'transaction' => TransactionFormData::fromTransaction($transaction),
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

        abort_if(
            $transaction->category_id === CategoryService::initialBalanceCategoryId(),
            422,
            'Initial balance must be changed via the account.'
        );

        $this->transactionService->update($transaction, TransactionData::from($request->validated()));

        return to_route('transactions.index')->flash('Transaction updated.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        if ($transaction->transfer_id !== null) {
            $this->transferService->deleteUnit($transaction);
        } else {
            abort_if(
                $transaction->category_id === CategoryService::initialBalanceCategoryId(),
                422,
                'Initial balance must be changed via the account.'
            );

            $this->transactionService->softDelete($transaction);
        }

        return to_route('transactions.index')->flash('Transaction deleted.');
    }
}
