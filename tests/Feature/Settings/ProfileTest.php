<?php

use App\Models\User;

describe('Profile Settings', function () {
    it('renders the profile page for authenticated users', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard/settings/profile')
                ->has('mustVerifyEmail')
            );
    });

    it('redirects guests to login', function () {
        $this->get(route('profile.edit'))
            ->assertRedirect(route('auth.login'));
    });

    it('updates name and email', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        expect($user->name)->toBe('Updated Name')
            ->and($user->email)->toBe('updated@example.com');
    });

    it('clears email verification when email is changed', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'new-email@example.com',
            ]);

        expect($user->fresh()->email_verified_at)->toBeNull();
    });

    it('does not clear email verification when email is unchanged', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'New Name',
                'email' => $user->email,
            ]);

        expect($user->fresh()->email_verified_at)->not->toBeNull();
    });

    it('validates that name is required for profile update', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => '', 'email' => $user->email])
            ->assertSessionHasErrors('name');
    });

    it('validates that email is valid for profile update', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
    });

    it('deletes the account with the correct password', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    });

    it('does not delete the account with a wrong password', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    });
});
