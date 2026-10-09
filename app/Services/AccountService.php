<?php

namespace App\Services;

use App\Data\DecorationData;
use App\Data\Transaction\TransactionData;
use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class AccountService
{
    public function __construct(private TransactionService $transactionService) {}

    public static function getAccountsByUser(User $user): Collection
    {
        return Account::query()
            ->where('owner_id', $user->id)
            ->notArchived()
            // ->shareable()
            ->with('provider')
            ->get();
    }

    public static function getArchivedAccountsByUser(User $user): Collection
    {
        return Account::query()
            ->where('owner_id', $user->id)
            ->archived()
            // ->shareable()
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
        return DB::transaction(function () use ($user, $data): Account {
            $account = Account::create([...$this->normalizeDecorations($data), 'owner_id' => $user->id]);

            if ((float) $account->initial_balance > 0) {
                $this->syncInitialBalance($account);
            }

            return $account;
        });
    }

    public function update(Account $account, array $data): Account
    {
        return DB::transaction(function () use ($account, $data): Account {
            $account->update($this->normalizeDecorations($data));

            if ($account->wasChanged('initial_balance')) {
                $this->syncInitialBalance($account);
            }

            return $account->fresh();
        });
    }

    /**
     * Ensure exactly one live opening row matches the account's display
     * initial_balance. All row mechanics delegate to TransactionService —
     * this method only decides create / update-in-place / soft-delete.
     */
    public function syncInitialBalance(Account $account): void
    {
        $amount = (float) $account->initial_balance;
        $categoryId = CategoryService::initialBalanceCategoryId();

        $row = Transaction::query()
            ->where('account_id', $account->id)
            ->where('category_id', $categoryId)
            ->first();

        if ($amount <= 0) {
            if ($row !== null) {
                $this->transactionService->softDelete($row);
            }

            return;
        }

        $data = new TransactionData(
            account_id: $account->id,
            type: TransactionType::Income,
            amount: $amount,
            transaction_date: ($row?->transaction_date ?? $account->created_at)->toDateString(),
            category_id: $categoryId,
            description: 'Initial balance',
        );

        if ($row === null) {
            $this->transactionService->create($account->owner, $data);

            return;
        }

        $this->transactionService->update($row, $data);
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
