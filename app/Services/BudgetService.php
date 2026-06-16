<?php

namespace App\Services;

use App\Data\BudgetStatusData;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    public function upsert(Account $account, array $data): Budget
    {
        return Budget::updateOrCreate([
            'account_id' => $account->id,
            'category_id' => $data['category_id'],
            'year' => $data['year'],
            'month' => $data['month'],
        ], [
            'limit_amount' => $data['limit_amount'],
        ]);
    }

    public function update(Budget $budget, array $data): Budget
    {
        $budget->update(['limit_amount' => $data['limit_amount']]);

        return $budget->fresh();
    }

    public function softDelete(Budget $budget): void
    {
        $budget->delete();
    }

    public function calculateStatus(Account $account, Category $category, int $year, int $month): BudgetStatusData
    {
        $budget = Budget::where('account_id', $account->id)
            ->where('category_id', $category->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $limitAmount = $budget ? (float) $budget->limit_amount : 0;

        $spend = (float) DB::table('transactions')
            ->selectRaw('COALESCE(SUM(amount), 0) AS spend')
            ->where('account_id', $account->id)
            ->where('category_id', $category->id)
            ->whereIn('type', ['expense', 'fee'])
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->whereNull('deleted_at')
            ->value('spend');

        $percentage = $limitAmount > 0 ? round(($spend / $limitAmount) * 100, 2) : 0;

        $status = match (true) {
            $percentage >= 100 => 'over_budget',
            $percentage >= 80 => 'at_risk',
            default => 'on_track',
        };

        return new BudgetStatusData(
            limit_amount: (string) $limitAmount,
            spend: (string) $spend,
            percentage: $percentage,
            status: $status,
        );
    }
}
