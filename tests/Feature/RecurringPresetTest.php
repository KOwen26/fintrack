<?php

use App\Enums\RecurringFrequency;
use App\Enums\TransactionPresetType;
use App\Models\Account;
use App\Models\TransactionRecurringPreset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createUserWithAccount(): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    return [$user, $account];
}

it('lists only the authenticated user recurring presets', function (): void {
    [$user, $account] = createUserWithAccount();
    $mine = TransactionRecurringPreset::factory()->create([
        'created_by' => $user->id,
        'account_id' => $account->id,
    ]);
    TransactionRecurringPreset::factory()->create(); // another user

    $this->actingAs($user)->get(route('recurring-presets.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('recurring-presets/index')
            ->has('presets', 1)
            ->where('presets.0.id', $mine->id)
        );
});

it('stores a new recurring preset', function (): void {
    [$user, $account] = createUserWithAccount();

    $this->actingAs($user)->post(route('recurring-presets.store'), [
        'account_id' => $account->id,
        'category_id' => null,
        'name' => 'Monthly Rent',
        'type' => TransactionPresetType::Expense->value,
        'frequency' => RecurringFrequency::Monthly->value,
        'amount' => 3_000_000,
        'description' => null,
        'next_run_date' => today()->addDay()->toDateString(),
        'recurrence_end_date' => null,
    ])->assertRedirect();

    expect(TransactionRecurringPreset::where('name', 'Monthly Rent')->where('created_by', $user->id)->exists())->toBeTrue();
});

it('toggles a recurring preset on and off', function (): void {
    [$user, $account] = createUserWithAccount();
    $preset = TransactionRecurringPreset::factory()->create([
        'created_by' => $user->id,
        'account_id' => $account->id,
        'is_active' => true,
    ]);

    // Toggle off
    $this->actingAs($user)->post(route('recurring-presets.toggle', $preset))
        ->assertRedirect();
    expect($preset->fresh()->is_active)->toBeFalse();

    // Toggle on
    $this->actingAs($user)->post(route('recurring-presets.toggle', $preset))
        ->assertRedirect();
    expect($preset->fresh()->is_active)->toBeTrue();
});

it('prevents toggling another user recurring preset', function (): void {
    $user = User::factory()->create();
    $other = TransactionRecurringPreset::factory()->create();

    $this->actingAs($user)->post(route('recurring-presets.toggle', $other))
        ->assertForbidden();
});

it('soft-deletes own recurring preset', function (): void {
    [$user, $account] = createUserWithAccount();
    $preset = TransactionRecurringPreset::factory()->create([
        'created_by' => $user->id,
        'account_id' => $account->id,
    ]);

    $this->actingAs($user)->delete(route('recurring-presets.destroy', $preset))
        ->assertRedirect();

    expect($preset->fresh())->toBeNull();
    expect(TransactionRecurringPreset::withTrashed()->find($preset->id))->not->toBeNull();
});
