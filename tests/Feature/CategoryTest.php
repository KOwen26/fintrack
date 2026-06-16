<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists categories with children', function (): void {
    $user = User::factory()->create();
    $parent = Category::factory()->create();
    Category::factory()->child($parent->id)->create();
    Category::factory()->create();

    $this->actingAs($user)->get(route('categories.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('categories/index')
            ->has('categories', 2)
            ->has('categories.0.children', 1)
        );
});

it('stores a top-level category', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('categories.store'), [
        'name' => 'Groceries',
        'type' => 'output',
        'order' => '0.100',
        'cosmetics' => [
            'icon' => 'ph:shopping-cart-bold',
            'color' => '#f97316',
        ],
        'is_fixed_cost' => false,
        'parent_id' => null,
    ])->assertRedirect();

    $category = Category::where('name', 'Groceries')->first();

    expect($category)->not->toBeNull();
    expect($category->cosmetics)->toMatchArray(['icon' => 'ph:shopping-cart-bold', 'color' => '#f97316']);
});

it('allows deleting a category when authenticated', function (): void {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($user)->delete(route('categories.destroy', $category))
        ->assertRedirect();
});

it('soft-deletes a category', function (): void {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($user)->delete(route('categories.destroy', $category))
        ->assertRedirect();

    expect(Category::find($category->id))->toBeNull();
    expect(Category::withTrashed()->find($category->id))->not->toBeNull();
});
