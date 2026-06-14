<?php

use App\Models\User;

describe('Authentication', function () {
    it('renders the login page for guests', function () {
        $this->get(route('auth.login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/login'));
    });

    it('redirects authenticated users away from login page', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('auth.login'))
            ->assertRedirect(route('dashboard'));
    });

    it('authenticates a user with valid credentials', function () {
        $user = User::factory()->create();

        $this->post(route('auth.login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    });

    it('rejects invalid credentials', function () {
        User::factory()->create(['email' => 'user@example.com']);

        $this->post(route('auth.login'), [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    });

    it('rejects login for non-existent user', function () {
        $this->post(route('auth.login'), [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    });

    it('logs out an authenticated user', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('auth.logout'))
            ->assertRedirect('/');

        $this->assertGuest();
    });

    it('redirects unverified users to email verification when accessing protected routes', function () {
        $user = User::factory()->unverified()->create();

        $this->post(route('auth.login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        // Subsequent access to a verified-only route redirects to email verification
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('auth.verification.notice'));
    });
});
