<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Provider;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

final class DummyDataSeeder extends Seeder
{
    private Collection $coveredIncomeIds;

    private Collection $coveredExpenseIds;

    /**
     * Seed demo users, each with accounts and $months of activity.
     *
     * Every knob is a named parameter so volumes stay controllable at the
     * call site: DatabaseSeeder keeps its plain $this->call(), while anyone
     * needing different volumes passes overrides through Seeder::call()'s
     * $parameters (e.g. $this->call(DummyDataSeeder::class, false, ['users' => 5])).
     *
     * The per-month mix is: incomes (random within the income bounds) +
     * transfers (2 rows each) + expenses filling the rest of the month's
     * transaction budget. Not idempotent — rerunning duplicates the data.
     *
     * @param  list<string>|null  $userNames  Names for the first N users; extras fall back to fake names.
     */
    public function run(
        int $users = 2,
        ?array $userNames = null,
        int $months = 3,
        int $accountsPerUserMin = 5,
        int $accountsPerUserMax = 10,
        int $transactionsPerMonth = 50,
        int $incomePerMonthMin = 8,
        int $incomePerMonthMax = 15,
        int $transfersPerMonth = 5,
    ): void {
        $userNames ??= ['Alice Johnson', 'Bob Smith'];

        // Child categories only — parents are groupings, not bookable.
        $incomeCategories = Category::whereNotNull('parent_id')
            ->where('type', CategoryType::Input->value)
            ->get();

        $expenseCategories = Category::whereNotNull('parent_id')
            ->where('type', CategoryType::Output->value)
            ->get();

        for ($index = 0; $index < $users; $index++) {
            $name = $userNames[$index] ?? fake()->name();

            $this->seedUser(
                name: $name,
                email: Str::slug($name, '.') . '@example.com',
                months: $months,
                accountsPerUserMin: $accountsPerUserMin,
                accountsPerUserMax: $accountsPerUserMax,
                transactionsPerMonth: $transactionsPerMonth,
                incomePerMonthMin: $incomePerMonthMin,
                incomePerMonthMax: $incomePerMonthMax,
                transfersPerMonth: $transfersPerMonth,
                incomeCategories: $incomeCategories,
                expenseCategories: $expenseCategories,
            );
        }
    }

    private function resetCoverage(): void
    {
        $this->coveredIncomeIds = collect();
        $this->coveredExpenseIds = collect();
    }

    private function seedUser(
        string $name,
        string $email,
        int $months,
        int $accountsPerUserMin,
        int $accountsPerUserMax,
        int $transactionsPerMonth,
        int $incomePerMonthMin,
        int $incomePerMonthMax,
        int $transfersPerMonth,
        Collection $incomeCategories,
        Collection $expenseCategories,
    ): void {
        $this->resetCoverage();

        $user = User::factory()->create(compact('name', 'email'));

        $this->command?->info("Seeded user: {$name} <{$email}>");

        $accountIds = $this->seedAccounts($user, Provider::query()->get(), $accountsPerUserMin, $accountsPerUserMax);

        for ($monthOffset = $months - 1; $monthOffset >= 0; $monthOffset--) {
            $date = Date::now()->startOfMonth()->subMonths($monthOffset);

            $this->seedMonthlyTransactions(
                user: $user,
                accountIds: $accountIds,
                year: $date->year,
                month: $date->month,
                transactionsPerMonth: $transactionsPerMonth,
                incomePerMonthMin: $incomePerMonthMin,
                incomePerMonthMax: $incomePerMonthMax,
                transfersPerMonth: $transfersPerMonth,
                incomeCategories: $incomeCategories,
                expenseCategories: $expenseCategories,
            );
        }
    }

    private function seedMonthlyTransactions(
        User $user,
        array $accountIds,
        int $year,
        int $month,
        int $transactionsPerMonth,
        int $incomePerMonthMin,
        int $incomePerMonthMax,
        int $transfersPerMonth,
        Collection $incomeCategories,
        Collection $expenseCategories,
    ): void {
        $incomeCount = random_int($incomePerMonthMin, $incomePerMonthMax);
        // Each transfer books as a pair (out + in), consuming 2 slots.
        $transferPairCount = min($transfersPerMonth, intdiv(max(0, $transactionsPerMonth - $incomeCount), 2));
        $expenseCount = max(0, $transactionsPerMonth - $incomeCount - $transferPairCount * 2);

        $incomePickList = $this->buildPickList($incomeCategories, $this->coveredIncomeIds, $incomeCount);
        $expensePickList = $this->buildPickList($expenseCategories, $this->coveredExpenseIds, $expenseCount);

        Transaction::factory()
            ->count($incomeCount)
            ->income()
            ->sequence(fn (Sequence $seq): array => [
                'account_id' => $accountIds[array_rand($accountIds)],
                'category_id' => $incomePickList[$seq->index % $incomePickList->count()]->id,
                'created_by' => $user->id,
                'amount' => random_int(10, 500) * 1000,
                'transaction_date' => $this->randomDateInMonth($year, $month),
                'description' => fake()->sentence(3),
            ])
            ->create();

        Transaction::factory()
            ->count($expenseCount)
            ->expense()
            ->sequence(fn (Sequence $seq): array => [
                'account_id' => $accountIds[array_rand($accountIds)],
                'category_id' => $expensePickList[$seq->index % $expensePickList->count()]->id,
                'created_by' => $user->id,
                'amount' => random_int(10, 500) * 1000,
                'transaction_date' => $this->randomDateInMonth($year, $month),
                'description' => fake()->sentence(3),
            ])
            ->create();

        for ($index = 0; $index < $transferPairCount; $index++) {
            $this->seedTransfer($user, $accountIds, $year, $month);
        }
    }

    /**
     * Book one transfer as an aggregate + member rows: a Transfer row with a
     * source row and destination row on distinct accounts of the same user
     * (same amount and date, uncategorized), plus a fee row ~30% of the time.
     */
    private function seedTransfer(User $user, array $accountIds, int $year, int $month): void
    {
        if (count($accountIds) < 2) {
            return;
        }

        $keys = array_rand($accountIds, 2);
        [$fromAccountId, $toAccountId] = [$accountIds[$keys[0]], $accountIds[$keys[1]]];
        $amount = random_int(10, 500) * 1000;
        $date = $this->randomDateInMonth($year, $month)->toDateString();
        $withFee = fake()->boolean(30);

        $transfer = Transfer::factory()->create([
            'created_by' => $user->id,
            'amount' => $amount,
            'fee_amount' => $withFee ? random_int(1, 10) * 500 : null,
            'transaction_date' => $date,
            'description' => fake()->sentence(3),
        ]);

        Transaction::factory()->transferOutflow($transfer)->create([
            'account_id' => $fromAccountId,
            'created_by' => $user->id,
            'amount' => $amount,
            'transaction_date' => $date,
            'description' => $transfer->description,
        ]);

        Transaction::factory()->transferInflow($transfer)->create([
            'account_id' => $toAccountId,
            'created_by' => $user->id,
            'amount' => $amount,
            'transaction_date' => $date,
            'description' => $transfer->description,
        ]);

        if ($withFee) {
            Transaction::factory()->transferFee($transfer)->create([
                'account_id' => $fromAccountId,
                'created_by' => $user->id,
                'amount' => $transfer->fee_amount,
                'transaction_date' => $date,
                'description' => 'Transfer fee',
            ]);
        }
    }

    private function randomDateInMonth(int $year, int $month): CarbonInterface
    {
        return Date::create(
            $year,
            $month,
            random_int(1, Date::create($year, $month)->daysInMonth),
            random_int(0, 23),
            random_int(0, 59),
        );
    }

    /**
     * Pick the month's category list: uncovered categories first (so every
     * category appears before repeats), then random filler.
     *
     * @param  Collection<int, Category>  $allCategories
     * @param  Collection<int, int>  $coveredIds
     *
     * @return Collection<int, Category>
     */
    private function buildPickList(Collection $allCategories, Collection $coveredIds, int $count): Collection
    {
        if ($allCategories->isEmpty()) {
            return collect();
        }

        $uncovered = $allCategories->reject(fn (Category $cat): bool => $coveredIds->contains($cat->id));

        /** @var Collection<int, Category> $pickList */
        $pickList = $uncovered->shuffle()
            ->merge($allCategories->shuffle())
            ->take($count);

        $coveredIds->push(
            ...$pickList->intersectByKeys($uncovered)->pluck('id'),
        );

        return $pickList;
    }

    /**
     * @param  Collection<int, Provider>  $providers
     *
     * @return list<int>
     */
    private function seedAccounts(User $user, Collection $providers, int $min, int $max): array
    {
        $accounts = collect();

        foreach (range(1, random_int(min($min, $max), max($min, $max))) as $ignored) {
            $factory = Account::factory()->state([
                'type' => collect(AccountType::cases())->random(),
                'owner_id' => $user->id,
            ]);

            if ($providers->isNotEmpty() && fake()->boolean(60)) {
                $accounts->push($factory->forProvider($providers->random())->create());

                continue;
            }

            $accounts->push($factory->create(['name' => $this->randomAccountName()]));
        }

        return $accounts->pluck('id')->toArray();
    }

    private function randomAccountName(): string
    {
        $names = [
            'debit_account' => ['Primary Checking', 'Daily Expense', 'Main Savings', 'Secondary Account', 'Payroll Account'],
            'credit_card' => ['Platinum Card', 'Gold Visa', 'Travel Rewards', 'Cashback Card'],
            'cash_wallet' => ['Cash Wallet', 'Petty Cash', 'Emergency Cash'],
            'e_wallet' => ['GoPay', 'OVO', 'Dana', 'ShopeePay'],
            'investment' => ['Stock Portfolio', 'Mutual Funds', 'Crypto Wallet', 'RDN'],
        ];

        $pools = array_merge(...array_values($names));

        return $pools[array_rand($pools)];
    }
}
