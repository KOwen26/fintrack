<?php

namespace App\Http\Controllers;

use App\Data\Transaction\TransactionData;
use App\Data\Transaction\TransactionDetailData;
use App\Data\Transaction\TransactionFormData;
use App\Data\Transaction\TransactionListCursor;
use App\Data\Transaction\TransactionListFilters;
use App\Enums\Category;
use App\Enums\DatePeriodPreset;
use App\Http\Requests\SaveTransactionRequest;
use App\Http\Requests\TransactionIndexRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountService;
use App\Services\CategoryService;
use App\Services\TagService;
use App\Services\TransactionListService;
use App\Services\TransactionService;
use App\Services\TransactionTemplateService;
use App\Services\TransferService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TransactionController extends Controller
{
    /**
     * Deployment pairing for the transaction list's serving mode — must match
     * the frontend engine ('full' ↔ client, 'paged' ↔ hybrid/server). Never
     * request-driven: no user or client input selects it.
     */
    private const string SERVING_WINDOW = 'full';

    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly TransactionListService $transactionListService,
        private readonly TransferService $transferService,
        private readonly AccountService $accountService,
        #[CurrentUser] private readonly ?User $user
    ) {}

    public function index(TransactionIndexRequest $request): Response
    {
        $this->authorize('viewAny', Transaction::class);

        $period = DatePeriodPreset::tryFrom($request->input('period', '')) ?? DatePeriodPreset::default();

        $filters = TransactionListFilters::fromRequest($request);

        // Serving mode is a server-owned deployment constant, not a request
        // toggle — it pairs 1:1 with the frontend engine choice. 'full' ships
        // the entire period window (client engine: filtering, sorting and
        // pagination all run in the browser); 'paged' answers keyset pages
        // (hybrid/server engines: append via merge).
        if (self::SERVING_WINDOW === 'full') {
            return Inertia::render('app/transaction/index', [
                'period' => $period->value,
                'summary' => $this->transactionListService->getSummary($this->user, $period),
                'transactions' => Inertia::defer(fn (): array => $this->transactionListService->getCachedTransactions($this->user, $period)),
                'accounts' => AccountService::getAccountsByUser($this->user),
                'categories' => CategoryService::getCategories(),
                'tags' => TagService::getUniqueTags($this->user),
            ]);
        }

        $cursorTransactions = $this->transactionListService->getTransactionsByCursorPage(
            $this->user,
            TransactionListCursor::fromRequest($request, $filters->sort),
            $period,
            $filters,
        );

        // Append semantics only apply to load-more: a cursor marks a next-page
        // append. A filter, sort or period change reloads without a cursor and
        // MUST replace — a merge label there would graft new rows onto the old
        // query's pages, so the prop goes out unmerged for those requests.
        return Inertia::render('app/transaction/index', [
            'transactions' => Inertia::merge($cursorTransactions['transactions']),
            'transactions_meta' => $cursorTransactions['meta'],
            'period' => $period->value,
            'summary' => $this->transactionListService->getSummary($this->user, $period),
            'accounts' => AccountService::getAccountsByUser($this->user),
            'categories' => CategoryService::getCategories(),
            'tags' => TagService::getUniqueTags($this->user),
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

        $template = TransactionTemplateService::getTemplateById($this->user, $request->integer('template'));

        return Inertia::render('app/transaction/create', [
            'transaction' => $template !== null
                ? TransactionFormData::fromTemplate($template, $request->integer('account_id') ?: null)
                : TransactionFormData::defaultFromRequest($request),
            'accounts' => $this->accountService->getAccountsByUser($this->user),
            'templates' => TransactionTemplateService::getQuickAccessTemplates($this->user),
            'tags' => TagService::getUniqueTags($this->user),
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
            'tags' => TagService::getUniqueTags($this->user),
        ]);
    }

    public function store(SaveTransactionRequest $request): RedirectResponse
    {
        $this->transactionService->create($this->user, TransactionData::from($request->validated()));

        return to_route('dashboard')->flash('Transaction saved.');
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
