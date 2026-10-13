<?php

use App\Models\User;

describe('Profile Settings', function (): void {
    it('renders the profile page for authenticated users', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('app/settings/profile')
                ->has('mustVerifyEmail')
            );
    });

    it('redirects guests to login', function (): void {
        $this->get(route('profile.edit'))
            ->assertRedirectToRoute('auth.login');
    });

    it('updates name and email', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ])
            ->assertRedirectToRoute('profile.edit');

        $user->refresh();
        expect($user->name)->toBe('Updated Name')
            ->and($user->email)->toBe('updated@example.com');
    });

    it('clears email verification when email is changed', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'new-email@example.com',
            ]);

        expect($user->fresh()->email_verified_at)->toBeNull();
    });

    it('does not clear email verification when email is unchanged', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'New Name',
                'email' => $user->email,
            ]);

        expect($user->fresh()->email_verified_at)->not->toBeNull();
    });

    it('validates that name is required for profile update', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => '', 'email' => $user->email])
            ->assertSessionHasErrors('name');
    });

    it('validates that email is valid for profile update', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
    });

    it('deletes the account with the correct password', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertGuest();
        expect($user->fresh())->toBeNull();
    });

    it('does not delete the account with a wrong password', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');

        expect($user->fresh())->not->toBeNull();
    });
});
