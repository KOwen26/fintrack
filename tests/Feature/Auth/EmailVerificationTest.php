<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

describe('Email Verification', function (): void {
    it('renders the email verification page for unverified users', function (): void {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('auth.verification.notice'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/verify-email'));
    });

    it('redirects already-verified users away from verification notice', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('auth.verification.notice'))
            ->assertRedirectToRoute('dashboard');
    });

    it('sends a verification email notification', function (): void {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('auth.verification.send'))
            ->assertRedirect();

        Notification::assertSentTo($user, VerifyEmail::class);
    });

    it('does not resend verification if already verified', function (): void {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('auth.verification.send'))
            ->assertRedirect();

        Notification::assertNotSentTo($user, VerifyEmail::class);
    });

    it('verifies email with a valid signed URL', function (): void {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'auth.verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)
            ->get($verificationUrl)
            ->assertRedirectContains(route('dashboard'));

        expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    });

    it('does not verify email with an invalid hash', function (): void {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'auth.verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)
            ->get($verificationUrl)
            ->assertForbidden();

        expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    });

    it('redirects guests to login from protected routes when unverified', function (): void {
        $this->get(route('dashboard'))
            ->assertRedirectToRoute('auth.login');
    });
});
