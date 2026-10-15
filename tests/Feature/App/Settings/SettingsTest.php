<?php

use App\Models\User;

test('renders the settings hub', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('settings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('app/settings/index'));
});

test('renders the appearance page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('appearance'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('app/settings/appearance'));
});
