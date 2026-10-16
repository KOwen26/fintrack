<?php

namespace App\Http\Controllers;

use App\Data\Report\BalanceTrendReportData;
use App\Data\Report\CashflowTrendReportData;
use App\Data\Report\MonthlyCashflowReportData;
use App\Data\Report\WeekdayPatternReportData;
use App\Http\Requests\StatisticsIndexRequest;
use App\Models\User;
use App\Services\SpendingService;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final class StatisticController extends Controller
{
    public function __construct(
        #[CurrentUser()] private readonly ?User $user,
        private readonly SpendingService $spendingService,
    ) {}

    /**
     * Statistics overview — daily income/expense calendar for one month plus
     * the trend cards. Every report prop is deferred: the page paints with
     * skeletons and each card streams in, and a partial reload of one prop
     * never evaluates the other reports. `?month` navigates the calendar
     * (see MonthPeriodRequest); `?trend` picks the cashflow card's
     * DatePeriodPreset; `?balance` sets the balance card's rolling span.
     */
    public function index(StatisticsIndexRequest $request): Response
    {
        return Inertia::render('app/statistics/index', [
            'monthlyCashflow' => Inertia::defer(
                fn (): MonthlyCashflowReportData => $this->spendingService->monthlyCashflow($this->user, $request->period())
            ),
            'cashflowTrend' => Inertia::defer(
                fn (): CashflowTrendReportData => $this->spendingService->cashflowTrend($this->user, $request->trendPreset())
            ),
            'balanceTrend' => Inertia::defer(
                fn (): BalanceTrendReportData => $this->spendingService->balanceTrend($this->user, $request->balanceSpan())
            ),
            'weekdayPattern' => Inertia::defer(fn (): WeekdayPatternReportData => $this->spendingService->weekdayPattern($this->user)),
        ]);
    }
}
