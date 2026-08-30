<?php

namespace App\Services;

use App\Models\User;

class UserThemeService
{
    public function update(User $user, string $theme): void
    {
        $user->update(['theme_preference' => $theme]);
    }
}
