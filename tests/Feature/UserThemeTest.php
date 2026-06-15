<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('updates the user theme preference', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('settings.theme.update'), ['theme' => 'dark'])
        ->assertRedirect();

    expect($user->fresh()->theme_preference)->toBe('dark');
});

it('clears theme preference when changed', function (): void {
    $user = User::factory()->create(['theme_preference' => 'dark']);

    $this->actingAs($user)->put(route('settings.theme.update'), ['theme' => 'light'])
        ->assertRedirect();

    expect($user->fresh()->theme_preference)->toBe('light');
});

it('rejects invalid theme values that exceed max length', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('settings.theme.update'), ['theme' => str_repeat('a', 51)])
        ->assertSessionHasErrors('theme');
});
