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
    test('renders the security page for authenticated users', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('app/settings/security'));
    });

    test('updates the password with valid data', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('security.update'), securityUpdatePayload())
            ->assertRedirect();

        expect(Hash::check('NewPassword1!', $user->fresh()->password))->toBeTrue();
    });

    test('returns success flash after password update', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('security.update'), securityUpdatePayload())
            ->assertSessionHas('type', 'success');
    });

    test('rejects an incorrect current password', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('security.update'), securityUpdatePayload(['current_password' => 'wrong-password']))
            ->assertSessionHasErrors('current_password');
    });

    test('requires password confirmation to match', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('security.update'), securityUpdatePayload(['password_confirmation' => 'DifferentPass1!']))
            ->assertSessionHasErrors('password');
    });

    test('requires current password to be present', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('security.update'), securityUpdatePayload(['current_password' => '']))
            ->assertSessionHasErrors('current_password');
    });

    test('enforces password strength rules', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('security.update'), securityUpdatePayload([
                'password' => 'weak',
                'password_confirmation' => 'weak',
            ]))
            ->assertSessionHasErrors('password');
    });
});
