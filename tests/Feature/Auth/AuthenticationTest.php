<?php

use App\Models\User;

describe('Authentication', function (): void {
    test('renders the login page for guests', function (): void {
        $this->get(route('auth.login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/login'));
    });

    test('redirects authenticated users away from login page', function (): void {
        $this->actingAs(User::factory()->create())
            ->get(route('auth.login'))
            ->assertRedirectToRoute('dashboard');
    });

    test('authenticates a user with valid credentials', function (): void {
        $user = User::factory()->create();

        $this->post(route('auth.login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirectToRoute('dashboard');

        $this->assertAuthenticatedAs($user);
    });

    test('rejects invalid credentials', function (): void {
        User::factory()->create(['email' => 'user@example.com']);

        $this->post(route('auth.login'), [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    });

    test('rejects login for non-existent user', function (): void {
        $this->post(route('auth.login'), [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    });

    test('logs out an authenticated user', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('auth.logout'))
            ->assertRedirect('/');

        $this->assertGuest();
    });

    test('redirects unverified users to email verification when accessing protected routes', function (): void {
        $user = User::factory()->unverified()->create();

        $this->post(route('auth.login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirectToRoute('dashboard');

        // Subsequent access to a verified-only route redirects to email verification
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirectToRoute('auth.verification.notice');
    });
});
