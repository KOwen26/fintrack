<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

describe('Password Reset', function (): void {
    describe('Forgot Password', function (): void {
        test('renders the forgot password page', function (): void {
            $this->get(route('auth.password.request'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('auth/forgot-password'));
        });

        test('sends a password reset link to a registered email', function (): void {
            Notification::fake();
            $user = User::factory()->create();

            $this->post(route('auth.password.email'), ['email' => $user->email])
                ->assertSessionHas('status');

            Notification::assertSentTo($user, ResetPassword::class);
        });

        test('returns a validation error for an unregistered email', function (): void {
            Notification::fake();

            $this->post(route('auth.password.email'), ['email' => 'notfound@example.com'])
                ->assertSessionHasErrors('email');

            Notification::assertNothingSent();
        });

        test('validates that email is required', function (): void {
            $this->post(route('auth.password.email'), ['email' => ''])
                ->assertSessionHasErrors('email');
        });
    });

    describe('Reset Password', function (): void {
        test('renders the reset password page with a valid token', function (): void {
            Notification::fake();
            $user = User::factory()->create();

            $this->post(route('auth.password.email'), ['email' => $user->email]);
            Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
                $this->get(route('auth.password.reset', ['token' => $notification->token]))
                    ->assertOk()
                    ->assertInertia(fn ($page) => $page->component('auth/reset-password'));

                return true;
            });
        });

        test('resets the password with a valid token', function (): void {
            Notification::fake();
            $user = User::factory()->create();

            $this->post(route('auth.password.email'), ['email' => $user->email]);

            Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
                $this->post(route('auth.password.store'), [
                    'token' => $notification->token,
                    'email' => $user->email,
                    'password' => 'NewPassword1!',
                    'password_confirmation' => 'NewPassword1!',
                ])->assertRedirectToRoute('auth.login');

                return true;
            });
        });

        test('requires password confirmation to match during reset', function (): void {
            Notification::fake();
            $user = User::factory()->create();

            $this->post(route('auth.password.email'), ['email' => $user->email]);

            Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
                $this->post(route('auth.password.store'), [
                    'token' => $notification->token,
                    'email' => $user->email,
                    'password' => 'NewPassword1!',
                    'password_confirmation' => 'DifferentPass1!',
                ])->assertSessionHasErrors('password');

                return true;
            });
        });

        test('requires a valid token to reset password', function (): void {
            $user = User::factory()->create();

            $this->post(route('auth.password.store'), [
                'token' => 'invalid-token',
                'email' => $user->email,
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
            ])->assertSessionHasErrors('email');
        });
    });
});
