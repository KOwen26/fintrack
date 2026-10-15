<?php

namespace App\Http\Controllers;

use App\Http\Requests\MonthPeriodRequest;
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
     * Statistics overview — daily income/expense calendar for one month.
     * `?month` (Y-m or Y-m-d) navigates to an explicit month; see
     * MonthPeriodRequest for the resolution and fallback rules.
     */
    public function index(MonthPeriodRequest $request): Response
    {
        return Inertia::render('app/statistics/index', [
            'monthlyFlow' => $this->spendingService->monthlyCashflow($this->user, $request->period()),
        ]);
    }
}
