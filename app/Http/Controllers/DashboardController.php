<?php

namespace App\Http\Controllers;

use App\Enums\DatePeriodPreset;
use App\Models\User;
use App\Services\AccountService;
use App\Services\SpendingService;
use App\Services\TransactionService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __construct(
        #[CurrentUser()] private readonly ?User $user,
        private readonly TransactionService $transactionService,
        private readonly AccountService $accountService,
        private readonly SpendingService $spendingService,
    ) {}

    /**
     * Dashboard overview — summary, recent transactions, accounts, and category spending.
     */
    public function index(Request $request): Response
    {
        $accounts = $this->accountService->getAccountsByUser($this->user);

        $categorySpending = $this->spendingService->globalCategorySpending($accounts->pluck('id')->all(), DatePeriodPreset::ThisMonth);

        $recentTransactions = $this->transactionService->getTransactions($this->user, DatePeriodPreset::Last14Days);

        $summary = [
            'current_balance' => $this->accountService->summarize($accounts)['total_balance'],
            'monthly_income' => 0,
            'monthly_expenses' => 0,
            'monthly_savings' => 0,
        ];

        return Inertia::render('app/dashboard', [
            'accounts' => $accounts,
            'summary' => $summary,
            'categorySpending' => $categorySpending,
            'recent_transactions' => $recentTransactions,
        ]);
    }
}
