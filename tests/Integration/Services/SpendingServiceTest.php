<?php

use App\Data\Report\CategorySpendingItemData;
use App\Enums\Category;
use App\Enums\CategoryGroup;
use App\Enums\DatePeriodPreset;
use App\Enums\StatisticDataTier;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Objects\CashflowPoint;
use App\Objects\DatePeriod;
use App\Services\SpendingService;
use Carbon\CarbonImmutable;

function createAccountWithOwner(): Account
{
    $user = User::factory()->create();

    return Account::factory()->create(['owner_id' => $user->id]);
}

function spendingItem(Category $category, CategoryGroup $group, float $total, float $percentage): CategorySpendingItemData
{
    return new CategorySpendingItemData(
        category_id: $category->value,
        group: $group->value,
        name: $category->label(),
        color: $category->decorations()->color,
        icon: $category->decorations()->icon,
        total: $total,
        percentage: $percentage,
    );
}

describe('categorySpending', function (): void {
    it('nests category spending under its group', function (): void {
        $account = createAccountWithOwner();
        Transaction::factory()->expense()->forCategory(Category::GeneralShopping)->create([
            'account_id' => $account->id,
            'amount' => 200_000,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $service = new SpendingService;
        $report = $service->categorySpending(
            $account->owner,
            DatePeriodPreset::ThisMonth,
        );

        expect($report->categories)->toHaveCount(1)
            ->and($report->categories[0])->group->toBe(CategoryGroup::Shopping->value)->name->toBe(CategoryGroup::Shopping->label())->total->toBe(200_000.0)->children->toHaveCount(1)
            ->and($report->categories[0]->children[0])->category_id->toBe(Category::GeneralShopping->value)->name->toBe(Category::GeneralShopping->label())->total->toBe(200_000.0);
    });

    it('returns an empty report when no accounts are given', function (): void {
        $service = new SpendingService;
        $user = User::factory()->create();

        $report = $service->categorySpending(
            $user,
            DatePeriodPreset::ThisMonth,
        );

        expect($report->categories)->toBeEmpty()
            ->and($report->total_expense)->toBe(0.0);
    });

    it('merges sibling category spending into one group', function (): void {
        $account = createAccountWithOwner();

        Transaction::factory()->expense()->forCategory(Category::Fuel)->create([
            'account_id' => $account->id,
            'amount' => 300_000,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        Transaction::factory()->expense()->forCategory(Category::PublicTransport)->create([
            'account_id' => $account->id,
            'amount' => 150_000,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $service = new SpendingService;
        $report = $service->categorySpending(
            $account->owner,
            DatePeriodPreset::ThisMonth,
        );

        expect($report->categories)->toHaveCount(1)
            ->and($report->categories[0])->group->toBe(CategoryGroup::TransportTravel->value)->name->toBe(CategoryGroup::TransportTravel->label())->total->toBe(450_000.0)
            ->and($report->categories[0]->children)->toHaveCount(2)
            ->and(array_column($report->categories[0]->children, 'total'))->toBe([300_000.0, 150_000.0]);
    });

    it('orders groups by total descending', function (): void {
        $account = createAccountWithOwner();

        Transaction::factory()->expense()->forCategory(Category::GeneralShopping)->create([
            'account_id' => $account->id,
            'amount' => 200_000,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        Transaction::factory()->expense()->forCategory(Category::Fuel)->create([
            'account_id' => $account->id,
            'amount' => 300_000,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $service = new SpendingService;
        $report = $service->categorySpending(
            $account->owner,
            DatePeriodPreset::ThisMonth,
        );

        expect($report->categories)->toHaveCount(2)
            ->and($report->categories[0]->group)->toBe(CategoryGroup::TransportTravel->value)
            ->and($report->categories[1]->group)->toBe(CategoryGroup::Shopping->value);
    });
});

describe('rollUpByCategoryGroup', function (): void {
    it('returns empty array for no items', function (): void {
        $service = new SpendingService;

        $result = $service->rollUpByCategoryGroup([], 1000);

        expect($result)->toBeEmpty();
    });

    it('groups a single category under its group without extra nesting', function (): void {
        $service = new SpendingService;

        $items = [spendingItem(Category::DiningOut, CategoryGroup::FoodAndDrinks, 500.0, 50.0)];

        $result = $service->rollUpByCategoryGroup($items, 1000);

        expect($result)->toHaveCount(1)
            ->and($result[0])->group->toBe(CategoryGroup::FoodAndDrinks->value)->name->toBe(CategoryGroup::FoodAndDrinks->label())->total->toBe(500.0)->percentage->toBe(50.0)->children->toHaveCount(1)
            ->and($result[0]->children[0])->category_id->toBe(Category::DiningOut->value)->total->toBe(500.0);
    });

    it('merges sibling categories into one group', function (): void {
        $service = new SpendingService;

        $items = [
            spendingItem(Category::Fuel, CategoryGroup::TransportTravel, 300.0, 30.0),
            spendingItem(Category::PublicTransport, CategoryGroup::TransportTravel, 150.0, 15.0),
        ];

        $result = $service->rollUpByCategoryGroup($items, 1000);

        expect($result)->toHaveCount(1)
            ->and($result[0])->group->toBe(CategoryGroup::TransportTravel->value)->name->toBe(CategoryGroup::TransportTravel->label())->total->toBe(450.0)->percentage->toBe(45.0);

        // Child percentages are relative to the group subtotal (450)
        expect($result[0]->children)->toHaveCount(2);
        expect(array_column($result[0]->children, 'percentage'))->toBe([66.67, 33.33]);
    });

    it('handles multiple groups sorted by total descending', function (): void {
        $service = new SpendingService;

        $items = [
            spendingItem(Category::Fuel, CategoryGroup::TransportTravel, 300.0, 30.0),
            spendingItem(Category::GeneralShopping, CategoryGroup::Shopping, 500.0, 50.0),
        ];

        $result = $service->rollUpByCategoryGroup($items, 1000);

        expect($result)->toHaveCount(2);
        // Shopping comes first (higher total)
        expect($result[0]->group)->toBe(CategoryGroup::Shopping->value);
        expect($result[1]->group)->toBe(CategoryGroup::TransportTravel->value)
            ->and($result[0]->total)->toBe(500.0)
            ->and($result[1]->total)->toBe(300.0);
    });

    it('recalculates children percentages relative to the group subtotal', function (): void {
        $service = new SpendingService;

        $items = [
            spendingItem(Category::Fuel, CategoryGroup::TransportTravel, 300.0, 30.0),
            spendingItem(Category::PublicTransport, CategoryGroup::TransportTravel, 100.0, 10.0),
            spendingItem(Category::ParkingTolls, CategoryGroup::TransportTravel, 100.0, 10.0),
        ];

        $result = $service->rollUpByCategoryGroup($items, 1000);

        expect($result)->toHaveCount(1)
            ->and($result[0]->total)->toBe(500.0)
            ->and($result[0]->children)->toHaveCount(3);
        // 300 / 500 = 60%
        expect($result[0]->children[0]->percentage)->toBe(60.0);
        // 100 / 500 = 20%
        expect($result[0]->children[1]->percentage)->toBe(20.0);
        expect($result[0]->children[2]->percentage)->toBe(20.0);
    });
});

describe('monthlyCashflow', function (): void {
    it('aggregates same-day income and expense into one flow with net', function (): void {
        $user = User::factory()->create();
        $account = Account::factory()->create(['owner_id' => $user->id]);

        Transaction::factory()->income()->create([
            'account_id' => $account->id,
            'amount' => 500_000,
            'transaction_date' => now()->startOfMonth()->addDays(2)->format('Y-m-d'),
        ]);

        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 200_000,
            'transaction_date' => now()->startOfMonth()->addDays(2)->format('Y-m-d'),
        ]);

        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 100_000,
            'transaction_date' => now()->startOfMonth()->addDays(5)->format('Y-m-d'),
        ]);

        $service = new SpendingService;
        $report = $service->monthlyCashflow($user, DatePeriodPreset::ThisMonth);

        expect($report->data)->toHaveCount(2)
            ->and($report->data[0])->toBeInstanceOf(CashflowPoint::class)
            ->and($report->data[0]->date)->toBeInstanceOf(CarbonImmutable::class)
            ->and($report->data[0]->date->toDateString())->toBe(now()->startOfMonth()->addDays(2)->toDateString())
            ->and($report->data[0]->income)->toBe(500_000.0)
            ->and($report->data[0]->expense)->toBe(200_000.0)
            ->and($report->data[0]->net)->toBe(300_000.0)
            ->and($report->data[1]->income)->toBe(0.0)
            ->and($report->data[1]->expense)->toBe(100_000.0)
            ->and($report->data[1]->net)->toBe(-100_000.0)
            ->and($report->total_income)->toBe(500_000.0)
            ->and($report->total_expense)->toBe(300_000.0)
            ->and($report->total_net)->toBe(200_000.0)
            ->and($report->max_abs_net)->toBe(300_000.0)
            ->and($report->max_income)->toBe(500_000.0)
            ->and($report->max_expense)->toBe(200_000.0);
    });

    it('scopes to the own accounts and excludes transfers', function (): void {
        $user = User::factory()->create();
        $account = Account::factory()->create(['owner_id' => $user->id]);
        $transfer = Transfer::factory()->create();

        Transaction::factory()->transferOutflow($transfer)->create([
            'account_id' => $account->id,
            'amount' => 750_000,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        Transaction::factory()->expense()->create([
            'account_id' => Account::factory()->create()->id,
            'amount' => 300_000,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $service = new SpendingService;
        $report = $service->monthlyCashflow($user, DatePeriodPreset::ThisMonth);

        expect($report->data)->toBeEmpty();
    });

    it('ignores transactions outside the period window', function (): void {
        $user = User::factory()->create();
        $account = Account::factory()->create(['owner_id' => $user->id]);

        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 250_000,
            'transaction_date' => now()->startOfMonth()->subDays(3)->format('Y-m-d'),
        ]);

        $service = new SpendingService;
        $report = $service->monthlyCashflow($user, DatePeriodPreset::ThisMonth);

        expect($report->data)->toBeEmpty()
            ->and($report->from->toDateString())->toBe(now()->startOfMonth()->toDateString());
    });

    it('returns an empty report for a user without accounts', function (): void {
        $user = User::factory()->create();

        $service = new SpendingService;
        $report = $service->monthlyCashflow($user, DatePeriodPreset::ThisMonth);

        expect($report->data)->toBeEmpty()
            ->and($report->from->isSameDay(now()->startOfMonth()))->toBeTrue()
            ->and($report->to->isSameDay(now()->endOfMonth()))->toBeTrue()
            ->and($report->total_income)->toBe(0.0)
            ->and($report->total_net)->toBe(0.0)
            ->and($report->max_abs_net)->toBe(0.0);
    });

    it('aggregates an explicit month window from any day within it', function (): void {
        $user = User::factory()->create();
        $account = Account::factory()->create(['owner_id' => $user->id]);

        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 120_000,
            'transaction_date' => '2026-03-10',
        ]);

        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 80_000,
            'transaction_date' => '2026-04-02',
        ]);

        $service = new SpendingService;
        $report = $service->monthlyCashflow($user, DatePeriod::forMonth(new CarbonImmutable('2026-03-15')));

        expect($report->from->toDateString())->toBe('2026-03-01')
            ->and($report->to->toDateString())->toBe('2026-03-31')
            ->and($report->data)->toHaveCount(1)
            ->and($report->data[0]->expense)->toBe(120_000.0)
            ->and($report->total_expense)->toBe(120_000.0);
    });
});

describe('cashflowTrend', function (): void {
    it('aggregates per month, zero-fills gaps, and derives surplus rate', function (): void {
        $this->travelTo(new CarbonImmutable('2026-10-15'));

        $user = User::factory()->create();
        $account = Account::factory()->create(['owner_id' => $user->id]);

        Transaction::factory()->income()->create([
            'account_id' => $account->id,
            'amount' => 1_000_000,
            'transaction_date' => '2026-10-05',
        ]);

        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 400_000,
            'transaction_date' => '2026-10-20',
        ]);

        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 100_000,
            'transaction_date' => '2026-08-02',
        ]);

        $service = new SpendingService;
        $report = $service->cashflowTrend($user, DatePeriodPreset::Last3Months);

        expect($report->from->toDateString())->toBe('2026-08-01')
            ->and($report->to->toDateString())->toBe('2026-10-31')
            ->and($report->data)->toHaveCount(3)
            ->and($report->data[0]->date->toDateString())->toBe('2026-08-01')
            ->and($report->data[0]->expense)->toBe(100_000.0)
            ->and($report->data[0]->rate)->toBe(0.0)
            ->and($report->data[1]->income)->toBe(0.0)
            ->and($report->data[1]->expense)->toBe(0.0)
            ->and($report->data[2]->income)->toBe(1_000_000.0)
            ->and($report->data[2]->net)->toBe(600_000.0)
            ->and($report->data[2]->rate)->toBe(60.0)
            ->and($report->total_income)->toBe(1_000_000.0)
            ->and($report->total_expense)->toBe(500_000.0)
            ->and($report->total_net)->toBe(500_000.0);
    });

    it('scopes to the own accounts and excludes transfers', function (): void {
        $this->travelTo(new CarbonImmutable('2026-10-15'));

        $user = User::factory()->create();
        $account = Account::factory()->create(['owner_id' => $user->id]);
        $transfer = Transfer::factory()->create();

        Transaction::factory()->transferOutflow($transfer)->create([
            'account_id' => $account->id,
            'amount' => 750_000,
            'transaction_date' => '2026-10-04',
        ]);

        Transaction::factory()->expense()->create([
            'account_id' => Account::factory()->create()->id,
            'amount' => 300_000,
            'transaction_date' => '2026-10-06',
        ]);

        $service = new SpendingService;
        $report = $service->cashflowTrend($user, DatePeriodPreset::Last6Months);

        expect($report->data)->toHaveCount(6)
            ->and($report->total_income)->toBe(0.0)
            ->and($report->total_expense)->toBe(0.0)
            ->and($report->total_net)->toBe(0.0);
    });

    it('returns a fully zero-filled span for a user without accounts', function (): void {
        $this->travelTo(new CarbonImmutable('2026-10-15'));

        $user = User::factory()->create();

        $service = new SpendingService;
        $report = $service->cashflowTrend($user, DatePeriodPreset::Last12Months);

        expect($report->data)->toHaveCount(12)
            ->and($report->data[11]->date->toDateString())->toBe('2026-10-01')
            ->and($report->total_income)->toBe(0.0)
            ->and($report->total_net)->toBe(0.0);
    });

    it('bounds an All Time window at the earliest transaction month', function (): void {
        $this->travelTo(new CarbonImmutable('2026-10-15'));

        $user = User::factory()->create();
        $account = Account::factory()->create(['owner_id' => $user->id]);

        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 100_000,
            'transaction_date' => '2024-03-05',
        ]);

        Transaction::factory()->income()->create([
            'account_id' => $account->id,
            'amount' => 1_000_000,
            'transaction_date' => '2026-10-10',
        ]);

        $service = new SpendingService;
        $report = $service->cashflowTrend($user, DatePeriodPreset::All);

        // March 2024 through October 2026 inclusive — 32 monthly buckets.
        expect($report->from->toDateString())->toBe('2024-03-01')
            ->and($report->to->toDateString())->toBe('2026-10-15')
            ->and($report->data)->toHaveCount(32)
            ->and($report->data[0]->date->toDateString())->toBe('2024-03-01')
            ->and($report->data[0]->expense)->toBe(100_000.0)
            ->and($report->data[31]->date->toDateString())->toBe('2026-10-01')
            ->and($report->data[31]->income)->toBe(1_000_000.0)
            ->and($report->total_income)->toBe(1_000_000.0)
            ->and($report->total_expense)->toBe(100_000.0);
    });

    it('reports the current month alone for All Time without history', function (): void {
        $this->travelTo(new CarbonImmutable('2026-10-15'));

        $user = User::factory()->create();
        Account::factory()->create(['owner_id' => $user->id]);

        $service = new SpendingService;
        $report = $service->cashflowTrend($user, DatePeriodPreset::All);

        expect($report->from->toDateString())->toBe('2026-10-01')
            ->and($report->data)->toHaveCount(1)
            ->and($report->data[0]->income)->toBe(0.0);
    });

    it('uses the literal window for a day-anchored preset', function (): void {
        $this->travelTo(new CarbonImmutable('2026-10-15'));

        $user = User::factory()->create();
        $account = Account::factory()->create(['owner_id' => $user->id]);

        // Sep 16 – Oct 15 window touches two partial months; both buckets
        // must survive the walk or the totals would silently drop rows.
        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 200_000,
            'transaction_date' => '2026-09-20',
        ]);

        Transaction::factory()->income()->create([
            'account_id' => $account->id,
            'amount' => 500_000,
            'transaction_date' => '2026-10-05',
        ]);

        $service = new SpendingService;
        $report = $service->cashflowTrend($user, DatePeriodPreset::Last30Days);

        expect($report->from->toDateString())->toBe('2026-09-16')
            ->and($report->data)->toHaveCount(2)
            ->and($report->data[0]->expense)->toBe(200_000.0)
            ->and($report->data[1]->income)->toBe(500_000.0)
            ->and($report->total_expense)->toBe(200_000.0);
    });
});

describe('balanceTrend', function (): void {
    it('walks month-end balances backward from the current total', function (): void {
        $this->travelTo(new CarbonImmutable('2026-10-15'));

        $user = User::factory()->create();
        $account = Account::factory()->create([
            'owner_id' => $user->id,
            'current_balance' => 1_000_000,
        ]);

        // Observer-maintained balance after both rows: 1_000_000 + 500_000 - 300_000 = 1_200_000.
        Transaction::factory()->income()->create([
            'account_id' => $account->id,
            'amount' => 500_000,
            'transaction_date' => '2026-10-08',
        ]);

        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 300_000,
            'transaction_date' => '2026-09-20',
        ]);

        $service = new SpendingService;
        $report = $service->balanceTrend($user, DatePeriodPreset::Last3Months);

        expect($report->current_total)->toBe(1_200_000.0)
            ->and($report->from->toDateString())->toBe('2026-08-01')
            ->and($report->data)->toHaveCount(3)
            ->and($report->data[2]['date'])->toBe('2026-10-31')
            ->and($report->data[2]['balance'])->toBe(1_200_000.0)
            ->and($report->data[2]['net_delta'])->toBe(500_000.0)
            ->and($report->data[1]['date'])->toBe('2026-09-30')
            ->and($report->data[1]['balance'])->toBe(700_000.0)
            ->and($report->data[1]['net_delta'])->toBe(-300_000.0)
            // August reconstructs the pre-transaction balance exactly.
            ->and($report->data[0]['balance'])->toBe(1_000_000.0);
    });

    it('cancels internal transfers out of the monthly deltas', function (): void {
        $this->travelTo(new CarbonImmutable('2026-10-15'));

        $user = User::factory()->create();
        $source = Account::factory()->create(['owner_id' => $user->id]);
        $destination = Account::factory()->create(['owner_id' => $user->id]);
        $transfer = Transfer::factory()->create();

        Transaction::factory()->transferOutflow($transfer)->create([
            'account_id' => $source->id,
            'amount' => 250_000,
            'transaction_date' => '2026-10-06',
        ]);

        Transaction::factory()->transferInflow($transfer)->create([
            'account_id' => $destination->id,
            'amount' => 250_000,
            'transaction_date' => '2026-10-06',
        ]);

        $service = new SpendingService;
        $report = $service->balanceTrend($user, DatePeriodPreset::Last3Months);

        expect($report->current_total)->toBe(0.0)
            ->and($report->data[2]['net_delta'])->toBe(0.0)
            ->and($report->data[0]['balance'])->toBe(0.0);
    });

    it('returns zero balances across the span for a user without accounts', function (): void {
        $this->travelTo(new CarbonImmutable('2026-10-15'));

        $user = User::factory()->create();

        $service = new SpendingService;
        $report = $service->balanceTrend($user, DatePeriodPreset::Last6Months);

        expect($report->data)->toHaveCount(6)
            ->and($report->current_total)->toBe(0.0)
            ->and(array_column($report->data, 'balance'))->toBe([0.0, 0.0, 0.0, 0.0, 0.0, 0.0]);
    });
});

describe('weekdayPattern', function (): void {
    it('compares this week per weekday against the trailing three-month average', function (): void {
        // Frozen Friday: current week runs Mon 2026-10-12 .. Sun 2026-10-18;
        // the baseline window is 2026-08-01 .. 2026-10-31, holding Saturday
        // 14 times and Monday 13 times.
        $this->travelTo(new CarbonImmutable('2026-10-16'));

        $user = User::factory()->create();
        $account = Account::factory()->create(['owner_id' => $user->id]);

        // Baseline fodder — Saturdays and a Monday, all outside this week.
        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 200_000,
            'transaction_date' => '2026-08-01',
        ]);

        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 200_000,
            'transaction_date' => '2026-09-05',
        ]);

        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 130_000,
            'transaction_date' => '2026-08-03',
        ]);

        // Tuesday baseline — two occurrences so this week's Tuesday can sit
        // above its average (the deviation-peak candidate).
        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 100_000,
            'transaction_date' => '2026-08-04',
        ]);

        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 100_000,
            'transaction_date' => '2026-09-08',
        ]);

        // This week: Tuesday (above baseline) and today (heaviest, no baseline).
        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 100_000,
            'transaction_date' => '2026-10-13',
        ]);

        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 150_000,
            'transaction_date' => '2026-10-16',
        ]);

        // Noise: before the baseline window, and income inside the week.
        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 500_000,
            'transaction_date' => '2026-07-01',
        ]);

        Transaction::factory()->income()->create([
            'account_id' => $account->id,
            'amount' => 900_000,
            'transaction_date' => '2026-10-14',
        ]);

        $service = new SpendingService;
        $report = $service->weekdayPattern($user);

        $byWeekday = collect($report->data)->keyBy('weekday');

        expect($report->from->toDateString())->toBe('2026-10-12')
            ->and($report->to->toDateString())->toBe('2026-10-18')
            ->and($report->baseline_from->toDateString())->toBe('2026-08-01')
            ->and($report->baseline_to->toDateString())->toBe('2026-10-31')
            ->and($report->data)->toHaveCount(7)
            ->and($report->total_expense)->toBe(250_000.0)
            // Peaks answer different questions: Friday is heaviest outright,
            // Tuesday is the one running hottest against its baseline
            // (Friday has no baseline, so its deviation is a non-peak 0).
            ->and($report->peak_weekday)->toBe(5)
            ->and($report->peak_deviation_weekday)->toBe(2)
            // Tuesday: spent this week, above its 3-mo average.
            ->and($byWeekday[2]->current)->toBe(100_000.0)
            ->and($byWeekday[2]->baseline)->toBe(15_384.62)  // 200_000 / 13
            ->and($byWeekday[2]->deviation)->toBe(550.0)
            // Friday: today — heaviest, but no baseline to deviate from.
            ->and($byWeekday[5]->current)->toBe(150_000.0)
            ->and($byWeekday[5]->baseline)->toBe(0.0)
            ->and($byWeekday[5]->deviation)->toBe(0.0)
            // Tiers ride the server's rank: the two heaviest spending days
            // step up the ladder, everything else — including zero-current
            // days — stays muted.
            ->and($byWeekday[5]->tier)->toBe(StatisticDataTier::Top)
            ->and($byWeekday[2]->tier)->toBe(StatisticDataTier::Second)
            ->and($byWeekday[6]->tier)->toBe(StatisticDataTier::Rest)
            // Saturday: nothing yet this week vs the 3-mo average.
            ->and($byWeekday[6]->current)->toBe(0.0)
            ->and($byWeekday[6]->baseline)->toBe(28_571.43)  // 400_000 / 14
            ->and($byWeekday[6]->deviation)->toBe(-100.0)
            // Monday: outside this week, averaged over 13 occurrences.
            ->and($byWeekday[1]->baseline)->toBe(10_000.0)   // 130_000 / 13
            ->and($byWeekday[1]->tier)->toBe(StatisticDataTier::Rest);
    });

    it('returns zero-filled weekdays for a user without accounts', function (): void {
        $this->travelTo(new CarbonImmutable('2026-10-16'));

        $user = User::factory()->create();

        $service = new SpendingService;
        $report = $service->weekdayPattern($user);

        expect($report->data)->toHaveCount(7)
            ->and($report->total_expense)->toBe(0.0)
            ->and($report->peak_weekday)->toBeNull()
            ->and($report->peak_deviation_weekday)->toBeNull()
            ->and(array_column($report->data, 'current'))->toBe([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0])
            ->and(array_column($report->data, 'baseline'))->toBe([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0])
            ->and(array_column($report->data, 'tier'))->toBe([
                StatisticDataTier::Rest,
                StatisticDataTier::Rest,
                StatisticDataTier::Rest,
                StatisticDataTier::Rest,
                StatisticDataTier::Rest,
                StatisticDataTier::Rest,
                StatisticDataTier::Rest,
            ]);
    });
});

describe('weeklySpendingAverage', function (): void {
    it('returns the average expense amount per transaction of the current week', function (): void {
        $this->travelTo(new CarbonImmutable('2026-10-15'));

        // Frozen Thursday: week-to-date covers Mon 2026-10-12 .. today.
        $user = User::factory()->create();
        $account = Account::factory()->create(['owner_id' => $user->id]);

        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 100_000,
            'transaction_date' => '2026-10-12',
        ]);
        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 100_000,
            'transaction_date' => '2026-10-13',
        ]);
        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 100_000,
            'transaction_date' => '2026-10-14',
        ]);
        // Last week's spending, income rows, and pre-week rows never count.
        Transaction::factory()->expense()->create([
            'account_id' => $account->id,
            'amount' => 400_000,
            'transaction_date' => '2026-10-05',
        ]);
        Transaction::factory()->income()->create([
            'account_id' => $account->id,
            'amount' => 999_000,
            'transaction_date' => '2026-10-13',
        ]);

        $service = new SpendingService;

        // 300_000 across 3 week rows — AVG(amount), not SUM ÷ elapsed days
        // (a projection would be 300_000 / 4 × 7 = 525_000).
        expect($service->weeklySpendingAverage($user))->toBe(100_000.0);
    });

    it('returns zero for a user without accounts', function (): void {
        $this->travelTo(new CarbonImmutable('2026-10-15'));

        $user = User::factory()->create();

        $service = new SpendingService;

        expect($service->weeklySpendingAverage($user))->toBe(0.0);
    });
});
