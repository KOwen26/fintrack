<?php

namespace App\Services;

use App\Data\CosmeticData;
use App\Models\Category;

class CategoryService
{
    public function create(array $data): Category
    {
        return Category::create($this->normalizeCosmetics($data));
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($this->normalizeCosmetics($data));

        return $category->fresh();
    }

    public function softDelete(Category $category): void
    {
        $category->delete();
    }

    private function normalizeCosmetics(array $data): array
    {
        if (isset($data['cosmetics'])) {
            $data['cosmetics'] = CosmeticData::from($data['cosmetics'])->toArray();
        }

        return $data;
    }
}
