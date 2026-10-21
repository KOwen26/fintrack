<?php

namespace App\Http\Controllers;

use App\Data\Transaction\TransactionData;
use App\Data\Transaction\TransactionDetailData;
use App\Data\Transaction\TransactionFormData;
use App\Enums\AccountAccessType;
use App\Enums\Category;
use App\Enums\TransactionType;
use App\Http\Requests\SaveTransactionRequest;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Objects\TransactionCursor;
use App\Services\AccountService;
use App\Services\TransactionService;
use App\Services\TransferService;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

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

        try {
            $cursor = TransactionCursor::fromWire($request->input('cursor'));
        } catch (InvalidFormatException | InvalidArgumentException) {
            abort(400, 'Malformed transaction cursor.');
        }

        ['rows' => $transactions, 'total' => $total, 'has_more' => $hasMore] =
            $this->transactionService->getTransactionsPage($this->user, $cursor);

        $accounts = Account::query()
            ->notArchived()
            ->where(function ($query): void {
                $query->where('owner_id', $this->user->id)
                    ->orWhere('access_type', AccountAccessType::Joint);
            })
            ->get(['id', 'name', 'decorations', 'owner_id', 'access_type']);

        $categories = collect(Category::cases())->map(fn (Category $category): array => [
            'id' => $category->value,
            'name' => $category->label(),
            'decorations' => $category->decorations()->toArray(),
        ])->all();

        // Append semantics are carried by the merge prop: a partial reload
        // with `only: ['transactions', 'transactions_meta']` extends the
        // client's array instead of replacing it; full visits replace it.
        return Inertia::render('app/transaction/index', [
            'transactions' => Inertia::merge($transactions),
            'transactions_meta' => ['total' => $total, 'has_more' => $hasMore],
            'summary' => $this->transactionService->getSummary($this->user),
            'accounts' => $accounts,
            'categories' => $categories,
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
            'accounts' => $accounts,
        ]);
    }

    public function edit(Request $request, Transaction $transaction): Response | RedirectResponse
    {
        $this->authorize('update', $transaction);

        if ($transaction->transfer_id !== null) {
            return to_route('transfers.edit', $transaction->transfer_id);
        }

        if ($transaction->category_id === Category::InitialBalance) {
            return to_route('accounts.edit', $transaction->account_id);
        }

        $accounts = $this->accountService->getAccountsByUser($this->user);

        return Inertia::render('app/transaction/edit', [
            'accounts' => $accounts,
            'transaction' => TransactionFormData::fromTransaction($transaction),
        ]);
    }

    public function store(SaveTransactionRequest $request): RedirectResponse
    {
        $this->transactionService->create($this->user, TransactionData::from($request->validated()));

        return to_route('transactions.index')->flash('Transaction saved.');
    }

    public function update(SaveTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->transfer_id === null, 422, 'Transfer unit members must be edited via their transfer.');

        abort_if(
            $transaction->category_id === Category::InitialBalance,
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
                $transaction->category_id === Category::InitialBalance,
                422,
                'Initial balance must be changed via the account.'
            );

            $this->transactionService->softDelete($transaction);
        }

        return to_route('transactions.index')->flash('Transaction deleted.');
    }
}
