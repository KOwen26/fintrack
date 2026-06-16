<?php

namespace App\Services;

use App\Models\TransactionPreset;
use App\Models\User;

class TransactionPresetService
{
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
