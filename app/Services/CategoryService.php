<?php

namespace App\Services;

use App\Data\DecorationData;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Collection;

class CategoryService
{
    public static function getCategories(): Collection
    {
        return Category::query()->levelChildren()->get();
    }

    public static function getGroupedCategories(): Collection
    {
        return Category::query()
            ->levelParent()
            ->with('children')
            ->get()
            ->map(fn ($parent): array => [
                'id' => $parent->id,
                'name' => $parent->name,
                'type' => $parent->type->value,
                'decorations' => $parent->decorations,
                'options' => $parent->children->map(fn ($child): array => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'decorations' => $child->decorations,
                ]),
            ]);
    }

    public function getRootCategories(?User $user = null): Collection
    {
        return Category::query()
            ->whereNull('parent_id')
            ->with('children')
            ->get();
    }

    public function create(array $data): Category
    {
        return Category::create($this->normalizeDecorations($data));
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($this->normalizeDecorations($data));

        return $category->fresh();
    }

    public function softDelete(Category $category): void
    {
        $category->delete();
    }

    private function normalizeDecorations(array $data): array
    {
        if (isset($data['decorations'])) {
            $data['decorations'] = DecorationData::from($data['decorations'])->toArray();
        }

        return $data;
    }
}
