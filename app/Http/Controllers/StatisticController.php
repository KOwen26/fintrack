<?php

namespace App\Http\Controllers;

use App\Enums\DatePeriodPreset;
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
     * Statistics overview — current-month daily income/expense calendar.
     */
    public function index(): Response
    {
        return Inertia::render('app/statistics/index', [
            'monthlyFlow' => $this->spendingService->monthlyFlow($this->user, DatePeriodPreset::ThisMonth),
        ]);
    }
}
