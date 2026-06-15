<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists categories for authenticated user with children', function (): void {
    $user = User::factory()->create();
    $parent = Category::factory()->create(['user_id' => $user->id]);
    Category::factory()->child($parent->id)->create(['user_id' => $user->id]);
    Category::factory()->create(); // another user

    $this->actingAs($user)->get(route('categories.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('categories/index')
            ->has('categories', 1)
            ->has('categories.0.children', 1)
        );
});

it('stores a top-level category', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('categories.store'), [
        'name' => 'Groceries',
        'icon' => 'ph:shopping-cart-bold',
        'color' => '#f97316',
        'is_fixed_cost' => false,
        'parent_id' => null,
    ])->assertRedirect();

    expect(Category::where('name', 'Groceries')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('prevents deleting another user category', function (): void {
    $user = User::factory()->create();
    $otherCategory = Category::factory()->create();

    $this->actingAs($user)->delete(route('categories.destroy', $otherCategory))
        ->assertForbidden();
});

it('soft-deletes a category', function (): void {
    $user = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->delete(route('categories.destroy', $category))
        ->assertRedirect();

    expect(Category::find($category->id))->toBeNull();
    expect(Category::withTrashed()->find($category->id))->not->toBeNull();
});
