<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;

class CategoryService
{
    public function create(User $user, array $data): Category
    {
        return Category::create([...$data, 'user_id' => $user->id]);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->fresh();
    }

    public function softDelete(Category $category): void
    {
        $category->delete();
    }
}
