<?php

namespace App\Services;

use App\Data\DecorationData;
use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryService
{
    /**
     * The dedicated bookkeeping category for account opening balances.
     * Find-only — presence is guaranteed by CategorySeeder; never creates.
     */
    public static function initialBalanceCategoryId(): ?int
    {
        return Category::query()
            ->whereNotNull('parent_id')
            ->where('name', 'Initial Balance')
            ->value('id');
    }

    public static function getCategories(): Collection
    {
        return Category::query()->levelChildren()->get();
    }

    /** Child categories selectable in the transaction form — excludes the Initial Balance bookkeeping category. */
    public static function getBookableCategories(): Collection
    {
        return self::getCategories()
            ->reject(fn (Category $category): bool => $category->id === self::initialBalanceCategoryId())
            ->values();
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
