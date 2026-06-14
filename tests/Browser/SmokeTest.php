<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;

describe('Guest pages', function () {
    it('login page renders with form fields', function () {
        visit('/login')
            ->assertSee('Login')
            ->assertVisible('[name="email"]')
            ->assertVisible('[name="password"]');
    });

    it('register page renders with form fields', function () {
        visit('/register')
            ->assertSee('Register')
            ->assertVisible('[name="name"]')
            ->assertVisible('[name="email"]')
            ->assertVisible('[name="password"]')
            ->assertVisible('[name="password_confirmation"]');
    });

    it('forgot password page renders with form fields', function () {
        visit('/forgot-password')
            ->assertVisible('[name="email"]');
    });

    it('unauthenticated access to dashboard redirects to login', function () {
        visit('/dashboard')
            ->assertPathIs('/login');
    });
});

describe('Registration', function () {
    it('registers a new user and redirects to email verification', function () {
        Notification::fake();

        visit('/register')
            ->fill('name', 'New User')
            ->fill('email', 'new@example.com')
            ->fill('password', 'Password1!')
            ->fill('password_confirmation', 'Password1!')
            ->click('[type="submit"]')
            ->waitForEvent('networkidle')
            ->assertPathIs('/email/verify');
    });

    it('stays on register page with empty submission', function () {
        visit('/register')
            ->click('[type="submit"]')
            ->assertPathIs('/register');
    });

    it('stays on register page when passwords do not match', function () {
        visit('/register')
            ->fill('name', 'Test User')
            ->fill('email', 'test@example.com')
            ->fill('password', 'Password1!')
            ->fill('password_confirmation', 'Different1!')
            ->click('[type="submit"]')
            ->assertPathIs('/register');
    });
});

describe('Login', function () {
    it('stays on login page with invalid credentials', function () {
        visit('/login')
            ->fill('email', 'nobody@nowhere.example')
            ->fill('password', 'WrongPass1!')
            ->click('[type="submit"]')
            ->waitForEvent('networkidle')
            ->assertPathIs('/login');
    });

    it('redirects to dashboard after successful login', function () {
        $user = User::factory()->create();

        visit('/login')
            ->fill('email', $user->email)
            ->fill('password', 'password')
            ->click('[type="submit"]')
            ->waitForEvent('networkidle')
            ->assertPathIs('/dashboard');
    });

    it('authenticated users visiting login are redirected away', function () {
        $user = User::factory()->create();

        visit('/login')
            ->fill('email', $user->email)
            ->fill('password', 'password')
            ->click('[type="submit"]')
            ->waitForEvent('networkidle')
            ->navigate('/login')
            ->waitForEvent('networkidle')
            ->assertPathIs('/dashboard');
    });
});

describe('Password reset', function () {
    it('forgot password form accepts submission', function () {
        visit('/forgot-password')
            ->fill('email', 'any@example.com')
            ->press('Kirim Email Reset Password')
            ->assertPathIs('/forgot-password');
    });
});
