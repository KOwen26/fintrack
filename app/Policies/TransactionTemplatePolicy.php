<?php

namespace App\Policies;

use App\Models\TransactionTemplate;
use App\Models\User;

/**
 * Templates are user-private: shareable-account viewers never see them.
 */
final class TransactionTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TransactionTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TransactionTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }

    public function delete(User $user, TransactionTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }
}
