<?php

namespace App\Http\Controllers;

use App\Models\Account;
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
     * Dashboard overview — category spending across all accounts for the current month.
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

        return Inertia::render('dashboard/dashboard', [
            'category_spending' => $categorySpending,
        ]);
    }
}
