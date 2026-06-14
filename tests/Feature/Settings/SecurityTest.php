<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('Security Settings', function () {
    it('renders the security page for authenticated users', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('dashboard/settings/security'));
    });

    it('redirects guests to login', function () {
        $this->get(route('security.edit'))
            ->assertRedirect(route('auth.login'));
    });

    it('updates the password with valid data', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('security.update'), [
                'current_password' => 'password',
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
            ])
            ->assertRedirect();

        expect(Hash::check('NewPassword1!', $user->fresh()->password))->toBeTrue();
    });

    it('returns success flash after password update', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('security.update'), [
                'current_password' => 'password',
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
            ])
            ->assertSessionHas('type', 'success');
    });

    it('rejects an incorrect current password', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('security.update'), [
                'current_password' => 'wrong-password',
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
            ])
            ->assertSessionHasErrors('current_password');
    });

    it('requires password confirmation to match', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('security.update'), [
                'current_password' => 'password',
                'password' => 'NewPassword1!',
                'password_confirmation' => 'DifferentPass1!',
            ])
            ->assertSessionHasErrors('password');
    });

    it('requires current password to be present', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('security.update'), [
                'current_password' => '',
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
            ])
            ->assertSessionHasErrors('current_password');
    });

    it('enforces password strength rules', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('security.update'), [
                'current_password' => 'password',
                'password' => 'weak',
                'password_confirmation' => 'weak',
            ])
            ->assertSessionHasErrors('password');
    });
});
