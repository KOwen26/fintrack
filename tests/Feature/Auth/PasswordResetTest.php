<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

describe('Password Reset', function () {
    describe('Forgot Password', function () {
        it('renders the forgot password page', function () {
            $this->get(route('auth.password.request'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('auth/forgot-password'));
        });

        it('sends a password reset link to a registered email', function () {
            Notification::fake();
            $user = User::factory()->create();

            $this->post(route('auth.password.email'), ['email' => $user->email])
                ->assertSessionHas('status');

            Notification::assertSentTo($user, ResetPassword::class);
        });

        it('returns a validation error for an unregistered email', function () {
            Notification::fake();

            $this->post(route('auth.password.email'), ['email' => 'notfound@example.com'])
                ->assertSessionHasErrors('email');

            Notification::assertNothingSent();
        });

        it('validates that email is required', function () {
            $this->post(route('auth.password.email'), ['email' => ''])
                ->assertSessionHasErrors('email');
        });
    });

    describe('Reset Password', function () {
        it('renders the reset password page with a valid token', function () {
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

        it('resets the password with a valid token', function () {
            Notification::fake();
            $user = User::factory()->create();

            $this->post(route('auth.password.email'), ['email' => $user->email]);

            Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
                $this->post(route('auth.password.store'), [
                    'token' => $notification->token,
                    'email' => $user->email,
                    'password' => 'NewPassword1!',
                    'password_confirmation' => 'NewPassword1!',
                ])->assertRedirect(route('auth.login'));

                return true;
            });
        });

        it('requires password confirmation to match during reset', function () {
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

        it('requires a valid token to reset password', function () {
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
