<?php

use App\Models\User;

describe('Password Confirmation', function (): void {
    test('renders the confirm password page', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('auth.password.confirm'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/confirm-password'));
    });

    test('confirms password with the correct current password', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('auth.password.confirm'), ['password' => 'password'])
            ->assertRedirect();

        $this->assertCredentials(['email' => $user->email, 'password' => 'password']);
    });

    test('rejects confirmation with a wrong password', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('auth.password.confirm'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');
    });
});
