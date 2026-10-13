<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ], $overrides);
}

describe('Registration', function (): void {
    test('renders the register page for guests', function (): void {
        $this->get(route('auth.register'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/register'));
    });

    test('redirects authenticated users away from register page', function (): void {
        $this->actingAs(User::factory()->create())
            ->get(route('auth.register'))
            ->assertRedirectToRoute('dashboard');
    });

    test('registers a new user with valid data', function (): void {
        Event::fake(Registered::class);

        $this->post(route('auth.register'), registrationPayload())
            ->assertRedirectToRoute('dashboard');

        $user = User::query()->where('email', 'john@example.com')->first();
        expect($user)->not->toBeNull();
        expect($user?->name)->toBe('John Doe');

        Event::assertDispatched(Registered::class);
    });

    test('authenticates the user after registration', function (): void {
        Notification::fake();

        $this->post(route('auth.register'), registrationPayload([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]))->assertRedirectToRoute('dashboard');

        $user = User::query()->where('email', 'jane@example.com')->first();
        expect($user)->not->toBeNull();
        Notification::assertSentTo($user, VerifyEmail::class);

        // Confirm auth persists: accessing dashboard redirects to email verify (not login)
        $this->get(route('dashboard'))->assertRedirectToRoute('auth.verification.notice');
    });

    test('requires name', function (): void {
        $this->post(route('auth.register'), registrationPayload(['name' => '']))
            ->assertSessionHasErrors('name');
    });

    test('requires a valid email', function (): void {
        $this->post(route('auth.register'), registrationPayload(['email' => 'not-an-email']))
            ->assertSessionHasErrors('email');
    });

    test('requires a unique email', function (): void {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('auth.register'), registrationPayload(['email' => 'taken@example.com']))
            ->assertSessionHasErrors('email');
    });

    test('requires password confirmation to match', function (): void {
        $this->post(route('auth.register'), registrationPayload(['password_confirmation' => 'DifferentPass1!']))
            ->assertSessionHasErrors('password');
    });

    test('requires a password', function (): void {
        $this->post(route('auth.register'), registrationPayload([
            'password' => '',
            'password_confirmation' => '',
        ]))->assertSessionHasErrors('password');
    });
});
