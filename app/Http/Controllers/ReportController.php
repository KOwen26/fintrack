<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Models\Account;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    /**
     * Reports dashboard — trend + category leak summary for the account.
     */
    public function index(Request $request, Account $account): Response
    {
        $this->authorize('view', $account);

        [$from, $to] = $this->parseDateRange($request);

        $trend = $this->reportService->trend($account, 6);
        $categoryLeak = $this->reportService->categorySpending($account, $from, $to);

        return Inertia::render('reports/index', [
            'account' => $account,
            'trend' => $trend,
            'category_leak' => $categoryLeak,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    /**
     * Income vs Expense Trend — configurable number of months.
     */
    public function trend(Request $request, Account $account): Response
    {
        $this->authorize('view', $account);

        $months = (int) $request->query('months', 6);
        $months = max(1, min(24, $months)); // clamp: 1–24

        $trend = $this->reportService->trend($account, $months);

        return Inertia::render('reports/trend', [
            'account' => $account,
            'trend' => $trend,
            'months' => $months,
        ]);
    }

    /**
     * Category Leak — expense share by category for the selected period.
     */
    public function categoryLeak(Request $request, Account $account): Response
    {
        $this->authorize('view', $account);

        [$from, $to] = $this->parseDateRange($request);

        $data = $this->reportService->categorySpending($account, $from, $to);

        return Inertia::render('reports/category-leak', [
            'account' => $account,
            'category_leak' => $data,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    /**
     * Joint Contribution Split — only meaningful for joint accounts.
     * Personal accounts receive is_joint: false and an empty-state page.
     */
    public function contributionSplit(Request $request, Account $account): Response
    {
        $this->authorize('view', $account);

        [$from, $to] = $this->parseDateRange($request);

        $data = $this->reportService->contributionSplit($account, $from, $to);

        return Inertia::render('reports/contribution-split', [
            'account' => $account,
            'contribution_split' => $data,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    /**
     * Credit Utilization — always live, only relevant for credit_card accounts.
     */
    public function creditUtilization(Account $account): Response
    {
        $this->authorize('view', $account);

        abort_unless(
            $account->type === AccountType::CreditCard,
            422,
            'Credit utilization is only available for credit card accounts.'
        );

        $data = $this->reportService->creditUtilization($account);

        return Inertia::render('reports/credit-utilization', [
            'account' => $account,
            'credit_utilization' => $data,
        ]);
    }

    /**
     * Fixed vs Variable — expense split by is_fixed_cost for the selected period.
     */
    public function fixedVsVariable(Request $request, Account $account): Response
    {
        $this->authorize('view', $account);

        [$from, $to] = $this->parseDateRange($request);

        $data = $this->reportService->fixedVsVariable($account, $from, $to);

        return Inertia::render('reports/fixed-vs-variable', [
            'account' => $account,
            'fixed_vs_variable' => $data,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    /**
     * Parse `from` / `to` query params. Defaults to current calendar month.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function parseDateRange(Request $request): array
    {
        $from = $request->query('from')
            ? Date::parse($request->query('from'))->startOfDay()
            : Date::now()->startOfMonth();

        $to = $request->query('to')
            ? Date::parse($request->query('to'))->endOfDay()
            : Date::now()->endOfMonth();

        return [$from, $to];
    }
}
