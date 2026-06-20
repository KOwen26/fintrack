<?php

namespace App\Services;

use App\Data\DecorationData;
use App\Models\Account;
use App\Models\DecorationColor;
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
        if (! isset($data['decorations'])) {
            return $data;
        }

        $decorationData = $data['decorations'];

        if (isset($decorationData['icon']) && is_string($decorationData['icon'])) {
            $decorationData['icon'] = ['id' => substr($decorationData['icon'], 3), 'value' => $decorationData['icon']];
        }

        if (isset($decorationData['color']) && is_string($decorationData['color'])) {
            $slug = DecorationColor::where('hex', $decorationData['color'])->first()?->slug;
            $decorationData['color'] = ['id' => $slug ?? $decorationData['color'], 'value' => $decorationData['color']];
        }

        $data['decorations'] = DecorationData::from($decorationData)->toArray();

        return $data;
    }
}
