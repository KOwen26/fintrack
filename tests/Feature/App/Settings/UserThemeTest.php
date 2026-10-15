<?php

use App\Models\User;

test('updates the user theme preference', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('settings.theme.update'), ['theme' => 'dark'])
        ->assertRedirect();

    expect($user->fresh()->theme_preference)->toBe('dark');
});

test('clears theme preference when changed', function (): void {
    $user = User::factory()->create(['theme_preference' => 'dark']);

    $this->actingAs($user)->put(route('settings.theme.update'), ['theme' => 'light'])
        ->assertRedirect();

    expect($user->fresh()->theme_preference)->toBe('light');
});

test('rejects invalid theme values that exceed max length', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('settings.theme.update'), ['theme' => str_repeat('a', 51)])
        ->assertSessionHasErrors('theme');
});
