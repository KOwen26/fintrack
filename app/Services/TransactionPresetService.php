<?php

namespace App\Services;

use App\Models\TransactionPreset;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class TransactionPresetService
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly CategoryService $categoryService,
    ) {}

    public function getUserPresets(User $user): Collection
    {
        return TransactionPreset::query()
            ->where('user_id', $user->id)
            ->with(['defaultCategory', 'defaultSourceAccount', 'defaultDestinationAccount'])
            ->get();
    }

    public function getUserAccounts(User $user): Collection
    {
        return $this->accountService->getTransferEligibleAccounts($user);
    }

    public function getUserCategories(User $user): Collection
    {
        return $this->categoryService->getRootCategories($user);
    }

    public function create(User $user, array $data): TransactionPreset
    {
        return TransactionPreset::create([
            ...$data,
            'user_id' => $user->id,
        ]);
    }

    public function update(TransactionPreset $preset, array $data): TransactionPreset
    {
        $preset->update($data);

        return $preset->fresh();
    }

    public function softDelete(TransactionPreset $preset): void
    {
        $preset->delete();
    }
}
