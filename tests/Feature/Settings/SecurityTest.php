<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

function securityUpdatePayload(array $overrides = []): array
{
    return array_merge([
        'current_password' => 'password',
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
    ], $overrides);
}

describe('Security Settings', function (): void {
    it('renders the security page for authenticated users', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('app/settings/security'));
    });

    it('redirects guests to login', function (): void {
        $this->get(route('security.edit'))
            ->assertRedirectToRoute('auth.login');
    });

    it('updates the password with valid data', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('security.update'), securityUpdatePayload())
            ->assertRedirect();

        expect(Hash::check('NewPassword1!', $user->fresh()->password))->toBeTrue();
    });

    it('returns success flash after password update', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('security.update'), securityUpdatePayload())
            ->assertSessionHas('type', 'success');
    });

    it('rejects an incorrect current password', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('security.update'), securityUpdatePayload(['current_password' => 'wrong-password']))
            ->assertSessionHasErrors('current_password');
    });

    it('requires password confirmation to match', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('security.update'), securityUpdatePayload(['password_confirmation' => 'DifferentPass1!']))
            ->assertSessionHasErrors('password');
    });

    it('requires current password to be present', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('security.update'), securityUpdatePayload(['current_password' => '']))
            ->assertSessionHasErrors('current_password');
    });

    it('enforces password strength rules', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('security.update'), securityUpdatePayload([
                'password' => 'weak',
                'password_confirmation' => 'weak',
            ]))
            ->assertSessionHasErrors('password');
    });
});
