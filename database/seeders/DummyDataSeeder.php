<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Provider;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

class DummyDataSeeder extends Seeder
{
    private Collection $coveredIncomeIds;

    private Collection $coveredExpenseIds;

    public function run(): void
    {
        $incomeCategories = Category::whereNotNull('parent_id')
            ->where('type', 'input')
            ->get()
            ->keyBy('name');

        $expenseCategories = Category::whereNotNull('parent_id')
            ->where('type', 'output')
            ->get()
            ->keyBy('name');

        $this->seedUser('Alice Johnson', 'alice@example.com', $incomeCategories, $expenseCategories);
        $this->seedUser('Bob Smith', 'bob@example.com', $incomeCategories, $expenseCategories);
    }

    private function resetCoverage(): void
    {
        $this->coveredIncomeIds = collect();
        $this->coveredExpenseIds = collect();
    }

    private function seedUser(
        string $name,
        string $email,
        Collection $incomeCategories,
        Collection $expenseCategories,
    ): void {
        $this->resetCoverage();

        $user = User::factory()->create(compact('name', 'email'));

        $accountIds = $this->seedAccounts($user, Provider::query()->get());

        for ($monthOffset = 2; $monthOffset >= 0; $monthOffset--) {
            $date = Date::now()->startOfMonth()->subMonths($monthOffset);

            $this->seedMonthlyTransactions(
                user: $user,
                accountIds: $accountIds,
                year: $date->year,
                month: $date->month,
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
        Collection $incomeCategories,
        Collection $expenseCategories,
    ): void {
        $daysInMonth = Date::create($year, $month)->daysInMonth;
        $incomeCount = random_int(8, 15);
        $expenseCount = 50 - $incomeCount;

        $incomePickList = $this->buildPickList($incomeCategories, $this->coveredIncomeIds, $incomeCount);
        $expensePickList = $this->buildPickList($expenseCategories, $this->coveredExpenseIds, $expenseCount);

        Transaction::factory()
            ->count($incomeCount)
            ->income()
            ->sequence(fn (Sequence $seq): array => [
                'account_id' => $accountIds[array_rand($accountIds)],
                'category_id' => $incomePickList[$seq->index % $incomePickList->count()]->id,
                'created_by' => $user->id,
                'transfer_link_id' => null,
                'amount' => random_int(10, 500) * 1000,
                'transaction_date' => Date::create($year, $month, random_int(1, $daysInMonth), random_int(0, 23), random_int(0, 59)),
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
                'transfer_link_id' => null,
                'amount' => random_int(10, 500) * 1000,
                'transaction_date' => Date::create($year, $month, random_int(1, $daysInMonth), random_int(0, 23), random_int(0, 59)),
                'description' => fake()->sentence(3),
            ])
            ->create();
    }

    /**
     * @param  Collection<int, Category>  $allCategories
     * @param  Collection<int, int>  $coveredIds
     *
     * @return Collection<int, Category>
     */
    private function buildPickList(Collection $allCategories, Collection $coveredIds, int $count): Collection
    {
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
    private function seedAccounts(User $user, Collection $providers): array
    {
        $accounts = collect();

        foreach (range(1, random_int(5, 10)) as $ignored) {
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
