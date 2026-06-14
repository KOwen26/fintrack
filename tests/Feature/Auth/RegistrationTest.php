<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

describe('Registration', function () {
    it('renders the register page for guests', function () {
        $this->get(route('auth.register'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/register'));
    });

    it('redirects authenticated users away from register page', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('auth.register'))
            ->assertRedirect(route('dashboard'));
    });

    it('registers a new user with valid data', function () {
        Event::fake();

        $response = $this->post(route('auth.register'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'john@example.com', 'name' => 'John Doe']);
        Event::assertDispatched(Registered::class);
    });

    it('authenticates the user after registration', function () {
        Notification::fake();

        $this->post(route('auth.register'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertRedirect(route('dashboard'));

        // Confirm auth persists: accessing dashboard redirects to email verify (not login)
        $this->get(route('dashboard'))->assertRedirect(route('auth.verification.notice'));
    });

    it('requires name', function () {
        $this->post(route('auth.register'), [
            'name' => '',
            'email' => 'john@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertSessionHasErrors('name');
    });

    it('requires a valid email', function () {
        $this->post(route('auth.register'), [
            'name' => 'John',
            'email' => 'not-an-email',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertSessionHasErrors('email');
    });

    it('requires a unique email', function () {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('auth.register'), [
            'name' => 'John',
            'email' => 'taken@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertSessionHasErrors('email');
    });

    it('requires password confirmation to match', function () {
        $this->post(route('auth.register'), [
            'name' => 'John',
            'email' => 'john@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'DifferentPass1!',
        ])->assertSessionHasErrors('password');
    });

    it('requires a password', function () {
        $this->post(route('auth.register'), [
            'name' => 'John',
            'email' => 'john@example.com',
            'password' => '',
            'password_confirmation' => '',
        ])->assertSessionHasErrors('password');
    });
});
