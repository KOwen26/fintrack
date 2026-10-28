<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTransactionTemplateRequest;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\AccountService;
use App\Services\CategoryService;
use App\Services\TransactionTemplateService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class TransactionTemplateController extends Controller
{
    public function __construct(
        private readonly TransactionTemplateService $templateService,
        #[CurrentUser] private readonly ?User $user,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', TransactionTemplate::class);

        return Inertia::render('app/settings/transaction-templates/index', [
            'templates' => TransactionTemplateService::getTemplatesByUser($this->user),
            'categories' => CategoryService::getCategories(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', TransactionTemplate::class);

        return Inertia::render('app/settings/transaction-templates/form', [
            'template' => null,
            'accounts' => AccountService::getAccountsByUser($this->user),
            'categories' => CategoryService::getCategories(),
        ]);
    }

    public function store(SaveTransactionTemplateRequest $request): RedirectResponse
    {
        $this->templateService->create($this->user, $request->validated());

        return to_route('transaction-templates.index')->flash('Template created.');
    }

    public function show(TransactionTemplate $template): Response
    {
        $this->authorize('view', $template);

        return Inertia::render('app/settings/transaction-templates/show', [
            'template' => $template->load(['account:id,name', 'destinationAccount:id,name']),
            'categories' => CategoryService::getCategories(),
        ]);
    }

    public function edit(TransactionTemplate $template): Response
    {
        $this->authorize('update', $template);

        return Inertia::render('app/settings/transaction-templates/form', [
            'template' => $template,
            'accounts' => AccountService::getAccountsByUser($this->user),
            'categories' => CategoryService::getCategories(),
        ]);
    }

    public function update(SaveTransactionTemplateRequest $request, TransactionTemplate $template): RedirectResponse
    {
        $this->templateService->update($template, $request->validated());

        return to_route('transaction-templates.show', $template)->flash('Template updated.');
    }

    public function destroy(TransactionTemplate $template): RedirectResponse
    {
        $this->authorize('delete', $template);

        $this->templateService->delete($template);

        return to_route('transaction-templates.index')->flash('Template deleted.');
    }
}
