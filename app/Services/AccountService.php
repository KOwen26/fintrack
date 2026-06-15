<?php

namespace App\Services;

use App\Models\Account;
use App\Models\User;

class AccountService
{
    public function create(User $user, array $data): Account
    {
        return Account::create([...$data, 'owner_id' => $user->id]);
    }

    public function update(Account $account, array $data): Account
    {
        $account->update($data);

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
}
