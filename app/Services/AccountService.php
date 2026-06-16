<?php

namespace App\Services;

use App\Data\CosmeticData;
use App\Models\Account;
use App\Models\User;

class AccountService
{
    public function create(User $user, array $data): Account
    {
        return Account::create([...$this->normalizeCosmetics($data), 'owner_id' => $user->id]);
    }

    public function update(Account $account, array $data): Account
    {
        $account->update($this->normalizeCosmetics($data));

        return $account->fresh();
    }

    private function normalizeCosmetics(array $data): array
    {
        if (! isset($data['cosmetics'])) {
            return $data;
        }

        $data['cosmetics'] = CosmeticData::from($data['cosmetics'])->toArray();

        return $data;
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
