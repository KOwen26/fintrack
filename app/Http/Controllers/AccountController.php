<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\Provider;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('accounts/index', [
            'accounts' => $this->accountService->getAccountsByUser(auth()->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('accounts/create', [
            'providers' => Provider::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $account = $this->accountService->create($request->user(), $request->validated());

        return to_route('accounts.show', $account)->flash('Account created.');
    }

    public function show(Request $request, Account $account): Response
    {
        $this->authorize('view', $account);

        return Inertia::render('accounts/show', [
            'account' => $account->load('provider'),
        ]);
    }

    public function edit(Account $account): Response
    {
        $this->authorize('update', $account);

        return Inertia::render('accounts/edit', [
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
