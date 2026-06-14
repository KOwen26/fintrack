<?php

use App\Models\User;

describe('Password Confirmation', function () {
    it('renders the confirm password page', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('auth.password.confirm'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/confirm-password'));
    });

    it('confirms password with the correct current password', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/confirm-password', ['password' => 'password'])
            ->assertRedirect();

        $this->assertCredentials(['email' => $user->email, 'password' => 'password']);
    });

    it('rejects confirmation with a wrong password', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/confirm-password', ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');
    });

    it('redirects guests to login', function () {
        $this->get(route('auth.password.confirm'))
            ->assertRedirect(route('auth.login'));
    });
});
