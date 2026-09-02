<?php

namespace App\Services;

use App\Data\DecorationData;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Collection;

class AccountService
{
    public static function getAccountsByUser(User $user): Collection
    {
        return Account::query()
            ->where('owner_id', $user->id)
            ->notArchived()
            ->shareable()
            ->with('provider')
            ->get();
    }

    public static function summarize(Collection $accounts): array
    {
        $totalBalance = (float) $accounts->sum('current_balance');
        $totalAccounts = $accounts->count();

        $oldest = $accounts->sortBy('created_at')->first();
        $oldestAccountYears = $oldest
            ? (int) $oldest->created_at->diffInYears(now())
            : null;

        return [
            'total_balance' => $totalBalance,
            'total_accounts' => $totalAccounts,
            'oldest_account_years' => $oldestAccountYears,
            'available_balance' => self::balanceForTypes($accounts, [
                AccountType::DebitAccount,
                AccountType::CashWallet,
                AccountType::EWallet,
            ]),
            'investment_balance' => self::balanceForTypes($accounts, [AccountType::Investment]),
        ];
    }

    /**
     * Sum current balances across accounts of the given types.
     *
     * @param  Collection<int, Account>  $accounts
     * @param  list<AccountType>  $types
     */
    private static function balanceForTypes(Collection $accounts, array $types): float
    {
        return (float) $accounts
            ->filter(fn (Account $account): bool => in_array($account->type, $types, true))
            ->sum('current_balance');
    }

    public function getTransferEligibleAccounts(?Account $excludeAccount = null): Collection
    {
        return Account::query()
            ->notArchived()
            ->when($excludeAccount, fn ($q) => $q->where('id', '!=', $excludeAccount->id))
            ->get();
    }

    public function create(User $user, array $data): Account
    {
        return Account::create([...$this->normalizeDecorations($data), 'owner_id' => $user->id]);
    }

    public function update(Account $account, array $data): Account
    {
        $account->update($this->normalizeDecorations($data));

        return $account->fresh();
    }

    public function archive(Account $account): Account
    {
        $account->update(['archived_at' => now()]);

        return $account->fresh();
    }

    public function restore(Account $account): Account
    {
        $account->update(['archived_at' => null]);

        return $account->fresh();
    }

    public function softDelete(Account $account): void
    {
        $account->delete();
    }

    private function normalizeDecorations(array $data): array
    {
        if (isset($data['decorations'])) {
            $data['decorations'] = DecorationData::from($data['decorations'])->toArray();
        }

        return $data;
    }
}
