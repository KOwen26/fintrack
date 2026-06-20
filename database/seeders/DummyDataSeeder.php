<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Date;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DummyDataSeeder extends Seeder
{
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

    private function seedUser(
        string $name,
        string $email,
        Collection $incomeCategories,
        Collection $expenseCategories,
    ): void {
        $user = User::factory()->create(compact('name', 'email'));

        $accountIds = Account::factory()
            ->count(random_int(5, 10))
            ->sequence(fn (): array => [
                'type' => collect(AccountType::cases())->random()->value,
                'name' => $this->randomAccountName(),
                'owner_id' => $user->id,
            ])
            ->create()
            ->pluck('id')
            ->toArray();

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

        Transaction::factory()
            ->count($incomeCount)
            ->income()
            ->sequence(fn (): array => [
                'account_id' => $accountIds[array_rand($accountIds)],
                'category_id' => $incomeCategories->random()->id,
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
            ->sequence(fn (): array => [
                'account_id' => $accountIds[array_rand($accountIds)],
                'category_id' => $expenseCategories->random()->id,
                'created_by' => $user->id,
                'transfer_link_id' => null,
                'amount' => random_int(10, 500) * 1000,
                'transaction_date' => Date::create($year, $month, random_int(1, $daysInMonth), random_int(0, 23), random_int(0, 59)),
                'description' => fake()->sentence(3),
            ])
            ->create();
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
