<?php

use App\Models\User;

describe('Password Confirmation', function (): void {
    it('renders the confirm password page', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('auth.password.confirm'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/confirm-password'));
    });

    it('confirms password with the correct current password', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('auth.password.confirm'), ['password' => 'password'])
            ->assertRedirect();

        $this->assertCredentials(['email' => $user->email, 'password' => 'password']);
    });

    it('rejects confirmation with a wrong password', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('auth.password.confirm'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');
    });

    it('redirects guests to login', function (): void {
        $this->get(route('auth.password.confirm'))
            ->assertRedirectToRoute('auth.login');
    });
});
