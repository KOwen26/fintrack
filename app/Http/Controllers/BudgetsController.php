<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBudgetRequest;
use App\Http\Requests\UpdateBudgetRequest;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Services\BudgetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetsController extends Controller
{
    public function __construct(private readonly BudgetService $budgetService) {}

    public function index(Request $request, Account $account): Response
    {
        $this->authorize('viewAny', [Budget::class, $account]);

        $year = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);

        $budgets = Budget::query()
            ->where('account_id', $account->id)
            ->where('year', $year)
            ->where('month', $month)
            ->with('category')
            ->get();

        $budgetsWithStatus = $budgets->map(fn (Budget $budget): array => [
            'budget' => $budget,
            'status' => $this->budgetService->calculateStatus($account, $budget->category, $year, $month),
        ]);

        return Inertia::render('budgets/index', [
            'account' => $account,
            'budgets_with_status' => $budgetsWithStatus,
            'year' => $year,
            'month' => $month,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(StoreBudgetRequest $request, Account $account): RedirectResponse
    {
        $this->authorize('create', [Budget::class, $account]);

        $this->budgetService->upsert($account, $request->validated());

        return to_route('budgets.index', $account)->flash('Budget saved.');
    }

    public function update(UpdateBudgetRequest $request, Account $account, Budget $budget): RedirectResponse
    {
        $this->authorize('update', $budget);

        $this->budgetService->update($budget, $request->validated());

        return to_route('budgets.index', $account)->flash('Budget updated.');
    }

    public function destroy(Account $account, Budget $budget): RedirectResponse
    {
        $this->authorize('delete', $budget);

        $this->budgetService->softDelete($budget);

        return to_route('budgets.index', $account)->flash('Budget deleted.');
    }
}
