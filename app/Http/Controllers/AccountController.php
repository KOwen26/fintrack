<?php

namespace App\Http\Controllers;

use App\Enums\DatePeriodPreset;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\Provider;
use App\Models\User;
use App\Services\AccountService;
use App\Services\SpendingService;
use App\Services\TransactionService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AccountController extends Controller
{
    public function __construct(
        #[CurrentUser] private readonly ?User $user,
        private readonly AccountService $accountService,
    ) {}

    public function index(Request $request): Response
    {
        $accounts = $this->accountService->getAccountsByUser($this->user);

        $archivedAccounts = $this->accountService->getArchivedAccountsByUser($this->user);

        return Inertia::render('app/account/index', [
            'accounts' => $accounts,
            'archived_accounts' => $archivedAccounts,
            'summary' => AccountService::summarize($accounts),
        ]);
    }

    public function show(Request $request, Account $account, TransactionService $transactionService, SpendingService $spendingService): Response
    {
        $this->authorize('view', $account);

        $account->load(['provider']);

        $period = DatePeriodPreset::ThisMonth;

        $transactions = $transactionService->getAccountTransactions($account, $period);

        $categorySpending = $spendingService->globalCategorySpending([$account->id], $period);

        return Inertia::render('app/account/show', [
            'account' => $account,
            'transactions' => $transactions,
            'categorySpending' => $categorySpending,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('app/account/create', [
            'providers' => Provider::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $account = $this->accountService->create($this->user, $request->validated());

        return to_route('accounts.show', $account)->flash('Account created.');
    }

    public function edit(Account $account): Response
    {
        $this->authorize('update', $account);

        return Inertia::render('app/account/edit', [
            'account' => $account->load('provider'),
            'providers' => Provider::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $this->authorize('update', $account);

        $this->accountService->update($account, $request->validated());

        return to_route('accounts.show', $account)->flash('Account updated.');
    }

    public function destroy(Account $account): RedirectResponse
    {
        $this->authorize('delete', $account);

        $this->accountService->softDelete($account);

        return to_route('accounts.index')->flash('Account deleted.');
    }

    public function archive(Account $account): RedirectResponse
    {
        $this->authorize('archive', $account);

        $this->accountService->archive($account);

        return to_route('accounts.index')->flash('Account archived.');
    }

    public function restore(Account $account): RedirectResponse
    {
        $this->authorize('archive', $account);

        $this->accountService->restore($account);

        return to_route('accounts.show', $account)->flash('Account restored.');
    }
}
