<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;

describe('Guest pages', function (): void {
    it('login page renders with form fields', function (): void {
        visit('/login')
            ->assertSee('Login')
            ->assertVisible('[name="email"]')
            ->assertVisible('[name="password"]')
            ->assertNoJavaScriptErrors();
    });

    it('register page renders with form fields', function (): void {
        visit('/register')
            ->assertSee('Register')
            ->assertVisible('[name="name"]')
            ->assertVisible('[name="email"]')
            ->assertVisible('[name="password"]')
            ->assertVisible('[name="password_confirmation"]')
            ->assertNoJavaScriptErrors();
    });

    it('forgot password page renders with form fields', function (): void {
        visit('/forgot-password')
            ->assertVisible('[name="email"]')
            ->assertNoJavaScriptErrors();
    });

    it('unauthenticated access to dashboard redirects to login', function (): void {
        visit('/dashboard')
            ->assertPathIs('/login')
            ->assertNoJavaScriptErrors();
    });
});

describe('Registration', function (): void {
    it('registers a new user and redirects to email verification', function (): void {
        // Guardrail only: keep the verification mail from sending during the
        // browser run. The notification itself is asserted in the feature tests.
        Notification::fake();

        visit('/register')
            ->fill('name', 'New User')
            ->fill('email', 'new@example.com')
            ->fill('password', 'Password1!')
            ->fill('password_confirmation', 'Password1!')
            ->click('[type="submit"]')
            ->waitForEvent('networkidle')
            ->assertPathIs('/email/verify')
            ->assertNoJavaScriptErrors();
    });

    it('stays on register page with empty submission', function (): void {
        visit('/register')
            ->click('[type="submit"]')
            ->assertPathIs('/register')
            ->assertNoJavaScriptErrors();
    });

    it('stays on register page when passwords do not match', function (): void {
        visit('/register')
            ->fill('name', 'Test User')
            ->fill('email', 'test@example.com')
            ->fill('password', 'Password1!')
            ->fill('password_confirmation', 'Different1!')
            ->click('[type="submit"]')
            ->assertPathIs('/register')
            ->assertNoJavaScriptErrors();
    });
});

describe('Login', function (): void {
    it('stays on login page with invalid credentials', function (): void {
        visit('/login')
            ->fill('email', 'nobody@nowhere.example')
            ->fill('password', 'WrongPass1!')
            ->click('[type="submit"]')
            ->waitForEvent('networkidle')
            ->assertPathIs('/login')
            ->assertNoJavaScriptErrors();
    });

    it('redirects to dashboard after successful login', function (): void {
        $user = User::factory()->create();

        visit('/login')
            ->fill('email', $user->email)
            ->fill('password', 'password')
            ->click('[type="submit"]')
            ->waitForEvent('networkidle')
            ->assertPathIs('/dashboard')
            ->assertNoJavaScriptErrors();
    });

    it('authenticated users visiting login are redirected away', function (): void {
        $user = User::factory()->create();

        visit('/login')
            ->fill('email', $user->email)
            ->fill('password', 'password')
            ->click('[type="submit"]')
            ->waitForEvent('networkidle')
            ->navigate('/login')
            ->waitForEvent('networkidle')
            ->assertPathIs('/dashboard')
            ->assertNoJavaScriptErrors();
    });
});

describe('Password reset', function (): void {
    it('forgot password form accepts submission', function (): void {
        visit('/forgot-password')
            ->fill('email', 'any@example.com')
            ->press('Kirim Email Reset Password')
            ->assertPathIs('/forgot-password')
            ->assertNoJavaScriptErrors();
    });
});
