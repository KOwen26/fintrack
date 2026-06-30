<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SpendingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly SpendingService $spendingService,
    ) {}

    /**
     * Dashboard overview — summary, recent transactions, accounts, and category spending.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $accountIds = Account::where('owner_id', $user->id)
            ->notArchived()
            ->pluck('id');

        $now = Date::now();
        $from = $now->startOfMonth()->toDateString();
        $to = $now->endOfMonth()->toDateString();

        $categorySpending = $accountIds->isNotEmpty()
            ? $this->spendingService->globalCategorySpending($accountIds->all(), $from, $to)
            : null;

        $totalBalance = (float) Account::where('owner_id', $user->id)
            ->notArchived()
            ->sum('current_balance');

        $monthlyIncome = (float) Transaction::whereIn('account_id', $accountIds)
            ->whereIn('type', TransactionType::inflows())
            ->whereBetween('transaction_date', [$from, $to])
            ->whereNull('deleted_at')
            ->sum('amount');

        $monthlyExpenses = (float) Transaction::whereIn('account_id', $accountIds)
            ->whereIn('type', TransactionType::outflows())
            ->whereBetween('transaction_date', [$from, $to])
            ->whereNull('deleted_at')
            ->sum('amount');

        $recentTransactions = Transaction::whereIn('account_id', $accountIds)
            ->with(['account', 'category'])
            ->latest('transaction_date')
            ->take(5)
            ->get();

        $accounts = Account::where('owner_id', $user->id)
            ->notArchived()
            ->with('provider')
            ->get();

        return Inertia::render('dashboard/dashboard', [
            'category_spending' => $categorySpending,
            'summary' => [
                'total_balance' => $totalBalance,
                'monthly_income' => $monthlyIncome,
                'monthly_expenses' => $monthlyExpenses,
                'monthly_savings' => max($monthlyIncome - $monthlyExpenses, 0),
            ],
            'recent_transactions' => $recentTransactions,
            'accounts' => $accounts,
        ]);
    }
}
