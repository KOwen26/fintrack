<?php

namespace App\Services;

use App\Data\DecorationData;
use App\Models\Category;
use App\Models\DecorationColor;
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
        if (isset($data['decorations']) && is_array($data['decorations'])) {
            $decorationData = $data['decorations'];

            if (isset($decorationData['icon']) && is_string($decorationData['icon'])) {
                $decorationData['icon'] = ['id' => substr($decorationData['icon'], 3), 'value' => $decorationData['icon']];
            }

            if (isset($decorationData['color']) && is_string($decorationData['color'])) {
                $slug = DecorationColor::where('hex', $decorationData['color'])->first()?->slug;
                $decorationData['color'] = ['id' => $slug ?? $decorationData['color'], 'value' => $decorationData['color']];
            }

            $data['decorations'] = DecorationData::from($decorationData)->toArray();
        }

        return $data;
    }
}
